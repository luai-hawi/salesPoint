<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImeiController extends Controller
{
    /**
     * Get all IMEIs for a product with optional filtering.
     */
    public function index(Request $request, Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $visibility = $this->visibility($user);

        if ($product->user_id !== $ownerId) {
            abort(403);
        }

        $query = $product->imeis()->with('purchaseBill', 'saleBill');

        if ($visibility['suppliers']) {
            $query->with('supplier');
        }

        if ($request->filled('filter')) {
            if ($request->filter === 'sold') {
                $query->whereNotNull('sale_bill_id');
            } elseif ($request->filter === 'unsold') {
                $query->whereNull('sale_bill_id');
            }
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $imeis = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'imeis' => $imeis->map(fn (ProductImei $imei) => $this->serializeImei($imei, $visibility)),
            'total' => $imeis->count(),
            'sold_count' => $imeis->whereNotNull('sale_bill_id')->count(),
            'unsold_count' => $imeis->whereNull('sale_bill_id')->count(),
        ]);
    }

    /**
     * Check if an IMEI already exists.
     */
    public function checkExists(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['create_products', 'edit_products']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $imei = trim($request->input('imei'));
        $productId = $request->input('product_id'); // optional: validate against a specific product

        if (empty($imei)) {
            return response()->json(['exists' => false]);
        }

        $existing = ProductImei::where('user_id', $ownerId)
            ->where('imei', $imei)
            ->with('product')
            ->first();

        if (!$existing) {
            // When validating for a specific product, distinguish "not found at all" vs "wrong product"
            return response()->json([
                'exists' => false,
                'belongs_to_product' => false,
            ]);
        }

        $isSold = !is_null($existing->sale_bill_id);
        $belongsToProduct = $productId ? ($existing->product_id == $productId) : true;

        return response()->json([
            'exists'             => true,
            'imei'               => $imei,
            'product_name'       => $existing->product->name ?? __('products_ui.misc.unknown_product'),
            'product_id'         => $existing->product_id,
            'is_sold'            => $isSold,
            'belongs_to_product' => $belongsToProduct,
        ]);
    }

    /**
     * Store new IMEIs for a product (from product create/edit page).
     */
    public function store(Request $request, Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($product->user_id !== $ownerId) {
            abort(403);
        }

        $request->validate([
            'imeis' => 'required|array|min:1',
            'imeis.*' => 'required|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id,user_id,' . $ownerId,
            'purchased_at' => 'nullable|date',
            'unit_cost' => 'nullable|numeric|min:0',
            'force' => 'nullable|boolean',
        ]);

        $supplierId = $request->input('supplier_id');
        $purchasedAt = $request->input('purchased_at');
        $unitCost = $request->input('unit_cost');
        $force = $request->boolean('force', false);

        $results = [];
        $duplicates = [];

        DB::beginTransaction();
        try {
            foreach ($request->imeis as $imei) {
                $imei = trim($imei);
                if (empty($imei)) continue;

                $existing = ProductImei::where('user_id', $ownerId)->where('imei', $imei)->first();
                if ($existing && !$force) {
                    $duplicates[] = [
                        'imei' => $imei,
                        'product_name' => $existing->product->name ?? __('products_ui.misc.unknown_product'),
                        'product_id' => $existing->product_id,
                    ];
                    continue;
                }

                $imeiRecord = ProductImei::create([
                    'user_id' => $ownerId,
                    'product_id' => $product->id,
                    'imei' => $imei,
                    'supplier_id' => $supplierId,
                    'purchased_at' => $purchasedAt,
                    'unit_cost' => $unitCost,
                ]);
                $results[] = $imeiRecord;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        if (!empty($duplicates) && !$force) {
            return response()->json([
                'warning' => 'duplicate_imeis',
                'duplicates' => $duplicates,
                'saved' => $results,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'saved' => count($results),
            'imeis' => $results,
        ]);
    }

    /**
     * Delete an IMEI (only if not sold).
     */
    public function destroy(Request $request, Product $product, ProductImei $imei)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($product->user_id !== $ownerId || $imei->product_id !== $product->id) {
            abort(403);
        }

        if ($imei->sale_bill_id) {
            return response()->json(['error' => __('products_ui.validation.sold_imei_locked')], 422);
        }

        $imei->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Search for an IMEI and return its full history.
     */
    public function search(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $visibility = $this->visibility($user);

        $imeiCode = trim($request->input('imei'));

        if (empty($imeiCode)) {
            return response()->json(['error' => __('products_ui.validation.imei_required')], 400);
        }

        $query = ProductImei::where('user_id', $ownerId)
            ->where('imei', $imeiCode)
            ->with('product');

        if ($visibility['suppliers']) {
            $query->with('supplier');
        }

        if ($visibility['purchase_bills']) {
            $query->with('purchaseBill');
        }

        if ($visibility['bills']) {
            $query->with('saleBill');

            if ($visibility['customers']) {
                $query->with('saleBill.customer');
            }

            $query->with('saleBill.creator');
        }

        $imei = $query->first();

        if (!$imei) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'imei' => $imeiCode,
            'product' => [
                'id' => $imei->product_id,
                'name' => $imei->product->name ?? __('products_ui.misc.unknown_product'),
            ],
            'supplier' => $visibility['suppliers'] && $imei->supplier ? [
                'id' => $imei->supplier_id,
                'name' => $imei->supplier->name,
            ] : null,
            'purchase_bill' => $visibility['purchase_bills'] && $imei->purchaseBill ? [
                'id' => $imei->purchase_bill_id,
                'reference_number' => $imei->purchaseBill->reference_number,
                'purchase_date' => $imei->purchaseBill->purchase_date,
            ] : null,
            'purchased_at' => $imei->purchased_at,
            'unit_cost' => $visibility['costs'] ? $imei->unit_cost : null,
            'is_sold' => $imei->isSold(),
            'sale_bill' => $visibility['bills'] && $imei->saleBill ? [
                'id' => $imei->sale_bill_id,
                'customer' => $visibility['customers'] ? ($imei->saleBill->customer->name ?? __('products_ui.misc.walk_in_customer')) : null,
                'created_by' => $imei->saleBill->creator->name ?? __('products_ui.misc.unknown_user'),
                'sold_at' => $imei->sold_at,
                'selling_price' => $imei->selling_price,
            ] : null,
        ]);
    }

    /**
     * Get available (unsold) IMEIs for a product - used during POS sale.
     */
    public function available(Request $request, Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products', 'create_bills']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $visibility = $this->visibility($user);

        if ($product->user_id !== $ownerId) {
            abort(403);
        }

        $query = ProductImei::where('user_id', $ownerId)
            ->where('product_id', $product->id)
            ->whereNull('sale_bill_id')
            ->orderBy('created_at', 'desc');

        if ($visibility['suppliers']) {
            $query->with('supplier');
        }

        $imeis = $query->get();

        return response()->json([
            'imeis' => $imeis->map(fn($i) => [
                'id' => $i->id,
                'imei' => $i->imei,
                'supplier_name' => $visibility['suppliers'] ? ($i->supplier->name ?? null) : null,
                'purchased_at' => $visibility['purchase_bills'] ? $i->purchased_at : null,
                'unit_cost' => $visibility['costs'] ? $i->unit_cost : null,
            ]),
            'count' => $imeis->count(),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function ensureEmployeeHasAnyPermission(User $user, array $permissions): void
    {
        if ($user->role !== 'employee') {
            return;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }

    /**
     * @return array{suppliers:bool,purchase_bills:bool,bills:bool,customers:bool,costs:bool}
     */
    private function visibility(User $user): array
    {
        $fullAccess = $user->role !== 'employee';

        return [
            'suppliers' => $fullAccess || $user->hasPermission('view_suppliers'),
            'purchase_bills' => $fullAccess || $user->hasPermission('view_purchase_bills'),
            'bills' => $fullAccess || $user->hasPermission('view_bills'),
            'customers' => $fullAccess || $user->hasPermission('view_customers'),
            'costs' => $fullAccess || $user->hasPermission('view_purchase_bills'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeImei(ProductImei $imei, array $visibility): array
    {
        return [
            'id' => $imei->id,
            'imei' => $imei->imei,
            'is_sold' => $imei->isSold(),
            'purchased_at' => $imei->purchased_at,
            'sold_at' => $visibility['bills'] ? $imei->sold_at : null,
            'selling_price' => $visibility['bills'] ? $imei->selling_price : null,
            'supplier' => $visibility['suppliers'] && $imei->supplier ? [
                'id' => $imei->supplier_id,
                'name' => $imei->supplier->name,
            ] : null,
            'purchase_bill_id' => $visibility['purchase_bills'] ? $imei->purchase_bill_id : null,
            'sale_bill_id' => $visibility['bills'] ? $imei->sale_bill_id : null,
            'unit_cost' => $visibility['costs'] ? $imei->unit_cost : null,
        ];
    }
}
