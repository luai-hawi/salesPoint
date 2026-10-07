<?php

namespace App\Services\Finance;

use App\Models\Bill;
use App\Models\CapitalEntry;
use App\Models\CashMovement;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\SupplierPayment;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashFlowService
{
    public function resolvePeriod(int $ownerId, ?string $preset, ?string $from, ?string $to): array
    {
        $today = ShopTime::local(now(), $ownerId);
        $preset = $preset ?: 'today';

        return match ($preset) {
            'yesterday' => $this->period($ownerId, 'yesterday', $today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()),
            'this_week' => $this->period($ownerId, 'this_week', $today->copy()->startOfWeek()->toDateString(), $today->copy()->toDateString()),
            'this_month' => $this->period($ownerId, 'this_month', $today->copy()->startOfMonth()->toDateString(), $today->copy()->toDateString()),
            'custom' => $this->period($ownerId, 'custom', $from ?: $today->toDateString(), $to ?: ($from ?: $today->toDateString())),
            default => $this->period($ownerId, 'today', $today->toDateString(), $today->toDateString()),
        };
    }

    public function cashDrawerData(int $ownerId, string $fromDate, string $toDate, string $methodFilter = 'cash'): array
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($fromDate, $toDate, $ownerId);
        $allRows = $this->periodRows($ownerId, $fromDate, $toDate, $startUtc, $endUtc);
        $cashRows = $allRows->filter(fn (array $row) => $this->matchesMethodFilter($row['method'], 'cash'))->values();
        $viewRows = $allRows->filter(fn (array $row) => $this->matchesMethodFilter($row['method'], $methodFilter))->values();
        $opening = $this->openingSnapshot($ownerId, $fromDate, $endUtc);

        $viewRows = $this->attachRunningBalance($ownerId, $viewRows, $cashRows, $methodFilter, $opening);
        $cashClosingBalance = $opening['has_running_balance']
            ? round((float) ($opening['opening_balance'] + $cashRows->sum('signed_amount')), 2)
            : null;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'method_filter' => $methodFilter,
            'rows' => $viewRows,
            'totals' => $this->totals($viewRows),
            'opening_balance' => $methodFilter === 'cash' ? $opening['opening_balance'] : null,
            'closing_balance' => $methodFilter === 'cash' ? $cashClosingBalance : null,
            'cash_closing_balance' => $cashClosingBalance,
            'has_running_balance' => $methodFilter === 'cash' ? $opening['has_running_balance'] : false,
            'opening_notice' => $methodFilter === 'cash' ? $opening['opening_notice'] : null,
            'totals_by_category' => $this->totalsByCategory($viewRows),
            'totals_by_method' => $this->totalsByMethod($viewRows),
            'cash_totals' => $this->totals($cashRows),
            'settlement_totals' => $this->totals($allRows),
        ];
    }

    public function moneyFlowSummary(int $ownerId, string $fromDate, string $toDate): array
    {
        $settlement = $this->cashDrawerData($ownerId, $fromDate, $toDate, 'all');
        $cashDrawer = $this->cashDrawerData($ownerId, $fromDate, $toDate, 'cash');
        $allCategories = collect($settlement['rows'])->groupBy('category');
        $cashCategories = collect($cashDrawer['rows'])->groupBy('category');

        return [
            'settlement' => [
                'cash_in' => round((float) $settlement['totals']['in'], 2),
                'cash_out' => round((float) $settlement['totals']['out'], 2),
                'net' => round((float) $settlement['totals']['net'], 2),
            ],
            'cash_drawer' => [
                'cash_in' => round((float) $cashDrawer['totals']['in'], 2),
                'cash_out' => round((float) $cashDrawer['totals']['out'], 2),
                'net' => round((float) $cashDrawer['totals']['net'], 2),
                'opening_balance' => $cashDrawer['opening_balance'],
                'closing_balance' => $cashDrawer['closing_balance'],
            ],
            'cash_in' => [
                'sales' => round((float) $allCategories->get('cash_sale', collect())->sum('amount_in'), 2),
                'collections' => round((float) $allCategories->get('collection', collect())->sum('amount_in'), 2),
                'supplier_refunds' => round((float) $allCategories->get('supplier_refund', collect())->sum('amount_in'), 2),
                'staff_refunds' => round((float) $allCategories->get('staff_refund', collect())->sum('amount_in'), 2),
                'capital' => round((float) $allCategories->get('capital', collect())->sum('amount_in'), 2),
                'manual' => round((float) $allCategories->get('manual_in', collect())->sum('amount_in'), 2),
                'total' => round((float) $settlement['totals']['in'], 2),
            ],
            'cash_out' => [
                'supplier_payments' => round((float) $allCategories->get('supplier_payment', collect())->sum('amount_out'), 2),
                'employee_payments' => round((float) $allCategories->get('employee_payment', collect())->sum('amount_out'), 2),
                'customer_refunds' => round((float) $allCategories->get('customer_refund', collect())->sum('amount_out'), 2),
                'expenses' => round((float) $allCategories->get('expense', collect())->sum('amount_out'), 2),
                'manual' => round((float) $allCategories->get('manual_out', collect())->sum('amount_out'), 2),
                'capital_withdrawals' => round((float) $allCategories->get('capital', collect())->sum('amount_out'), 2),
                'total' => round((float) $settlement['totals']['out'], 2),
            ],
            'cash_only_categories' => [
                'collections' => round((float) $cashCategories->get('collection', collect())->sum('amount_in'), 2),
                'customer_refunds' => round((float) $cashCategories->get('customer_refund', collect())->sum('amount_out'), 2),
            ],
        ];
    }

    public function currentCashEstimate(int $ownerId, string $asOfDate): ?float
    {
        return $this->cashDrawerData($ownerId, $asOfDate, $asOfDate, 'cash')['closing_balance'];
    }

    public function dayCloseCashSummary(int $ownerId, string $date): array
    {
        $all = $this->cashDrawerData($ownerId, $date, $date, 'all');
        $cash = $this->cashDrawerData($ownerId, $date, $date, 'cash');

        return [
            'all' => $all,
            'cash' => $cash,
            'received_by_method' => collect(['cash', 'card', 'transfer', 'check'])->mapWithKeys(function (string $method) use ($all) {
                $methodRows = collect($all['rows'])->where('method', $method);

                return [$method => round((float) $methodRows->whereIn('category', ['cash_sale', 'collection'])->sum('amount_in'), 2)];
            })->all(),
        ];
    }

    private function period(int $ownerId, string $preset, string $fromDate, string $toDate): array
    {
        [$startUtc, $endUtc] = ShopTime::utcRange($fromDate, $toDate, $ownerId);

        return [
            'preset' => $preset,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'start_utc' => $startUtc,
            'end_utc' => $endUtc,
        ];
    }

    private function periodRows(int $ownerId, string $fromDate, string $toDate, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return collect()
            ->merge($this->billSales($ownerId, $startUtc, $endUtc))
            ->merge($this->customerMovements($ownerId, $startUtc, $endUtc))
            ->merge($this->supplierPayments($ownerId, $fromDate, $toDate))
            ->merge($this->employeePayments($ownerId, $fromDate, $toDate))
            ->merge($this->expenses($ownerId, $fromDate, $toDate))
            ->merge($this->capitalEntries($ownerId, $fromDate, $toDate))
            ->merge($this->manualMovements($ownerId, $startUtc, $endUtc))
            ->sortBy(fn (array $row) => sprintf('%s-%09d', $row['occurred_at']->format('YmdHis.u'), $row['sort_id']))
            ->values();
    }

    private function openingSnapshot(int $ownerId, string $fromDate, Carbon $endUtc): array
    {
        $opening = CashMovement::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('type', 'opening')
            ->where('occurred_at', '<=', $endUtc)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        if (! $opening) {
            return [
                'has_running_balance' => false,
                'opening_balance' => null,
                'opening_notice' => __('finance.cash_drawer.no_opening_balance_notice'),
            ];
        }

        [$startUtc] = ShopTime::utcRange($fromDate, $fromDate, $ownerId);
        $prePeriodLocalEnd = Carbon::parse($fromDate)->subDay()->toDateString();
        $openingBalance = round(
            (float) $opening->amount + $this->cashAggregateBetween(
                $ownerId,
                $opening->occurred_at,
                $startUtc,
                ShopTime::localDate($opening->occurred_at, $ownerId),
                $prePeriodLocalEnd,
            ),
            2,
        );

        return [
            'has_running_balance' => true,
            'opening_balance' => $openingBalance,
            'opening_notice' => null,
        ];
    }

    private function cashAggregateBetween(int $ownerId, Carbon $fromUtc, Carbon $toUtc, string $fromLocalDate, string $toLocalDate): float
    {
        if ($fromUtc >= $toUtc || $fromLocalDate > $toLocalDate) {
            return 0.0;
        }

        $employeeIds = Employee::query()->where('shop_owner_id', $ownerId)->pluck('id');

        $billSales = (float) Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNull('customer_id')
            ->where('is_damaged', false)
            ->where(function ($query) {
                $query->whereNull('payment_method')->orWhere('payment_method', 'cash');
            })
            ->where('created_at', '>=', $fromUtc)
            ->where('created_at', '<', $toUtc)
            ->selectRaw('COALESCE(SUM(CASE WHEN is_returned = 1 THEN -ABS(total_price) ELSE ABS(total_price) END), 0) as total')
            ->value('total');

        $customerRows = (float) CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('type', 'cash')
            ->where('created_at', '>=', $fromUtc)
            ->where('created_at', '<', $toUtc)
            ->get()
            ->sum(function (CustomerPayment $payment) {
                $kind = $this->classifyCustomerPayment($payment);

                return match (true) {
                    in_array($kind, ['bill_payment', 'payment'], true) && (float) $payment->amount > 0 => (float) $payment->amount,
                    $kind === 'adjustment' && (float) $payment->amount < 0 => (float) $payment->amount,
                    default => 0.0,
                };
            });

        $supplierRows = (float) SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('payment_date', '>=', $fromLocalDate)
            ->whereDate('payment_date', '<=', $toLocalDate)
            ->where(function ($query) {
                $query->whereNull('type')->orWhere('type', 'cash');
            })
            ->get()
            ->sum(function (SupplierPayment $payment) {
                $kind = $this->classifySupplierPayment($payment);
                if ($kind === 'opening_balance') {
                    return 0.0;
                }

                return -1 * (float) $payment->amount;
            });

        $employeeRows = (float) EmployeePayment::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('payment_date', '>=', $fromLocalDate)
            ->whereDate('payment_date', '<=', $toLocalDate)
            ->where(function ($query) {
                $query->whereNull('type')->orWhere('type', 'cash');
            })
            ->sum('amount');

        $expenses = (float) Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $fromLocalDate)
            ->whereDate('expense_date', '<=', $toLocalDate)
            ->sum(DB::raw('-amount'));

        $capital = (float) CapitalEntry::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('entry_date', '>=', $fromLocalDate)
            ->whereDate('entry_date', '<=', $toLocalDate)
            ->sum('amount');

        $manual = (float) CashMovement::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('type', '!=', 'opening')
            ->where('occurred_at', '>=', $fromUtc)
            ->where('occurred_at', '<', $toUtc)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) as total")
            ->value('total');

        return round($billSales + $customerRows - $employeeRows + $supplierRows + $expenses + $capital + $manual, 2);
    }

    private function billSales(int $ownerId, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNull('customer_id')
            ->where('is_damaged', false)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with('creator:id,name')
            ->get()
            ->map(function (Bill $bill) {
                $amount = round(abs((float) $bill->total_price), 2);

                return $this->row(
                    'cash_sale',
                    $bill->payment_method ?: 'cash',
                    $bill->created_at,
                    (int) $bill->id,
                    'bill',
                    (string) $bill->id,
                    $bill->creator?->name,
                    $bill->is_returned ? -$amount : $amount,
                    $bill->is_returned ? 0.0 : $amount,
                    $bill->is_returned ? $amount : 0.0,
                    ['id' => $bill->id],
                );
            });
    }

    private function customerMovements(int $ownerId, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return CustomerPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with(['customer:id,name'])
            ->get()
            ->map(function (CustomerPayment $row) {
                $kind = $this->classifyCustomerPayment($row);
                $amount = round((float) $row->amount, 2);

                if (in_array($kind, ['bill_payment', 'payment'], true) && $amount > 0) {
                    return $this->row(
                        'collection',
                        $row->type ?: 'cash',
                        $row->created_at,
                        (int) $row->id,
                        'customer_payment',
                        $row->customer?->name ?: (string) $row->id,
                        null,
                        $amount,
                        $amount,
                        0.0,
                        ['id' => $row->id, 'bill_id' => $row->bill_id],
                    );
                }

                if ($kind === 'adjustment' && $amount < 0) {
                    $out = abs($amount);

                    return $this->row(
                        'customer_refund',
                        $row->type ?: 'cash',
                        $row->created_at,
                        (int) $row->id,
                        'customer_payment',
                        $row->customer?->name ?: (string) $row->id,
                        null,
                        -$out,
                        0.0,
                        $out,
                        ['id' => $row->id, 'bill_id' => $row->bill_id],
                    );
                }

                return null;
            })
            ->filter();
    }

    private function supplierPayments(int $ownerId, string $fromDate, string $toDate): Collection
    {
        return SupplierPayment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->with('supplier:id,name')
            ->get()
            ->map(function (SupplierPayment $payment) use ($ownerId) {
                $kind = $this->classifySupplierPayment($payment);
                if ($kind === 'opening_balance') {
                    return null;
                }

                $amount = round((float) $payment->amount, 2);
                $category = $amount < 0 ? 'supplier_refund' : 'supplier_payment';

                return $this->row(
                    $category,
                    $payment->type ?: 'cash',
                    $this->occursAtForDate($payment->payment_date, $payment->created_at, $ownerId),
                    (int) $payment->id,
                    'supplier_payment',
                    $payment->supplier?->name ?: (string) $payment->id,
                    null,
                    $amount < 0 ? abs($amount) : -$amount,
                    $amount < 0 ? abs($amount) : 0.0,
                    $amount > 0 ? $amount : 0.0,
                    ['id' => $payment->id, 'purchase_bill_id' => $payment->purchase_bill_id],
                );
            })
            ->filter();
    }

    private function employeePayments(int $ownerId, string $fromDate, string $toDate): Collection
    {
        $employeeIds = Employee::query()->where('shop_owner_id', $ownerId)->pluck('id');

        return EmployeePayment::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->with('employee:id,name')
            ->get()
            ->map(function (EmployeePayment $payment) use ($ownerId) {
                $amount = round((float) $payment->amount, 2);

                return $this->row(
                    $amount < 0 ? 'staff_refund' : 'employee_payment',
                    $payment->type ?: 'cash',
                    $this->occursAtForDate($payment->payment_date, $payment->created_at, $ownerId),
                    (int) $payment->id,
                    'employee_payment',
                    $payment->employee?->name ?: (string) $payment->id,
                    null,
                    $amount < 0 ? abs($amount) : -$amount,
                    $amount < 0 ? abs($amount) : 0.0,
                    $amount > 0 ? $amount : 0.0,
                    ['id' => $payment->id],
                );
            });
    }

    private function expenses(int $ownerId, string $fromDate, string $toDate): Collection
    {
        return Expense::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->get()
            ->map(fn (Expense $expense) => $this->row(
                'expense',
                'cash',
                $this->occursAtForDate($expense->expense_date, $expense->created_at, $ownerId),
                (int) $expense->id,
                'expense',
                $expense->title,
                null,
                -round((float) $expense->amount, 2),
                0.0,
                round((float) $expense->amount, 2),
                ['id' => $expense->id],
            ));
    }

    private function capitalEntries(int $ownerId, string $fromDate, string $toDate): Collection
    {
        return CapitalEntry::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->get()
            ->map(function (CapitalEntry $entry) use ($ownerId) {
                $amount = round((float) $entry->amount, 2);

                return $this->row(
                    'capital',
                    'cash',
                    $this->occursAtForDate($entry->entry_date, $entry->created_at, $ownerId),
                    (int) $entry->id,
                    'capital_entry',
                    $entry->note ?: (string) $entry->id,
                    null,
                    $amount,
                    $amount > 0 ? $amount : 0.0,
                    $amount < 0 ? abs($amount) : 0.0,
                    ['id' => $entry->id],
                );
            });
    }

    private function manualMovements(int $ownerId, Carbon $startUtc, Carbon $endUtc): Collection
    {
        return CashMovement::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('type', '!=', 'opening')
            ->whereBetween('occurred_at', [$startUtc, $endUtc])
            ->with('creator:id,name')
            ->get()
            ->map(function (CashMovement $movement) {
                $amount = round((float) $movement->amount, 2);
                $signed = match ($movement->type) {
                    'in' => $amount,
                    default => -$amount,
                };

                return $this->row(
                    match ($movement->type) {
                        'in' => 'manual_in',
                        default => 'manual_out',
                    },
                    'cash',
                    $movement->occurred_at,
                    (int) $movement->id,
                    'cash_movement',
                    $movement->reason,
                    $movement->creator?->name,
                    $signed,
                    $signed > 0 ? $signed : 0.0,
                    $signed < 0 ? abs($signed) : 0.0,
                    ['id' => $movement->id, 'note' => $movement->note],
                );
            })
            ->filter()
            ->values();
    }

    private function attachRunningBalance(int $ownerId, Collection $viewRows, Collection $cashRows, string $methodFilter, array $opening): Collection
    {
        if ($methodFilter !== 'cash' || ! $opening['has_running_balance']) {
            return $viewRows->map(fn (array $row) => $row + ['running_balance' => null, 'show_day_subtotal' => false])->values();
        }

        $running = (float) $opening['opening_balance'];
        $rowsByKey = $cashRows->keyBy('uid');
        $lastDate = null;

        return $viewRows->map(function (array $row) use ($ownerId, &$running, $rowsByKey, &$lastDate) {
            if ($rowsByKey->has($row['uid'])) {
                $running = round($running + $row['signed_amount'], 2);
            }

            $localDate = ShopTime::localDate($row['occurred_at'], $ownerId);
            $showDaySubtotal = $lastDate !== null && $lastDate !== $localDate;
            $lastDate = $localDate;

            return $row + [
                'running_balance' => $rowsByKey->has($row['uid']) ? $running : null,
                'show_day_subtotal' => $showDaySubtotal,
            ];
        })->values();
    }

    private function totals(Collection $rows): array
    {
        return [
            'in' => round((float) $rows->sum('amount_in'), 2),
            'out' => round((float) $rows->sum('amount_out'), 2),
            'net' => round((float) $rows->sum('signed_amount'), 2),
        ];
    }

    private function totalsByCategory(Collection $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['category']] = [
                'in' => round((float) (($totals[$row['category']]['in'] ?? 0) + $row['amount_in']), 2),
                'out' => round((float) (($totals[$row['category']]['out'] ?? 0) + $row['amount_out']), 2),
            ];
        }

        return $totals;
    }

    private function totalsByMethod(Collection $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $key = $row['method'];
            $totals[$key] = [
                'in' => round((float) (($totals[$key]['in'] ?? 0) + $row['amount_in']), 2),
                'out' => round((float) (($totals[$key]['out'] ?? 0) + $row['amount_out']), 2),
            ];
        }

        return $totals;
    }

    private function occursAtForDate($localDate, ?Carbon $fallbackCreatedAt, int $ownerId): Carbon
    {
        $tz = ShopTime::timezone($ownerId);
        $local = Carbon::parse((string) $localDate, $tz)->startOfDay();

        if ($fallbackCreatedAt) {
            $fallbackLocal = ShopTime::local($fallbackCreatedAt, $ownerId);
            $local->setTime($fallbackLocal->hour, $fallbackLocal->minute, $fallbackLocal->second, $fallbackLocal->microsecond);
        }

        return $local->utc();
    }

    private function matchesMethodFilter(?string $method, string $filter): bool
    {
        $method = $this->normalizeMethod($method);

        return match ($filter) {
            'all' => true,
            'cash', 'cash_drawer' => $method === 'cash',
            default => $method === $this->normalizeMethod($filter),
        };
    }

    private function normalizeMethod(?string $method): string
    {
        return in_array($method, ['cash', 'card', 'transfer', 'check'], true) ? $method : 'cash';
    }

    private function classifyCustomerPayment(CustomerPayment $row): string
    {
        if ($row->kind) {
            return $row->kind;
        }

        $note = trim((string) ($row->note ?? ''));
        if ($note === 'Initial balance') {
            return 'opening_balance';
        }

        if ($note !== '' && preg_match('/^Bill #\d+ created as debt$/', $note)) {
            return 'bill_charge';
        }

        return (float) $row->amount > 0 ? 'payment' : 'adjustment';
    }

    private function classifySupplierPayment(SupplierPayment $payment): string
    {
        if ($payment->kind) {
            return $payment->kind;
        }

        $note = trim((string) ($payment->note ?? ''));
        if ($note === 'Initial balance') {
            return 'opening_balance';
        }

        return (float) $payment->amount < 0 ? 'refund' : 'payment';
    }

    private function row(
        string $category,
        ?string $method,
        Carbon $occurredAt,
        int $sortId,
        string $documentType,
        string $label,
        ?string $actorName,
        float $signedAmount,
        float $amountIn,
        float $amountOut,
        array $document = [],
    ): array {
        return [
            'uid' => $documentType . ':' . $sortId,
            'category' => $category,
            'method' => $this->normalizeMethod($method),
            'occurred_at' => $occurredAt->copy(),
            'sort_id' => $sortId,
            'document_type' => $documentType,
            'document_label' => $label,
            'document' => $document,
            'actor_name' => $actorName,
            'signed_amount' => round($signedAmount, 2),
            'amount_in' => round($amountIn, 2),
            'amount_out' => round($amountOut, 2),
        ];
    }
}
