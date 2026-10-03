<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\CapitalEntry;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinanceInsightsService
{
    public function __construct(private readonly CashFlowService $cashFlow) {}

    public function dashboardSummary(int $ownerId, string $fromDate, string $toDate): array
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($fromDate, $toDate, $ownerId);
        $profitLoss = $this->profitLossRange($ownerId, $fromDate, $toDate, $startUtc, $endUtc);
        $staffNet = round((float) EmployeePayment::query()
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->whereIn('employee_id', Employee::query()->where('shop_owner_id', $ownerId)->pluck('id'))
            ->sum('amount'), 2);

        $sales = Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('is_damaged', false)
            ->where('is_returned', false);

        $revenue = round((float) $sales->sum('total_price'), 2);

        $profit = $profitLoss['gross_profit'];

        $damagedLoss = round((float) (DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', true)
            ->selectRaw('COALESCE(SUM(bill_product.cost_price * ABS(bill_product.quantity)), 0) as loss')
            ->value('loss')), 2);

        $expenses = round((float) Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->sum('amount'), 2);

        return [
            'revenue' => $revenue,
            'profit' => $profit,
            'discounts' => $profitLoss['discounts'],
            'expenses' => $expenses,
            'staff_payments' => $staffNet,
            'staff_refunds' => $profitLoss['staff_refunds'],
            'damaged_loss' => $damagedLoss,
            'net_income' => round($profit - $damagedLoss - $expenses - $staffNet, 2),
            'cash_flow' => $this->cashFlow->moneyFlowSummary($ownerId, $fromDate, $toDate),
            'team_today' => $this->teamSummary($ownerId, ShopTime::today($ownerId), ShopTime::today($ownerId)),
            'data_health' => $this->dataHealth($ownerId),
        ];
    }

    public function dayCloseSummary(int $ownerId, string $date): array
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($date, $date, $ownerId);

        $billQuery = Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc]);

        $bills = (clone $billQuery)->get(['id', 'total_price', 'created_by', 'is_damaged', 'is_returned', 'payment_method']);

        $discounts = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->selectRaw('COALESCE(SUM(bill_product.discount), 0) as total')
            ->value('total');

        $damagedLoss = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', true)
            ->selectRaw('COALESCE(SUM(bill_product.cost_price * ABS(bill_product.quantity)), 0) as total')
            ->value('total');

        $creditSales = (float) CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('amount', '<', 0)
            ->where(function ($query) {
                $query->where('kind', 'bill_charge')
                    ->orWhere(function ($inner) {
                        $inner->whereNull('kind')->where('note', 'like', 'Bill #% created as debt');
                    });
            })
            ->sum(DB::raw('ABS(amount)'));

        $cashSummary = $this->cashFlow->dayCloseCashSummary($ownerId, $date);
        $categories = collect($cashSummary['cash']['rows'])->groupBy('category');

        return [
            'date' => $date,
            'sales_count' => $bills->where('is_damaged', false)->where('is_returned', false)->count(),
            'sales_total' => round((float) $bills->where('is_damaged', false)->where('is_returned', false)->sum('total_price'), 2),
            'returns_total' => round((float) $bills->where('is_returned', true)->sum(fn ($bill) => abs((float) $bill->total_price)), 2),
            'returns_count' => $bills->where('is_returned', true)->count(),
            'damaged_loss' => round($damagedLoss, 2),
            'discounts' => round($discounts, 2),
            'credit_sales' => round($creditSales, 2),
            'received_by_method' => $cashSummary['received_by_method'],
            'supplier_payments' => round((float) $categories->get('supplier_payment', collect())->sum('amount_out'), 2),
            'expenses' => round((float) $categories->get('expense', collect())->sum('amount_out'), 2),
            'staff_payments' => round((float) $categories->get('employee_payment', collect())->sum('amount_out'), 2),
            'staff_refunds' => round((float) $categories->get('staff_refund', collect())->sum('amount_in'), 2),
            'customer_refunds' => round((float) $categories->get('customer_refund', collect())->sum('amount_out'), 2),
            'manual_in' => round((float) $categories->get('manual_in', collect())->sum('amount_in'), 2),
            'manual_out' => round((float) $categories->get('manual_out', collect())->sum('amount_out'), 2),
            'expected_cash' => $cashSummary['cash']['closing_balance'],
            'cash_drawer' => $cashSummary['cash'],
            'all_methods' => $cashSummary['all'],
        ];
    }

    public function dashboardDetails(int $ownerId, string $from, string $to): array
    {
        [$start, $end] = ShopTime::utcRange($from, $to, $ownerId);
        $bills = Bill::withoutGlobalScopes()->where('user_id', $ownerId)
            ->whereBetween('created_at', [$start, $end]);
        $lines = DB::table('bills')->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)->whereBetween('bills.created_at', [$start, $end]);
        $lineTotals = (clone $lines)->selectRaw('bills.id,
            SUM((selling_price - cost_price) * quantity - COALESCE(discount, 0)) as profit,
            SUM(cost_price * ABS(quantity)) as loss, SUM(ABS(quantity)) as units')
            ->groupBy('bills.id')->get()->keyBy('id');

        $daily = (clone $bills)->get(['id', 'created_at', 'total_price', 'is_damaged', 'is_returned'])
            ->groupBy(fn ($bill) => ShopTime::localDate($bill->created_at, $ownerId))
            ->map(function ($rows, $date) use ($lineTotals) {
                return [
                    'date' => $date,
                    'revenue' => (float) $rows->where('is_damaged', false)->where('is_returned', false)->sum('total_price'),
                    'profit' => (float) $rows->where('is_damaged', false)->sum(fn ($bill) => $lineTotals->get($bill->id)?->profit ?? 0),
                    'returns' => (float) $rows->where('is_returned', true)->sum(fn ($bill) => abs($bill->total_price)),
                    'damaged' => (float) $rows->where('is_damaged', true)->sum(fn ($bill) => $lineTotals->get($bill->id)?->loss ?? 0),
                ];
            })->sortKeys();

        $purchases = PurchaseBill::withoutGlobalScopes()->where('user_id', $ownerId)
            ->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to);
        $purchaseDaily = (clone $purchases)->selectRaw('DATE(purchase_date) as date, SUM(total_amount) as purchases')
            ->groupByRaw('DATE(purchase_date)')->get()->keyBy('date');
        $settlement = $this->cashFlow->cashDrawerData($ownerId, $from, $to, 'all');
        $flows = collect($settlement['rows']);
        $flowDaily = $flows->groupBy(fn ($row) => ShopTime::localDate($row['occurred_at'], $ownerId));
        $dates = $daily->keys()->merge($purchaseDaily->keys())->merge($flowDaily->keys())->unique()->sort();
        $trends = $dates->map(fn ($date) => array_merge(
            ['date' => $date, 'revenue' => 0, 'profit' => 0, 'returns' => 0, 'damaged' => 0],
            $daily->get($date, []),
            ['purchases' => (float) ($purchaseDaily->get($date)?->purchases ?? 0),
                'money_in' => (float) $flowDaily->get($date, collect())->sum('amount_in'),
                'money_out' => (float) $flowDaily->get($date, collect())->sum('amount_out')]
        ))->values();

        $inventory = Product::withoutGlobalScopes()->where('user_id', $ownerId)->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as cost,
                COALESCE(SUM(quantity * selling_price), 0) as selling,
                COALESCE(SUM(quantity), 0) as units, COUNT(*) as products')->first();
        $capital = CapitalEntry::withoutGlobalScopes()->where('user_id', $ownerId)->orderByDesc('entry_date')->get();
        $pnl = $this->profitLoss($ownerId, $from, $to);
        $previousPurchases = PurchaseBill::withoutGlobalScopes()->where('user_id', $ownerId)
            ->whereDate('purchase_date', '>=', $pnl['compare']['from_date'])
            ->whereDate('purchase_date', '<=', $pnl['compare']['to_date'])->sum('total_amount');
        $growth = collect([
            'revenue' => [$pnl['revenue'], $pnl['compare']['revenue']],
            'profit' => [$pnl['gross_profit'], $pnl['compare']['gross_profit']],
            'expenses' => [$pnl['expenses_total'], $pnl['compare']['expenses_total']],
            'purchases' => [(float) (clone $purchases)->sum('total_amount'), (float) $previousPurchases],
        ])->map(fn ($values, $key) => [
            'metric' => __('finance.restored.'.$key), 'current' => $values[0], 'previous' => $values[1],
            'growth' => $values[1] == 0 ? null : round(($values[0] - $values[1]) / abs($values[1]) * 100, 2),
        ])->values();

        $topProducts = (clone $lines)->join('products', 'products.id', '=', 'bill_product.product_id')
            ->where('bills.is_damaged', false)
            ->selectRaw('products.name, SUM(bill_product.quantity) as quantity,
                SUM(bill_product.selling_price * bill_product.quantity - COALESCE(bill_product.discount, 0)) as revenue,
                SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity - COALESCE(bill_product.discount, 0)) as profit')
            ->groupBy('products.id', 'products.name')->orderByDesc('profit')->limit(10)->get();
        $topSuppliers = (clone $purchases)->with('supplier:id,name')->get()
            ->groupBy('supplier_id')->map(fn ($rows) => [
                'name' => $rows->first()->supplier?->name ?? __('finance.common.no_data'),
                'count' => $rows->count(), 'purchases' => (float) $rows->sum('total_amount'),
            ])->sortByDesc('purchases')->take(10)->values();
        $expenses = Expense::withoutGlobalScopes()->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)
            ->selectRaw('title as name, SUM(amount) as amount')->groupBy('title')->orderByDesc('amount')->get();
        $staff = EmployeePayment::query()->whereIn('employee_id', Employee::where('shop_owner_id', $ownerId)->select('id'))
            ->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->with('employee:id,name')->get()->groupBy('employee_id')
            ->map(fn ($rows) => ['name' => $rows->first()->employee?->name, 'amount' => (float) $rows->sum('amount')])->values();
        $damaged = (clone $lines)->join('products', 'products.id', '=', 'bill_product.product_id')->where('bills.is_damaged', true)
            ->selectRaw('products.name, SUM(ABS(bill_product.quantity)) as quantity, SUM(bill_product.cost_price * ABS(bill_product.quantity)) as amount')
            ->groupBy('products.id', 'products.name')->orderByDesc('amount')->get();
        $returned = (clone $bills)->where('is_returned', true)->with('customer:id,name')->latest()->get()
            ->map(fn ($bill) => ['id' => $bill->id, 'date' => ShopTime::local($bill->created_at, $ownerId)->format('Y-m-d H:i'),
                'name' => $bill->customer?->name, 'amount' => abs((float) $bill->total_price)]);
        $returnedProducts = (clone $lines)->join('products', 'products.id', '=', 'bill_product.product_id')
            ->where('bills.is_returned', true)
            ->selectRaw('products.name, SUM(ABS(bill_product.quantity)) as quantity,
                SUM(bill_product.cost_price * ABS(bill_product.quantity)) as returned_cost,
                ABS(SUM((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity - COALESCE(bill_product.discount, 0))) as lost_profit')
            ->groupBy('products.id', 'products.name')->orderByDesc('returned_cost')->get();
        $balanceTables = [];
        foreach (['customers' => Customer::class, 'suppliers' => Supplier::class] as $entity => $model) {
            foreach (['owing', 'owed'] as $side) {
                $negative = ($entity === 'customers') === ($side === 'owing');
                $query = $model::withoutGlobalScopes()->where('user_id', $ownerId)->where('balance', $negative ? '<' : '>', 0);
                $balanceTables[$entity.'_'.$side] = [
                    'total' => abs((float) (clone $query)->sum('balance')),
                    'rows' => $query->orderBy('balance', $negative ? 'asc' : 'desc')->limit(10)->get(['name', 'phone', 'balance']),
                ];
            }
        }
        $payments = $flows->whereIn('category', ['collection', 'customer_refund', 'supplier_payment', 'supplier_refund'])
            ->groupBy('category')->map(fn ($rows, $key) => [
                'name' => __('finance.cash_drawer.categories.'.$key),
                'money_in' => (float) $rows->sum('amount_in'), 'money_out' => (float) $rows->sum('amount_out'),
            ])->values();

        return [
            'inventory' => $inventory, 'capital' => $capital, 'growth' => $growth, 'trends' => $trends,
            'purchases' => (float) (clone $purchases)->sum('total_amount'), 'balances' => $balanceTables,
            'tables' => [
                'trends' => ['columns' => ['date', 'revenue', 'profit', 'returns', 'damaged', 'purchases', 'money_in', 'money_out'], 'rows' => $trends],
                'growth' => ['columns' => ['metric', 'current', 'previous', 'growth'], 'rows' => $growth],
                'top_products' => ['columns' => ['name', 'quantity', 'revenue', 'profit'], 'rows' => $topProducts],
                'top_suppliers' => ['columns' => ['name', 'count', 'purchases'], 'rows' => $topSuppliers],
                'expenses_breakdown' => ['columns' => ['name', 'amount'], 'rows' => $expenses],
                'staff_breakdown' => ['columns' => ['name', 'amount'], 'rows' => $staff],
                'payment_breakdown' => ['columns' => ['name', 'money_in', 'money_out'], 'rows' => $payments],
                'damaged_breakdown' => ['columns' => ['name', 'quantity', 'amount'], 'rows' => $damaged],
                'returns_breakdown' => ['columns' => ['id', 'date', 'name', 'amount'], 'rows' => $returned],
                'returned_products' => ['columns' => ['name', 'quantity', 'returned_cost', 'lost_profit'], 'rows' => $returnedProducts],
            ],
            'team' => $this->teamSummary($ownerId, $from, $to),
        ];
    }

    public function teamSummary(int $ownerId, string $fromDate, string $toDate): array
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($fromDate, $toDate, $ownerId);

        $users = User::withoutGlobalScopes()
            ->whereKey($ownerId)
            ->orWhere(function ($query) use ($ownerId) {
                $query->where('role', 'employee')->where('shop_owner_id', $ownerId);
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$ownerId])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $billAgg = DB::table('bills')
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('created_by,
                SUM(CASE WHEN is_damaged = 0 AND is_returned = 0 THEN 1 ELSE 0 END) as bills_count,
                SUM(CASE WHEN is_damaged = 0 AND is_returned = 0 THEN total_price ELSE 0 END) as sales_total,
                SUM(CASE WHEN is_returned = 1 THEN ABS(total_price) ELSE 0 END) as returns_total,
                SUM(CASE WHEN is_damaged = 1 THEN ABS(total_price) ELSE 0 END) as damaged_total')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $discountAgg = DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->selectRaw('bills.created_by, COALESCE(SUM(bill_product.discount), 0) as discounts')
            ->groupBy('bills.created_by')
            ->pluck('discounts', 'created_by');

        $deletedAgg = ActivityLog::query()
            ->forOwner($ownerId)
            ->where('subject_type', 'bill')
            ->where('action', 'deleted')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('actor_id, COUNT(*) as deleted_count')
            ->groupBy('actor_id')
            ->pluck('deleted_count', 'actor_id');

        $activityAgg = ActivityLog::query()
            ->forOwner($ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('actor_id, MIN(created_at) as first_action, MAX(created_at) as last_action')
            ->groupBy('actor_id')
            ->get()
            ->keyBy('actor_id');

        $expenseAgg = ActivityLog::query()
            ->forOwner($ownerId)
            ->where('subject_type', 'expense')
            ->where('action', 'created')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw('actor_id, COUNT(*) as expense_entries')
            ->groupBy('actor_id')
            ->pluck('expense_entries', 'actor_id');

        $paymentAgg = ActivityLog::query()
            ->forOwner($ownerId)
            ->join('customer_payments', function ($join) {
                $join->on('activity_logs.subject_id', '=', 'customer_payments.id')
                    ->where('activity_logs.subject_type', '=', 'customer_payment');
            })
            ->where('activity_logs.action', 'created')
            ->whereBetween('activity_logs.created_at', [$startUtc, $endUtc])
            ->where('customer_payments.amount', '>', 0)
            ->where(function ($query) {
                $query->whereIn('customer_payments.kind', ['bill_payment', 'payment'])
                    ->orWhere(function ($inner) {
                        $inner->whereNull('customer_payments.kind')
                            ->whereRaw("COALESCE(customer_payments.note, '') != 'Initial balance'")
                            ->whereRaw("COALESCE(customer_payments.note, '') not like 'Bill #% created as debt'");
                    });
            })
            ->selectRaw('activity_logs.actor_id, COALESCE(SUM(customer_payments.amount), 0) as collections')
            ->groupBy('activity_logs.actor_id')
            ->pluck('collections', 'activity_logs.actor_id');

        $rows = $users->map(function (User $user) use ($billAgg, $discountAgg, $deletedAgg, $activityAgg, $expenseAgg, $paymentAgg) {
            $sales = $billAgg->get($user->id);
            $activity = $activityAgg->get($user->id);

            return [
                'user' => $user,
                'bills_count' => (int) ($sales->bills_count ?? 0),
                'sales_total' => round((float) ($sales->sales_total ?? 0), 2),
                'returns_total' => round((float) ($sales->returns_total ?? 0), 2),
                'discounts' => round((float) ($discountAgg[$user->id] ?? 0), 2),
                'damaged_total' => round((float) ($sales->damaged_total ?? 0), 2),
                'deleted_bills' => (int) ($deletedAgg[$user->id] ?? 0),
                'collections' => round((float) ($paymentAgg[$user->id] ?? 0), 2),
                'expense_entries' => (int) ($expenseAgg[$user->id] ?? 0),
                'first_action' => $activity?->first_action ? Carbon::parse($activity->first_action) : null,
                'last_action' => $activity?->last_action ? Carbon::parse($activity->last_action) : null,
            ];
        });

        return [
            'rows' => $rows,
            'totals' => [
                'bills_count' => $rows->sum('bills_count'),
                'sales_total' => round((float) $rows->sum('sales_total'), 2),
                'returns_total' => round((float) $rows->sum('returns_total'), 2),
                'discounts' => round((float) $rows->sum('discounts'), 2),
                'damaged_total' => round((float) $rows->sum('damaged_total'), 2),
                'deleted_bills' => $rows->sum('deleted_bills'),
                'collections' => round((float) $rows->sum('collections'), 2),
                'expense_entries' => $rows->sum('expense_entries'),
            ],
        ];
    }

    public function activityFeed(int $ownerId, Request $request, bool $paginate = true): LengthAwarePaginator|Collection
    {
        $query = $this->activityFeedQuery($ownerId, $request)->orderByDesc('created_at');

        return $paginate ? $query->paginate(25)->withQueryString() : $query->get();
    }

    public function activityFeedQuery(int $ownerId, Request $request): EloquentBuilder
    {
        $query = ActivityLog::query()->forOwner($ownerId);

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type')->toString());
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('from') || $request->filled('to')) {
            [$startUtc, $endUtc] = ShopTime::utcRange(
                $request->string('from', ShopTime::today($ownerId))->toString(),
                $request->string('to', $request->string('from', ShopTime::today($ownerId))->toString())->toString(),
                $ownerId,
            );
            $query->whereBetween('created_at', [$startUtc, $endUtc]);
        }

        if ($request->filled('min_amount')) {
            $query->whereRaw('ABS(COALESCE(amount, 0)) >= ?', [(float) $request->input('min_amount')]);
        }

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->input('search')).'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('actor_name', 'like', $search)
                    ->orWhere('subject_label', 'like', $search)
                    ->orWhere('subject_type', 'like', $search)
                    ->orWhere('action', 'like', $search);
            });
        }

        return $query;
    }

    public function dataHealth(int $ownerId): array
    {
        return Cache::remember("finance-health-{$ownerId}", now()->addMinutes(5), function () use ($ownerId) {
            $owner = User::withoutGlobalScopes()->find($ownerId);
            $supplierNames = Supplier::withoutGlobalScopes()->where('user_id', $ownerId)->pluck('name')->filter()->map(fn ($name) => mb_strtolower($name));

            $issues = [
                'bad_costing' => Product::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where(function ($query) {
                        $query->where('cost_price', '<=', 0)->orWhereColumn('selling_price', '<', 'cost_price');
                    })
                    ->count(),
                'negative_stock' => $owner && $owner->isRestaurantAccount()
                    ? 0
                    : Product::withoutGlobalScopes()->where('user_id', $ownerId)->where('quantity', '<', 0)->count(),
                'zero_total_bills' => Bill::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('is_damaged', false)
                    ->where('total_price', 0)
                    ->count(),
                'customer_balance_mismatch' => $this->customerBalanceMismatchQuery($ownerId)->count(),
                'negative_supplier_balance' => Supplier::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('balance', '<', 0)
                    ->count(),
                'supplier_like_expenses' => Expense::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->get()
                    ->filter(function (Expense $expense) use ($supplierNames) {
                        $title = mb_strtolower($expense->title);

                        return str_contains($title, 'supplier')
                            || str_contains($title, 'dealer')
                            || str_contains($title, 'purchase')
                            || str_contains($title, 'مورد')
                            || str_contains($title, 'تاجر')
                            || str_contains($title, 'بضاعة')
                            || $supplierNames->contains(fn ($name) => $name !== '' && str_contains($title, $name));
                    })
                    ->count(),
                'stock_without_cost' => Product::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('quantity', '>', 0)
                    ->where(function ($query) {
                        $query->whereNull('cost_price')->orWhere('cost_price', '<=', 0);
                    })
                    ->count(),
            ];

            return $issues;
        });
    }

    public function customerBalanceMismatchQuery(int $ownerId)
    {
        return DB::table('customers')
            ->leftJoin('customer_payments', function ($join) {
                $join->on('customers.id', '=', 'customer_payments.customer_id');
            })
            ->where('customers.user_id', $ownerId)
            ->select('customers.id')
            ->groupBy('customers.id', 'customers.balance')
            ->havingRaw('ROUND(customers.balance, 2) <> ROUND(COALESCE(SUM(customer_payments.amount), 0), 2)');
    }

    public function profitLoss(int $ownerId, string $fromDate, string $toDate): array
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($fromDate, $toDate, $ownerId);
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        $diff = $from->diffInDays($to) + 1;
        $compareTo = $from->copy()->subDay();
        $compareFrom = $compareTo->copy()->subDays($diff - 1);
        [$compareStartUtc, $compareEndUtc] = ShopTime::utcRange($compareFrom->toDateString(), $compareTo->toDateString(), $ownerId);

        $current = $this->profitLossRange($ownerId, $fromDate, $toDate, $startUtc, $endUtc);
        $compare = $this->profitLossRange($ownerId, $compareFrom->toDateString(), $compareTo->toDateString(), $compareStartUtc, $compareEndUtc);

        return $current + ['compare' => $compare];
    }

    public function receivablesAging(int $ownerId, string $asOfDate): array
    {
        return $this->receivableOrPayableAging($ownerId, $asOfDate, 'customer');
    }

    public function payablesAging(int $ownerId, string $asOfDate): array
    {
        return $this->receivableOrPayableAging($ownerId, $asOfDate, 'supplier');
    }

    public function inventoryValuation(int $ownerId, string $fromDate, string $toDate): array
    {
        [, $toEndUtc] = ShopTime::utcRange($toDate, $toDate, $ownerId);
        $categoryRows = Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->selectRaw("COALESCE(category, '') as category, SUM(quantity * cost_price) as value, SUM(quantity) as qty")
            ->groupByRaw("COALESCE(category, '')")
            ->orderByDesc('value')
            ->get();

        $cutoff = Carbon::parse($toDate)->subDays(90);
        $soldRecently = DB::table('bill_product')
            ->join('bills', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->where('bills.is_damaged', false)
            ->where('bills.created_at', '>=', $cutoff)
            ->where('bills.created_at', '<=', $toEndUtc)
            ->pluck('bill_product.product_id')
            ->unique();

        $deadStock = Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('quantity', '>', 0)
            ->whereNotIn('id', $soldRecently)
            ->orderByDesc(DB::raw('quantity * cost_price'))
            ->get();

        return [
            'categories' => $categoryRows,
            'total_value' => round((float) $categoryRows->sum('value'), 2),
            'dead_stock' => $deadStock,
        ];
    }

    public function balancesSummary(int $ownerId, string $asOfDate): array
    {
        $receivables = $this->receivablesAging($ownerId, $asOfDate);
        $payables = $this->payablesAging($ownerId, $asOfDate);

        return [
            'cash_estimate' => $this->cashFlow->currentCashEstimate($ownerId, $asOfDate),
            'receivables' => round((float) $receivables['totals']['total'], 2),
            'payables' => round((float) $payables['totals']['total'], 2),
            'inventory_value' => round((float) Product::withoutGlobalScopes()->where('user_id', $ownerId)->sum(DB::raw('quantity * cost_price')), 2),
            'capital' => round((float) CapitalEntry::withoutGlobalScopes()->where('user_id', $ownerId)->whereDate('entry_date', '<=', $asOfDate)->sum('amount'), 2),
        ];
    }

    private function profitLossRange(int $ownerId, string $fromDate, string $toDate, Carbon $startUtc, Carbon $endUtc): array
    {
        $salesRevenue = (float) Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('is_damaged', false)
            ->where('is_returned', false)
            ->sum('total_price');

        $returns = (float) Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('is_returned', true)
            ->sum(DB::raw('ABS(total_price)'));

        $signedRevenue = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->selectRaw('COALESCE(SUM(bill_product.selling_price * bill_product.quantity), 0) as total')
            ->value('total');

        $discounts = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->where('bills.is_returned', false)
            ->selectRaw('COALESCE(SUM(bill_product.discount), 0) as total')
            ->value('total');

        $grossProfit = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->selectRaw('COALESCE(SUM(((bill_product.selling_price - bill_product.cost_price) * bill_product.quantity) - COALESCE(bill_product.discount, 0)), 0) as total')
            ->value('total');

        $cogs = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', false)
            ->selectRaw('COALESCE(SUM(bill_product.cost_price * bill_product.quantity), 0) as total')
            ->value('total');

        $expenses = Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->selectRaw("COALESCE(category, '') as category, SUM(amount) as total")
            ->groupByRaw("COALESCE(category, '')")
            ->get();
        $expenseTotal = (float) Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->sum('amount');

        $staffNetPayments = (float) EmployeePayment::query()
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->whereIn('employee_id', Employee::query()->where('shop_owner_id', $ownerId)->pluck('id'))
            ->sum('amount');
        $staffRefunds = (float) abs(EmployeePayment::query()
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->whereIn('employee_id', Employee::query()->where('shop_owner_id', $ownerId)->pluck('id'))
            ->where('amount', '<', 0)
            ->sum('amount'));

        $damagedLoss = (float) DB::table('bills')
            ->join('bill_product', 'bills.id', '=', 'bill_product.bill_id')
            ->where('bills.user_id', $ownerId)
            ->whereBetween('bills.created_at', [$startUtc, $endUtc])
            ->where('bills.is_damaged', true)
            ->selectRaw('COALESCE(SUM(bill_product.cost_price * ABS(bill_product.quantity)), 0) as total')
            ->value('total');

        $netProfit = $grossProfit - $expenseTotal - $staffNetPayments - $damagedLoss;
        $billsCount = (int) Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->where('is_damaged', false)
            ->where('is_returned', false)
            ->count();

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'revenue' => round($salesRevenue, 2),
            'bills_count' => $billsCount,
            'average_bill' => $billsCount > 0 ? round($salesRevenue / $billsCount, 2) : 0.0,
            'returns' => round($returns, 2),
            'net_revenue' => round($salesRevenue - $returns, 2),
            'signed_revenue' => round($signedRevenue, 2),
            'cogs' => round($cogs, 2),
            'discounts' => round($discounts, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses_total' => round($expenseTotal, 2),
            'expenses_by_category' => $expenses,
            'staff_payments' => round($staffNetPayments, 2),
            'staff_refunds' => round($staffRefunds, 2),
            'damaged_loss' => round($damagedLoss, 2),
            'net_profit' => round($netProfit, 2),
            'gross_margin' => $salesRevenue > 0 ? round(($grossProfit / $salesRevenue) * 100, 2) : 0.0,
            'net_margin' => $salesRevenue > 0 ? round(($netProfit / $salesRevenue) * 100, 2) : 0.0,
        ];
    }

    private function receivableOrPayableAging(int $ownerId, string $asOfDate, string $entity): array
    {
        return $entity === 'customer'
            ? $this->customerAging($ownerId, $asOfDate)
            : $this->supplierAging($ownerId, $asOfDate);
    }

    private function customerAging(int $ownerId, string $asOfDate): array
    {
        [, $asOfEndUtc] = ShopTime::utcRange($asOfDate, $asOfDate, $ownerId);
        $asOf = Carbon::parse($asOfDate);
        $balances = CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('created_at', '<=', $asOfEndUtc)
            ->selectRaw('customer_id, COALESCE(SUM(amount), 0) as balance')
            ->groupBy('customer_id')
            ->havingRaw('COALESCE(SUM(amount), 0) < 0')
            ->pluck('balance', 'customer_id');
        $customers = Customer::withoutGlobalScopes()->whereIn('id', $balances->keys())->get()->keyBy('id');
        $charges = CustomerPayment::withoutGlobalScopes()
            ->leftJoin('bills', 'bills.id', '=', 'customer_payments.bill_id')
            ->where('customer_payments.user_id', $ownerId)
            ->where('customer_payments.created_at', '<=', $asOfEndUtc)
            ->where(function ($query) {
                $query->where('customer_payments.kind', 'bill_charge')
                    ->orWhere(function ($inner) {
                        $inner->whereNull('customer_payments.kind')
                            ->where('customer_payments.note', 'like', 'Bill #% created as debt');
                    });
            })
            ->selectRaw('customer_payments.customer_id, customer_payments.bill_id, COALESCE(MAX(bills.created_at), MAX(customer_payments.created_at)) as bill_date, ABS(SUM(customer_payments.amount)) as total_due')
            ->groupBy('customer_payments.customer_id', 'customer_payments.bill_id')
            ->orderByDesc('bill_date')
            ->get()
            ->groupBy('customer_id');
        $paidByBill = CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('created_at', '<=', $asOfEndUtc)
            ->where('amount', '>', 0)
            ->where('kind', 'bill_payment')
            ->whereNotNull('bill_id')
            ->selectRaw('customer_id, bill_id, COALESCE(SUM(amount), 0) as paid')
            ->groupBy('customer_id', 'bill_id')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($rows) => $rows->keyBy('bill_id'));

        return $this->allocateAgingRows($customers, $balances, $charges, $paidByBill, $asOf, 'customer');
    }

    private function supplierAging(int $ownerId, string $asOfDate): array
    {
        $asOf = Carbon::parse($asOfDate);
        $purchaseTotals = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('purchase_date', '<=', $asOfDate)
            ->selectRaw('supplier_id, COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');
        $payments = SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->get();
        $paymentTotals = $payments->filter(fn (SupplierPayment $payment) => $this->cashFlowSupplierKind($payment) !== 'opening_balance')
            ->groupBy('supplier_id')
            ->map(fn ($rows) => (float) $rows->sum('amount'));
        $openingTotals = $payments->filter(fn (SupplierPayment $payment) => $this->cashFlowSupplierKind($payment) === 'opening_balance')
            ->groupBy('supplier_id')
            ->map(fn ($rows) => (float) $rows->sum(fn ($payment) => abs((float) $payment->amount)));
        $supplierIds = $purchaseTotals->keys()->merge($paymentTotals->keys())->merge($openingTotals->keys())->unique()->values();
        $suppliers = Supplier::withoutGlobalScopes()->whereIn('id', $supplierIds)->get()->keyBy('id');
        $balances = $supplierIds->mapWithKeys(fn ($supplierId) => [
            $supplierId => round((float) ($purchaseTotals[$supplierId] ?? 0) - (float) ($paymentTotals[$supplierId] ?? 0) + (float) ($openingTotals[$supplierId] ?? 0), 2),
        ])->filter(fn ($value) => $value > 0);
        $charges = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('purchase_date', '<=', $asOfDate)
            ->selectRaw('supplier_id, id as bill_id, purchase_date as bill_date, total_amount as total_due')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('supplier_id');
        $paidByBill = $payments->filter(fn (SupplierPayment $payment) => $this->cashFlowSupplierKind($payment) !== 'opening_balance' && $payment->purchase_bill_id && (float) $payment->amount > 0)
            ->groupBy('supplier_id')
            ->map(function ($rows) {
                return $rows->groupBy('purchase_bill_id')->map(fn ($paymentRows) => (object) ['paid' => (float) $paymentRows->sum('amount')]);
            });

        return $this->allocateAgingRows($suppliers, $balances, $charges, $paidByBill, $asOf, 'supplier', $openingTotals);
    }

    private function allocateAgingRows(Collection $entities, Collection $balances, Collection $charges, Collection $paidByBill, Carbon $asOf, string $entityKey, ?Collection $openingTotals = null): array
    {
        $rows = [];
        $totals = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0, 'unallocated' => 0, 'total' => 0];

        foreach ($balances as $entityId => $balance) {
            $remaining = round(abs((float) $balance), 2);
            if ($remaining <= 0) {
                continue;
            }

            $buckets = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0, 'unallocated' => 0];
            foreach ($charges->get($entityId, collect()) as $charge) {
                if ($remaining <= 0) {
                    break;
                }

                $paid = (float) (($paidByBill->get($entityId)?->get($charge->bill_id)->paid) ?? 0);
                $chargeTotal = round((float) $charge->total_due - $paid, 2);
                if ($chargeTotal <= 0) {
                    continue;
                }

                $due = min($chargeTotal, $remaining);
                $days = Carbon::parse(ShopTime::localDate($charge->bill_date, optional($entities->get($entityId))->user_id ?? optional($entities->get($entityId))->shop_owner_id ?? null))->diffInDays($asOf);
                $bucket = $days <= 30 ? '0_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus'));
                $buckets[$bucket] += $due;
                $remaining = round($remaining - $due, 2);
            }

            if ($remaining > 0) {
                $buckets['unallocated'] += $remaining;
            }

            $rowTotal = round(array_sum($buckets), 2);
            foreach ($buckets as $bucket => $value) {
                $totals[$bucket] += $value;
            }
            $totals['total'] += $rowTotal;
            $rows[] = [$entityKey => $entities->get($entityId), 'buckets' => array_map(fn ($value) => round($value, 2), $buckets), 'total' => $rowTotal];
        }

        return ['rows' => collect($rows), 'totals' => array_map(fn ($value) => round($value, 2), $totals)];
    }

    private function cashFlowSupplierKind(SupplierPayment $payment): string
    {
        if ($payment->kind) {
            return $payment->kind;
        }

        return trim((string) ($payment->note ?? '')) === 'Initial balance' ? 'opening_balance' : 'payment';
    }
}
