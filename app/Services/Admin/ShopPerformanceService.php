<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales and profit figures for the admin panel.
 *
 * Definitions match the shop's own financial dashboard: sales exclude damaged and returned bills,
 * gross profit = (selling - cost) x quantity - line discount over non-damaged bills (returns reduce it).
 */
class ShopPerformanceService
{
    private const PROFIT_SQL = '((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity - COALESCE(bill_product.discount, 0))';

    public function __construct(private readonly FinanceInsightsService $insights) {}

    /**
     * Today / this month figures for every shop that sold this month, keyed by owner id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function monthSummaries(?Carbon $now = null, ?array $ownerIds = null): Collection
    {
        if ($ownerIds !== null && $ownerIds === []) {
            return collect();
        }

        $now ??= now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $sales = DB::table('bills')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->when($ownerIds !== null, fn ($query) => $query->whereIn('user_id', $ownerIds))
            ->groupBy('user_id')
            ->selectRaw('user_id,
                SUM(CASE WHEN is_damaged = 0 AND is_returned = 0 AND created_at >= ? AND created_at <= ? THEN total_price ELSE 0 END) as sales_today,
                SUM(CASE WHEN is_damaged = 0 AND is_returned = 0 THEN total_price ELSE 0 END) as sales_month,
                SUM(CASE WHEN is_damaged = 0 AND is_returned = 0 AND created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as bills_today,
                SUM(CASE WHEN is_damaged = 0 AND is_returned = 0 THEN 1 ELSE 0 END) as bills_month,
                SUM(CASE WHEN is_returned = 1 THEN ABS(total_price) ELSE 0 END) as returns_month,
                MAX(created_at) as last_bill_at', [$todayStart, $todayEnd, $todayStart, $todayEnd])
            ->get()
            ->keyBy('user_id');

        $profits = DB::table('bill_product')
            ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
            ->whereBetween('bills.created_at', [$monthStart, $monthEnd])
            ->when($ownerIds !== null, fn ($query) => $query->whereIn('bills.user_id', $ownerIds))
            ->where('bills.is_damaged', false)
            ->groupBy('bills.user_id')
            ->selectRaw('bills.user_id,
                SUM(CASE WHEN bills.created_at >= ? AND bills.created_at <= ? THEN '.self::PROFIT_SQL.' ELSE 0 END) as profit_today,
                SUM('.self::PROFIT_SQL.') as profit_month,
                SUM(CASE WHEN bills.is_returned = 0 THEN bill_product.quantity ELSE 0 END) as units_month', [$todayStart, $todayEnd])
            ->get()
            ->keyBy('user_id');

        return $sales->keys()->merge($profits->keys())->unique()->mapWithKeys(function ($ownerId) use ($sales, $profits) {
            $sale = $sales->get($ownerId);
            $profit = $profits->get($ownerId);
            $salesMonth = round((float) ($sale->sales_month ?? 0), 2);
            $profitMonth = round((float) ($profit->profit_month ?? 0), 2);
            $billsMonth = (int) ($sale->bills_month ?? 0);

            return [(int) $ownerId => [
                'sales_today' => round((float) ($sale->sales_today ?? 0), 2),
                'sales_month' => $salesMonth,
                'profit_today' => round((float) ($profit->profit_today ?? 0), 2),
                'profit_month' => $profitMonth,
                'bills_today' => (int) ($sale->bills_today ?? 0),
                'bills_month' => $billsMonth,
                'returns_month' => round((float) ($sale->returns_month ?? 0), 2),
                'units_month' => round((float) ($profit->units_month ?? 0), 2),
                'avg_bill' => $billsMonth > 0 ? round($salesMonth / $billsMonth, 2) : 0.0,
                'margin' => $salesMonth > 0 ? round($profitMonth / $salesMonth * 100, 1) : null,
                'last_bill_at' => $sale->last_bill_at ?? null,
            ]];
        });
    }

    public static function emptySummary(): array
    {
        return [
            'sales_today' => 0.0, 'sales_month' => 0.0, 'profit_today' => 0.0, 'profit_month' => 0.0,
            'bills_today' => 0, 'bills_month' => 0, 'returns_month' => 0.0, 'units_month' => 0.0,
            'avg_bill' => 0.0, 'margin' => null, 'last_bill_at' => null,
        ];
    }

    /**
     * Daily sales and gross profit for the whole platform (server dates) or one shop (shop-local dates).
     *
     * @return array{labels: array<int, string>, sales: array<int, float>, profit: array<int, float>, bills: array<int, int>}
     */
    public function dailySeries(int $days = 30, ?int $ownerId = null): array
    {
        $days = max(1, min(366, $days));

        if ($ownerId === null) {
            $start = now()->subDays($days - 1)->startOfDay();
            $sales = DB::table('bills')
                ->where('created_at', '>=', $start)
                ->where('is_damaged', false)
                ->where('is_returned', false)
                ->groupByRaw('DATE(created_at)')
                ->selectRaw('DATE(created_at) as day, SUM(total_price) as total, COUNT(*) as bills')
                ->get()->keyBy('day');
            $profit = DB::table('bill_product')
                ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
                ->where('bills.created_at', '>=', $start)
                ->where('bills.is_damaged', false)
                ->groupByRaw('DATE(bills.created_at)')
                ->selectRaw('DATE(bills.created_at) as day, SUM('.self::PROFIT_SQL.') as total')
                ->get()->keyBy('day');
            $dates = collect(range($days - 1, 0))->map(fn ($offset) => now()->subDays($offset)->toDateString());

            return [
                'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format('m/d'))->all(),
                'sales' => $dates->map(fn ($date) => round((float) ($sales[$date]->total ?? 0), 2))->all(),
                'profit' => $dates->map(fn ($date) => round((float) ($profit[$date]->total ?? 0), 2))->all(),
                'bills' => $dates->map(fn ($date) => (int) ($sales[$date]->bills ?? 0))->all(),
            ];
        }

        $today = ShopTime::today($ownerId);
        $from = Carbon::parse($today)->subDays($days - 1)->toDateString();
        [$startUtc, $endUtc] = ShopTime::utcRange($from, $today, $ownerId);
        $bills = DB::table('bills')
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('is_damaged', false)
            ->get(['id', 'created_at', 'total_price', 'is_returned']);
        $lineProfit = DB::table('bill_product')
            ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->groupBy('bill_product.bill_id')
            ->selectRaw('bill_product.bill_id, SUM('.self::PROFIT_SQL.') as profit')
            ->pluck('profit', 'bill_id');
        $byDay = $bills->groupBy(fn ($bill) => ShopTime::localDate($bill->created_at.' UTC', $ownerId));
        $dates = collect(range($days - 1, 0))->map(fn ($offset) => Carbon::parse($today)->subDays($offset)->toDateString());

        return [
            'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format('m/d'))->all(),
            'sales' => $dates->map(fn ($date) => round((float) $byDay->get($date, collect())->where('is_returned', false)->sum('total_price'), 2))->all(),
            'profit' => $dates->map(fn ($date) => round((float) $byDay->get($date, collect())->sum(fn ($bill) => (float) ($lineProfit[$bill->id] ?? 0)), 2))->all(),
            'bills' => $dates->map(fn ($date) => $byDay->get($date, collect())->where('is_returned', false)->count())->all(),
        ];
    }

