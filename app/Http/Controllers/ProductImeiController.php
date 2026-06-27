<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Supplier;
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
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($product->user_id !== $ownerId) {
            abort(403);
        }

        $query = $product->imeis()->with('supplier', 'saleBill', 'purchaseBill');

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
            'imeis' => $imeis,
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
            'product_name'       => $existing->product->name ?? 'Unknown',
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
                        'product_name' => $existing->product->name ?? 'Unknown',
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
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($product->user_id !== $ownerId || $imei->product_id !== $product->id) {
            abort(403);
        }

        if ($imei->sale_bill_id) {
            return response()->json(['error' => 'Cannot delete a sold IMEI'], 422);
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
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $imeiCode = trim($request->input('imei'));

        if (empty($imeiCode)) {
            return response()->json(['error' => 'IMEI required'], 400);
        }

        $imei = ProductImei::where('user_id', $ownerId)
            ->where('imei', $imeiCode)
            ->with(['product', 'supplier', 'purchaseBill', 'saleBill.customer', 'saleBill.creator'])
            ->first();

        if (!$imei) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'imei' => $imeiCode,
            'product' => [
                'id' => $imei->product_id,
                'name' => $imei->product->name ?? 'Unknown',
            ],
            'supplier' => $imei->supplier ? [
                'id' => $imei->supplier_id,
                'name' => $imei->supplier->name,
            ] : null,
            'purchase_bill' => $imei->purchaseBill ? [
                'id' => $imei->purchase_bill_id,
                'reference_number' => $imei->purchaseBill->reference_number,
                'purchase_date' => $imei->purchaseBill->purchase_date,
            ] : null,
            'purchased_at' => $imei->purchased_at,
            'unit_cost' => $imei->unit_cost,
            'is_sold' => $imei->isSold(),
            'sale_bill' => $imei->saleBill ? [
                'id' => $imei->sale_bill_id,
                'customer' => $imei->saleBill->customer->name ?? 'Walk-in',
                'created_by' => $imei->saleBill->creator->name ?? 'Unknown',
                'sold_at' => $imei->sold_at,
            ] : null,
            'selling_price' => $imei->selling_price,
        ]);
    }

    /**
     * Get available (unsold) IMEIs for a product - used during POS sale.
     */
    public function available(Request $request, Product $product)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($product->user_id !== $ownerId) {
            abort(403);
        }

        $imeis = ProductImei::where('user_id', $ownerId)
            ->where('product_id', $product->id)
            ->whereNull('sale_bill_id')
            ->with('supplier')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'imeis' => $imeis->map(fn($i) => [
                'id' => $i->id,
                'imei' => $i->imei,
                'supplier_name' => $i->supplier->name ?? null,
                'purchased_at' => $i->purchased_at,
                'unit_cost' => $i->unit_cost,
            ]),
            'count' => $imeis->count(),
        ]);
    }
}
