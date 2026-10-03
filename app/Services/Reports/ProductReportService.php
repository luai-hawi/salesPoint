<?php

namespace App\Services\Reports;

use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product-level reports (sales, profit, stock, purchases, damage and returns).
 *
 * Figures follow the financial dashboard: damaged bills are excluded from sales, return lines carry a
 * negative quantity, and profit = (selling - cost) x quantity - line discount.
 */
class ProductReportService
{
    public const TYPES = [
        'product_sales', 'top_selling_products', 'most_profitable_products', 'least_selling_products',
        'unsold_products', 'product_movement', 'category_sales', 'low_stock_products',
        'product_stock_valuation', 'product_purchases', 'damaged_products', 'returned_products',
    ];

    private const ROW_LIMIT = 5000;

    public function rows(string $type, int $ownerId, Request $request, string $from, string $to): Collection
    {
        return match ($type) {
            'product_sales' => $this->salesSummary($ownerId, $request, $from, $to)->sortByDesc('net_qty')->values(),
            'top_selling_products' => $this->salesSummary($ownerId, $request, $from, $to)
                ->filter(fn ($row) => $row['net_qty'] > 0)->sortByDesc('net_qty')->take(50)->values(),
            'most_profitable_products' => $this->salesSummary($ownerId, $request, $from, $to)
                ->sortByDesc('profit')->take(50)->values(),
            'least_selling_products' => $this->leastSelling($ownerId, $request, $from, $to),
            'unsold_products' => $this->unsold($ownerId, $request, $from, $to),
            'product_movement' => $this->movement($ownerId, $request, $from, $to),
            'category_sales' => $this->categorySales($ownerId, $request, $from, $to),
            'low_stock_products' => $this->lowStock($ownerId, $request),
            'product_stock_valuation' => $this->stockValuation($ownerId, $request),
            'product_purchases' => $this->purchases($ownerId, $request, $from, $to),
            'damaged_products' => $this->damaged($ownerId, $request, $from, $to),
            'returned_products' => $this->returned($ownerId, $request, $from, $to),
            default => collect(),
        };
    }