    /**
     * Detailed performance of one shop using the shop's own timezone and P&L definitions.
     */
    public function forShop(User $shop): array
    {
        $ownerId = (int) $shop->id;
        $today = ShopTime::today($ownerId);
        $monthStart = Carbon::parse($today)->startOfMonth()->toDateString();

        $todayPnl = $this->insights->profitLoss($ownerId, $today, $today);
        $monthPnl = $this->insights->profitLoss($ownerId, $monthStart, $today);

        $lifetime = DB::table('bills')
            ->where('user_id', $ownerId)
            ->where('is_damaged', false)
            ->where('is_returned', false)
            ->selectRaw('COALESCE(SUM(total_price), 0) as sales, COUNT(*) as bills, MIN(created_at) as first_bill_at, MAX(created_at) as last_bill_at')
            ->first();
        $lifetimeProfit = (float) DB::table('bill_product')
            ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->where('bills.is_damaged', false)
            ->selectRaw('COALESCE(SUM('.self::PROFIT_SQL.'), 0) as total')
            ->value('total');

        [$monthStartUtc, $monthEndUtc] = ShopTime::utcRange($monthStart, $today, $ownerId);
        $monthBills = (int) DB::table('bills')
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$monthStartUtc, $monthEndUtc])
            ->where('is_damaged', false)
            ->where('is_returned', false)
            ->count();
        $topProducts = DB::table('bill_product')
            ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
            ->join('products', 'products.id', '=', 'bill_product.product_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$monthStartUtc, $monthEndUtc])
            ->where('bills.is_damaged', false)
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name, SUM(bill_product.quantity) as quantity, SUM('.self::PROFIT_SQL.') as profit,
                SUM(bill_product.selling_price * bill_product.quantity - COALESCE(bill_product.discount, 0)) as revenue')
            ->orderByDesc('profit')
            ->limit(8)
            ->get();

        return [
            'today' => $todayPnl,
            'month' => $monthPnl,
            'month_bills' => $monthBills,
            'month_avg_bill' => $monthBills > 0 ? round($monthPnl['revenue'] / $monthBills, 2) : 0.0,
            'month_growth' => [
                'revenue' => $this->growth($monthPnl['revenue'], $monthPnl['compare']['revenue']),
                'gross_profit' => $this->growth($monthPnl['gross_profit'], $monthPnl['compare']['gross_profit']),
                'net_profit' => $this->growth($monthPnl['net_profit'], $monthPnl['compare']['net_profit']),
            ],
            'lifetime' => [
                'sales' => round((float) $lifetime->sales, 2),
                'profit' => round($lifetimeProfit, 2),
                'bills' => (int) $lifetime->bills,
                'first_bill_at' => $lifetime->first_bill_at,
                'last_bill_at' => $lifetime->last_bill_at,
            ],
            'top_products' => $topProducts,
            'series' => $this->dailySeries(30, $ownerId),
        ];
    }

    public function growth(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.00001) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
