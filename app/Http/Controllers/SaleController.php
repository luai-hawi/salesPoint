<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleRule;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private function ownerId(): int
    {
        $user = auth()->user();
        return $user->role === 'employee' ? $user->shop_owner_id : $user->id;
    }

    public function index()
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('view_sales')) {
            abort(403);
        }

        $ownerId = $this->ownerId();

        $sales = Sale::where('user_id', $ownerId)
            ->with(['rules.product'])
            ->orderByDesc('created_at')
            ->get();

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'selling_price']);

        return view('sales.index', compact('sales', 'products'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('create_sales')) {
            abort(403);
        }

        $data = $request->validate([
            'name'                        => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'start_date'                  => 'nullable|date',
            'end_date'                    => 'nullable|date|after_or_equal:start_date',
            'is_active'                   => 'boolean',
            'rules'                       => 'array',
            'rules.*.product_id'          => 'required|integer|exists:products,id',
            'rules.*.discount_type'       => 'required|in:percentage,amount',
            'rules.*.discount_value'      => 'required|numeric|min:0.01',
            'rules.*.applies_every_n'     => 'required|integer|min:1',
        ]);

        $ownerId = $this->ownerId();

        DB::transaction(function () use ($data, $ownerId, $request) {
            $sale = Sale::create([
                'user_id'     => $ownerId,
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date'  => $data['start_date'] ?? null,
                'end_date'    => $data['end_date'] ?? null,
                'is_active'   => $request->boolean('is_active', true),
            ]);

            foreach ($data['rules'] ?? [] as $rule) {
                // Ensure the product belongs to this owner
                $product = Product::where('id', $rule['product_id'])
                    ->where('user_id', $ownerId)
                    ->first();

                if ($product) {
                    SaleRule::create([
                        'sale_id'         => $sale->id,
                        'product_id'      => $rule['product_id'],
                        'discount_type'   => $rule['discount_type'],
                        'discount_value'  => $rule['discount_value'],
                        'applies_every_n' => $rule['applies_every_n'],
                    ]);
                }
            }
        });

        return redirect()->route('sales.index')
            ->with('success', __('sales.Sale created successfully'));
    }

    public function update(Request $request, Sale $sale)
    {
        $this->authorizeSale($sale);

        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('edit_sales')) {
            abort(403);
        }

        $data = $request->validate([
            'name'                        => 'required|string|max:255',
            'description'                 => 'nullable|string|max:1000',
            'start_date'                  => 'nullable|date',
            'end_date'                    => 'nullable|date|after_or_equal:start_date',
            'is_active'                   => 'boolean',
            'rules'                       => 'array',
            'rules.*.product_id'          => 'required|integer|exists:products,id',
            'rules.*.discount_type'       => 'required|in:percentage,amount',
            'rules.*.discount_value'      => 'required|numeric|min:0.01',
            'rules.*.applies_every_n'     => 'required|integer|min:1',
        ]);

        $ownerId = $this->ownerId();

        DB::transaction(function () use ($sale, $data, $ownerId, $request) {
            $sale->update([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date'  => $data['start_date'] ?? null,
                'end_date'    => $data['end_date'] ?? null,
                'is_active'   => $request->boolean('is_active', true),
            ]);

            // Replace rules
            $sale->rules()->delete();

            foreach ($data['rules'] ?? [] as $rule) {
                $product = Product::where('id', $rule['product_id'])
                    ->where('user_id', $ownerId)
                    ->first();

                if ($product) {
                    SaleRule::create([
                        'sale_id'         => $sale->id,
                        'product_id'      => $rule['product_id'],
                        'discount_type'   => $rule['discount_type'],
                        'discount_value'  => $rule['discount_value'],
                        'applies_every_n' => $rule['applies_every_n'],
                    ]);
                }
            }
        });

        return redirect()->route('sales.index')
            ->with('success', __('sales.Sale updated successfully'));
    }

    public function destroy(Sale $sale)
    {
        $this->authorizeSale($sale);

        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('delete_sales')) {
            abort(403);
        }

        $sale->delete();

        return redirect()->route('sales.index')
            ->with('success', __('sales.Sale deleted successfully'));
    }

    public function toggleActive(Sale $sale)
    {
        $this->authorizeSale($sale);

        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('edit_sales')) {
            abort(403);
        }

        $sale->update(['is_active' => ! $sale->is_active]);

        return response()->json(['is_active' => $sale->is_active]);
    }

    public function extendDate(Request $request, Sale $sale)
    {
        $this->authorizeSale($sale);

        $user = auth()->user();
        if ($user->role === 'employee' && ! $user->hasPermission('edit_sales')) {
            abort(403);
        }

        $data = $request->validate([
            'end_date' => 'required|date|after_or_equal:today',
        ]);

        $sale->update(['end_date' => $data['end_date']]);

        return response()->json([
            'success'  => true,
            'end_date' => $sale->end_date?->format('Y-m-d'),
        ]);
    }

    /**
     * Return all currently-active sales (with rules + product prices)
     * for the authenticated user — used by the dashboard JS loader.
     */
    public function activeSales()
    {
        $ownerId = $this->ownerId();
        $today   = now()->toDateString();

        $sales = Sale::where('user_id', $ownerId)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $today))
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->with(['rules:id,sale_id,product_id,discount_type,discount_value,applies_every_n'])
            ->get(['id', 'name']);

        return response()->json($sales);
    }

    private function authorizeSale(Sale $sale): void
    {
        if ($sale->user_id !== $this->ownerId()) {
            abort(403);
        }
    }
}