    /**
     * Categories used by the shop, for the report filter.
     *
     * @return array<int, string>
     */
    public function categories(int $ownerId): array
    {
        return DB::table('products')
            ->where('user_id', $ownerId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->limit(500)
            ->pluck('category')
            ->all();
    }

    private function applyProductFilters(Builder $query, Request $request, string $alias = 'p'): Builder
    {
        $search = trim((string) $request->input('product_search', ''));
        $category = trim((string) $request->input('category', ''));
        $productId = (int) $request->integer('product_id');

        return $query
            ->when($productId > 0, fn ($q) => $q->where($alias.'.id', $productId))
            ->when($search !== '', function ($q) use ($search, $alias) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($inner) => $inner->where($alias.'.name', 'like', $like)->orWhere($alias.'.barcode', 'like', $like));
            })
            ->when($category !== '', fn ($q) => $category === '__none'
                ? $q->where(fn ($inner) => $inner->whereNull($alias.'.category')->orWhere($alias.'.category', ''))
                : $q->where($alias.'.category', $category));
    }

    private function salesLines(int $ownerId, Request $request, string $from, string $to): Builder
    {
        [$start, $end] = ShopTime::utcRange($from, $to, $ownerId);

        return $this->applyProductFilters(
            DB::table('bill_product as bp')
                ->join('bills as b', 'b.id', '=', 'bp.bill_id')
                ->join('products as p', 'p.id', '=', 'bp.product_id')
                ->where('b.user_id', $ownerId)
                ->where('p.user_id', $ownerId)
                ->whereBetween('b.created_at', [$start, $end])
                ->where('b.is_damaged', false),
            $request,
        );
    }

    private function salesSummary(int $ownerId, Request $request, string $from, string $to): Collection
    {
        return $this->salesLines($ownerId, $request, $from, $to)
            ->groupBy('p.id', 'p.barcode', 'p.name', 'p.category', 'p.quantity')
            ->selectRaw('p.id, p.barcode, p.name, p.category, p.quantity as stock,
                SUM(CASE WHEN bp.quantity > 0 THEN bp.quantity ELSE 0 END) as qty_sold,
                SUM(CASE WHEN bp.quantity < 0 THEN -bp.quantity ELSE 0 END) as qty_returned,
                SUM(bp.quantity) as net_qty,
                SUM(CASE WHEN bp.quantity > 0 THEN bp.selling_price * bp.quantity ELSE 0 END) as gross_sales,
                SUM(CASE WHEN bp.quantity < 0 THEN -(bp.selling_price * bp.quantity - COALESCE(bp.discount, 0)) ELSE 0 END) as returned_value,
                SUM(CASE WHEN bp.quantity > 0 THEN COALESCE(bp.discount, 0) ELSE 0 END) as discounts,
                SUM(bp.cost_price * bp.quantity) as cogs,
                SUM((bp.selling_price - bp.cost_price) * bp.quantity - COALESCE(bp.discount, 0)) as profit,
                COUNT(DISTINCT b.id) as bills_count,
                MAX(b.created_at) as last_sold_at')
            ->limit(self::ROW_LIMIT)
            ->get()
            ->map(function ($row) use ($ownerId) {
                $netSales = (float) $row->gross_sales - (float) $row->discounts - (float) $row->returned_value;
                $profit = (float) $row->profit;
                $qtySold = (float) $row->qty_sold;

                return [
                    'id' => (int) $row->id,
                    'barcode' => $row->barcode,
                    'name' => $row->name,
                    'category' => $row->category,
                    'stock' => (float) $row->stock,
                    'qty_sold' => $qtySold,
                    'qty_returned' => (float) $row->qty_returned,
                    'net_qty' => (float) $row->net_qty,
                    'gross_sales' => round((float) $row->gross_sales, 2),
                    'returned_value' => round((float) $row->returned_value, 2),
                    'discounts' => round((float) $row->discounts, 2),
                    'revenue' => round($netSales, 2),
                    'avg_price' => $qtySold > 0 ? round((float) $row->gross_sales / $qtySold, 2) : 0.0,
                    'cogs' => round((float) $row->cogs, 2),
                    'profit' => round($profit, 2),
                    'margin' => abs($netSales) > 0.00001 ? round($profit / $netSales * 100, 1) : null,
                    'bills_count' => (int) $row->bills_count,
                    'last_sold_at' => $this->localTime($row->last_sold_at, $ownerId),
                ];
            });
    }

    private function leastSelling(int $ownerId, Request $request, string $from, string $to): Collection
    {
        $sales = $this->salesSummary($ownerId, $request, $from, $to)->keyBy('id');

        return $this->applyProductFilters(DB::table('products as p')->where('p.user_id', $ownerId)->where('p.is_active', true), $request)
            ->orderBy('p.name')
            ->limit(self::ROW_LIMIT)
            ->get(['p.id', 'p.barcode', 'p.name', 'p.category', 'p.quantity', 'p.last_sale_date'])
            ->map(function ($product) use ($sales, $ownerId) {
                $sale = $sales->get((int) $product->id);

                return [
                    'id' => (int) $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category,
                    'stock' => (float) $product->quantity,
                    'net_qty' => (float) ($sale['net_qty'] ?? 0),
                    'revenue' => (float) ($sale['revenue'] ?? 0),
                    'profit' => (float) ($sale['profit'] ?? 0),
                    'last_sold_at' => $sale['last_sold_at'] ?? $this->localTime($product->last_sale_date, $ownerId),
                ];
            })
            ->sortBy([['net_qty', 'asc'], ['stock', 'desc']])
            ->take(100)
            ->values();
    }

    private function unsold(int $ownerId, Request $request, string $from, string $to): Collection
    {
        $soldInPeriod = $this->salesLines($ownerId, $request, $from, $to)
            ->where('bp.quantity', '>', 0)
            ->select('bp.product_id');
        $lastSales = DB::table('bill_product as bp')
            ->join('bills as b', 'b.id', '=', 'bp.bill_id')
            ->where('b.user_id', $ownerId)
            ->where('b.is_damaged', false)
            ->where('bp.quantity', '>', 0)
            ->groupBy('bp.product_id')
            ->selectRaw('bp.product_id, MAX(b.created_at) as last_sold_at')
            ->pluck('last_sold_at', 'product_id');
        $today = Carbon::parse(ShopTime::today($ownerId));

        return $this->applyProductFilters(DB::table('products as p')->where('p.user_id', $ownerId)->where('p.is_active', true), $request)
            ->whereNotIn('p.id', $soldInPeriod)
            ->orderByDesc(DB::raw('p.quantity * p.cost_price'))
            ->limit(self::ROW_LIMIT)
            ->get(['p.id', 'p.barcode', 'p.name', 'p.category', 'p.quantity', 'p.cost_price', 'p.selling_price'])
            ->map(function ($product) use ($lastSales, $ownerId, $today) {
                $last = $this->localTime($lastSales[$product->id] ?? null, $ownerId);

                return [
                    'id' => (int) $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category,
                    'stock' => (float) $product->quantity,
                    'cost_price' => (float) $product->cost_price,
                    'selling_price' => (float) $product->selling_price,
                    'stock_cost' => round(max(0, (float) $product->quantity) * (float) $product->cost_price, 2),
                    'last_sold_at' => $last,
                    'days_without_sale' => $last ? (int) Carbon::parse(substr($last, 0, 10))->diffInDays($today) : null,
                ];
            })
            ->values();
    }

    private function movement(int $ownerId, Request $request, string $from, string $to): Collection
    {
        [$start, $end] = ShopTime::utcRange($from, $to, $ownerId);

        return $this->applyProductFilters(
            DB::table('bill_product as bp')
                ->join('bills as b', 'b.id', '=', 'bp.bill_id')
                ->join('products as p', 'p.id', '=', 'bp.product_id')
                ->leftJoin('customers as c', 'c.id', '=', 'b.customer_id')
                ->leftJoin('users as u', 'u.id', '=', 'b.created_by')
                ->where('b.user_id', $ownerId)
                ->where('p.user_id', $ownerId)
                ->whereBetween('b.created_at', [$start, $end]),
            $request,
        )
            ->orderByDesc('b.created_at')
            ->orderByDesc('b.id')
            ->limit(self::ROW_LIMIT)
            ->get(['b.id as bill_id', 'b.created_at', 'b.is_damaged', 'b.is_returned', 'p.barcode', 'p.name', 'c.name as customer_name',
                'u.name as creator_name', 'bp.quantity', 'bp.selling_price', 'bp.cost_price', 'bp.discount'])
            ->map(function ($line) use ($ownerId) {
                $quantity = (float) $line->quantity;
                $discount = (float) ($line->discount ?? 0);
                $damaged = (bool) $line->is_damaged;
                $movement = $damaged ? 'damaged' : (($line->is_returned || $quantity < 0) ? 'return' : 'sale');

                return [
                    'created_at' => $this->localTime($line->created_at, $ownerId),
                    'bill_id' => (int) $line->bill_id,
                    'movement' => __('charts.products.movement_types.'.$movement),
                    'barcode' => $line->barcode,
                    'name' => $line->name,
                    'customer_name' => $line->customer_name,
                    'creator_name' => $line->creator_name,
                    'quantity' => $quantity,
                    'selling_price' => (float) $line->selling_price,
                    'discount' => round($discount, 2),
                    'line_total' => $damaged ? 0.0 : round((float) $line->selling_price * $quantity - $discount, 2),
                    'profit' => $damaged
                        ? round(-1 * (float) $line->cost_price * abs($quantity), 2)
                        : round(((float) $line->selling_price - (float) $line->cost_price) * $quantity - $discount, 2),
                ];
            });
    }

    private function categorySales(int $ownerId, Request $request, string $from, string $to): Collection
    {
        $rows = $this->salesSummary($ownerId, $request, $from, $to)
            ->groupBy(fn ($row) => trim((string) $row['category']))
            ->map(function ($rows, $category) {
                $revenue = (float) $rows->sum('revenue');
                $profit = (float) $rows->sum('profit');

                return [
                    'category' => $category === '' ? __('charts.products.uncategorized') : $category,
                    'products_count' => $rows->count(),
                    'qty_sold' => (float) $rows->sum('qty_sold'),
                    'qty_returned' => (float) $rows->sum('qty_returned'),
                    'net_qty' => (float) $rows->sum('net_qty'),
                    'revenue' => round($revenue, 2),
                    'cogs' => round((float) $rows->sum('cogs'), 2),
                    'profit' => round($profit, 2),
                    'margin' => abs($revenue) > 0.00001 ? round($profit / $revenue * 100, 1) : null,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
        $total = (float) $rows->sum('revenue');

        return $rows->map(fn ($row) => $row + ['share' => $total > 0 ? round($row['revenue'] / $total * 100, 1) : null]);
    }

    private function lowStock(int $ownerId, Request $request): Collection
    {
        $velocity = DB::table('bill_product as bp')
            ->join('bills as b', 'b.id', '=', 'bp.bill_id')
            ->where('b.user_id', $ownerId)
            ->where('b.is_damaged', false)
            ->where('b.created_at', '>=', now()->subDays(30))
            ->groupBy('bp.product_id')
            ->selectRaw('bp.product_id, SUM(bp.quantity) as qty')
            ->pluck('qty', 'product_id');

        return $this->applyProductFilters(DB::table('products as p')->where('p.user_id', $ownerId)->where('p.is_active', true), $request)
            ->where(fn ($q) => $q->where('p.quantity', '<=', 0)
                ->orWhere(fn ($inner) => $inner->whereNotNull('p.low_stock_threshold')->whereColumn('p.quantity', '<=', 'p.low_stock_threshold')))
            ->orderBy('p.quantity')
            ->limit(self::ROW_LIMIT)
            ->get(['p.id', 'p.barcode', 'p.name', 'p.category', 'p.quantity', 'p.low_stock_threshold', 'p.cost_price'])
            ->map(function ($product) use ($velocity) {
                $stock = (float) $product->quantity;
                $daily = max(0, (float) ($velocity[$product->id] ?? 0)) / 30;
                $threshold = $product->low_stock_threshold === null ? null : (float) $product->low_stock_threshold;
                $target = max($threshold ?? 0, ceil($daily * 30));

                return [
                    'id' => (int) $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category,
                    'stock' => $stock,
                    'low_stock_threshold' => $threshold,
                    'stock_status' => __('charts.products.stock_status.'.($stock <= 0 ? 'out' : 'low')),
                    'avg_daily_sales' => round($daily, 2),
                    'days_of_stock' => $daily > 0 ? round(max(0, $stock) / $daily, 1) : null,
                    'suggested_order' => max(0, $target - max(0, $stock)),
                    'cost_price' => (float) $product->cost_price,
                ];
            })
            ->values();
    }

    private function stockValuation(int $ownerId, Request $request): Collection
    {
        return $this->applyProductFilters(DB::table('products as p')->where('p.user_id', $ownerId), $request)
            ->where('p.quantity', '>', 0)
            ->orderByDesc(DB::raw('p.quantity * p.cost_price'))
            ->limit(self::ROW_LIMIT)
            ->get(['p.id', 'p.barcode', 'p.name', 'p.category', 'p.quantity', 'p.cost_price', 'p.selling_price'])
            ->map(function ($product) {
                $quantity = (float) $product->quantity;
                $cost = round($quantity * (float) $product->cost_price, 2);
                $value = round($quantity * (float) $product->selling_price, 2);

                return [
                    'id' => (int) $product->id,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category,
                    'stock' => $quantity,
                    'cost_price' => (float) $product->cost_price,
                    'selling_price' => (float) $product->selling_price,
                    'stock_cost' => $cost,
                    'stock_value' => $value,
                    'potential_profit' => round($value - $cost, 2),
                    'margin' => $value > 0 ? round(($value - $cost) / $value * 100, 1) : null,
                ];
            });
    }

    private function purchases(int $ownerId, Request $request, string $from, string $to): Collection
    {
        $supplierId = (int) $request->integer('supplier_id');
        $lines = $this->applyProductFilters(
            DB::table('purchase_bill_product as pbp')
                ->join('purchase_bills as pb', 'pb.id', '=', 'pbp.purchase_bill_id')
                ->join('products as p', 'p.id', '=', 'pbp.product_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'pb.supplier_id')
                ->where('pb.user_id', $ownerId)
                ->where('p.user_id', $ownerId)
                ->whereDate('pb.purchase_date', '>=', $from)
                ->whereDate('pb.purchase_date', '<=', $to)
                ->when($supplierId > 0, fn ($q) => $q->where('pb.supplier_id', $supplierId)),
            $request,
        )
            ->limit(20000)
            ->get(['p.id', 'p.barcode', 'p.name', 'p.category', 'p.quantity as stock', 'pb.id as purchase_bill_id', 'pb.purchase_date',
                's.name as supplier_name', 'pbp.quantity', 'pbp.unit_cost', 'pbp.total_cost']);

        return $lines->groupBy('id')->map(function ($rows) {
            $first = $rows->first();
            $quantity = (float) $rows->sum('quantity');
            $total = (float) $rows->sum(fn ($row) => $row->total_cost !== null ? (float) $row->total_cost : (float) $row->quantity * (float) $row->unit_cost);

            return [
                'id' => (int) $first->id,
                'barcode' => $first->barcode,
                'name' => $first->name,
                'category' => $first->category,
                'qty_purchased' => $quantity,
                'purchase_cost' => round($total, 2),
                'avg_cost' => $quantity > 0 ? round($total / $quantity, 2) : 0.0,
                'purchase_bills_count' => $rows->pluck('purchase_bill_id')->unique()->count(),
                'suppliers' => $rows->pluck('supplier_name')->filter()->unique()->take(5)->implode(', '),
                'last_purchase_date' => substr((string) $rows->max('purchase_date'), 0, 10),
                'stock' => (float) $first->stock,
            ];
        })->sortByDesc('purchase_cost')->take(self::ROW_LIMIT)->values();
    }

    private function damaged(int $ownerId, Request $request, string $from, string $to): Collection
    {
        [$start, $end] = ShopTime::utcRange($from, $to, $ownerId);

        return $this->applyProductFilters(
            DB::table('bill_product as bp')
                ->join('bills as b', 'b.id', '=', 'bp.bill_id')
                ->join('products as p', 'p.id', '=', 'bp.product_id')
                ->where('b.user_id', $ownerId)
                ->where('p.user_id', $ownerId)
                ->whereBetween('b.created_at', [$start, $end])
                ->where('b.is_damaged', true),
            $request,
        )
            ->groupBy('p.id', 'p.barcode', 'p.name', 'p.category')
            ->selectRaw('p.id, p.barcode, p.name, p.category, SUM(ABS(bp.quantity)) as qty_damaged,
                SUM(bp.cost_price * ABS(bp.quantity)) as damaged_loss, COUNT(DISTINCT b.id) as bills_count, MAX(b.created_at) as last_at')
            ->orderByDesc('damaged_loss')
            ->limit(self::ROW_LIMIT)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'barcode' => $row->barcode,
                'name' => $row->name,
                'category' => $row->category,
                'qty_damaged' => (float) $row->qty_damaged,
                'damaged_loss' => round((float) $row->damaged_loss, 2),
                'bills_count' => (int) $row->bills_count,
                'last_damaged_at' => $this->localTime($row->last_at, $ownerId),
            ]);
    }

    private function returned(int $ownerId, Request $request, string $from, string $to): Collection
    {
        return $this->salesSummary($ownerId, $request, $from, $to)
            ->filter(fn ($row) => $row['qty_returned'] > 0)
            ->map(fn ($row) => [
                'id' => $row['id'],
                'barcode' => $row['barcode'],
                'name' => $row['name'],
                'category' => $row['category'],
                'qty_sold' => $row['qty_sold'],
                'qty_returned' => $row['qty_returned'],
                'return_rate' => $row['qty_sold'] > 0 ? round($row['qty_returned'] / $row['qty_sold'] * 100, 1) : null,
                'returned_value' => $row['returned_value'],
            ])
            ->sortByDesc('returned_value')
            ->values();
    }

    private function localTime(mixed $value, int $ownerId): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;

        return ShopTime::local($string.' UTC', $ownerId)->format('Y-m-d H:i');
    }
}
