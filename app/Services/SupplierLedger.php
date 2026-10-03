<?php

namespace App\Services;

use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

/**
 * The only place that should write supplier ledger rows (supplier_payments)
 * and move suppliers.balance.
 *
 * Conventions:
 *   suppliers.balance = opening balances + purchase bills - non-opening supplier payments
 *   positive balance  = we owe the supplier
 *   negative balance  = the supplier owes us / we hold supplier credit
 */
class SupplierLedger
{
    /** @var array<string, array<int, array{total: float, paid: float, raw_paid: float, due: float, overpaid: float, status: string}>> */
    private static array $billCoverageCache = [];

    public const LEGACY_OPENING_NOTE = 'Initial balance';

    public const KIND_PAYMENT = 'payment';
    public const KIND_BILL_PAYMENT = 'bill_payment';
    public const KIND_OPENING = 'opening_balance';
    public const KIND_REFUND = 'refund';

    public const WALK_IN_KEY = 'walk_in';

    /**
     * A purchase bill increases what we owe.
     */
    public static function charge(Supplier $supplier, float $amount): void
    {
        self::forgetCoverageCache($supplier->user_id, $supplier->id);
        self::adjustBalance($supplier, round($amount, 2));
    }

    /**
     * Direct balance adjustment (positive = we owe more, negative = we owe less).
     */
    public static function adjustBalance(Supplier $supplier, float $delta): void
    {
        $delta = round($delta, 2);
        if ($delta == 0.0) {
            return;
        }

        $supplier->increment('balance', $delta);
        $supplier->balance = round((float) $supplier->balance + $delta, 2);
    }

    /**
     * Opening balance row saved when a supplier is created/imported with a prior balance.
     */
    public static function recordOpeningBalance(
        Supplier $supplier,
        float $signedAmount,
        ?string $note = null,
        ?CarbonInterface $date = null,
    ): ?SupplierPayment {
        $signedAmount = round($signedAmount, 2);
        if ($signedAmount == 0.0) {
            return null;
        }

        return DB::transaction(function () use ($supplier, $signedAmount, $note, $date) {
            self::forgetCoverageCache($supplier->user_id, $supplier->id);
            $payment = self::createRow(
                $supplier,
                $signedAmount,
                'cash',
                self::KIND_OPENING,
                null,
                $note ?: self::LEGACY_OPENING_NOTE,
                $date,
            );

            self::adjustBalance($supplier, $signedAmount);

            return $payment;
        });
    }

    /**
     * Record a supplier payment (positive = we paid them, negative = they refunded/paid us).
     * Pass $bill to link the payment to the purchase bill it settles.
     */
    public static function recordPayment(
        Supplier $supplier,
        float $signedAmount,
        string $method = 'cash',
        ?string $note = null,
        ?CarbonInterface $date = null,
        ?PurchaseBill $bill = null,
        ?string $kind = null,
    ): SupplierPayment {
        $signedAmount = round($signedAmount, 2);
        if ($signedAmount == 0.0) {
            throw new \InvalidArgumentException('A payment amount must not be zero.');
        }

        $kind ??= $bill
            ? self::KIND_BILL_PAYMENT
            : ($signedAmount > 0 ? self::KIND_PAYMENT : self::KIND_REFUND);

        return DB::transaction(function () use ($supplier, $signedAmount, $method, $note, $date, $bill, $kind) {
            self::forgetCoverageCache($supplier->user_id, $supplier->id);
            $payment = self::createRow($supplier, $signedAmount, $method, $kind, $bill, $note, $date);

            self::adjustBalance($supplier, -1 * $signedAmount);

            return $payment;
        });
    }

    /**
     * Backwards-compatible helper for ordinary positive supplier payments.
     */
    public static function pay(
        Supplier $supplier,
        float $amount,
        string $method = 'cash',
        ?string $note = null,
        ?CarbonInterface $date = null,
        ?PurchaseBill $bill = null,
        ?string $kind = null,
    ): SupplierPayment {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('A payment amount must be greater than zero.');
        }

        return self::recordPayment($supplier, $amount, $method, $note, $date, $bill, $kind);
    }

    /**
     * Update a supplier payment and keep suppliers.balance correct.
     */
    public static function updatePayment(
        SupplierPayment $payment,
        float $signedAmount,
        string $method = 'cash',
        ?string $note = null,
        ?CarbonInterface $date = null,
        ?PurchaseBill $bill = null,
        ?string $kind = null,
    ): SupplierPayment {
        $signedAmount = round($signedAmount, 2);
        if ($signedAmount == 0.0) {
            throw new \InvalidArgumentException('A payment amount must not be zero.');
        }

        return DB::transaction(function () use ($payment, $signedAmount, $method, $note, $date, $bill, $kind) {
            $supplier = self::supplierForPayment($payment);
            self::forgetCoverageCache($supplier->user_id, $supplier->id);
            $oldImpact = self::rowBalanceImpact($payment);

            $payment->amount = $signedAmount;
            $payment->type = self::normalizeMethod($method);
            $payment->note = $note;
            $payment->payment_date = ($date ?? $payment->payment_date)->toDateString();
            $payment->purchase_bill_id = $bill?->id;
            $payment->kind = $kind
                ?? ($bill ? self::KIND_BILL_PAYMENT : ($signedAmount > 0 ? self::KIND_PAYMENT : self::KIND_REFUND));
            $payment->save();

            $newImpact = self::rowBalanceImpact($payment);
            self::adjustBalance($supplier, round($newImpact - $oldImpact, 2));

            return $payment->fresh(['purchaseBill']);
        });
    }

    /**
     * Delete a supplier payment row and reverse its balance effect.
     */
    public static function deletePayment(SupplierPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $lockedPayment = SupplierPayment::withoutGlobalScopes()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                return;
            }

            $supplier = Supplier::withoutGlobalScopes()
                ->where('user_id', $lockedPayment->user_id)
                ->whereKey($lockedPayment->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            $impact = self::rowBalanceImpact($lockedPayment);
            $deleted = SupplierPayment::withoutGlobalScopes()->whereKey($lockedPayment->id)->delete();

            if ($deleted) {
                self::forgetCoverageCache($supplier->user_id, $supplier->id);
                self::adjustBalance($supplier, -1 * $impact);
            }
        });
    }

    /**
     * Total positive money paid against one purchase bill.
     */
    public static function billPaid(PurchaseBill $bill): float
    {
        $coverage = self::billCoverageMap($bill->user_id, $bill->supplier_id);

        return round((float) ($coverage[$bill->id]['raw_paid'] ?? 0), 2);
    }

    /**
     * @return array{total: float, paid: float, raw_paid: float, due: float, overpaid: float, status: string}
     */
    public static function billSummary(PurchaseBill $bill): array
    {
        return self::billCoverageMap($bill->user_id, $bill->supplier_id)[$bill->id]
            ?? [
                'total' => round((float) $bill->total_amount, 2),
                'paid' => 0.0,
                'raw_paid' => 0.0,
                'due' => round((float) $bill->total_amount, 2),
                'overpaid' => 0.0,
                'status' => 'unpaid',
            ];
    }

    /**
     * @param  iterable<int, PurchaseBill>  $bills
     * @return array<int, array{total: float, paid: float, raw_paid: float, due: float, overpaid: float, status: string}>
     */
    public static function summariesForBills(iterable $bills): array
    {
        $collection = collect($bills)->filter(fn ($bill) => $bill instanceof PurchaseBill)->values();
        if ($collection->isEmpty()) {
            return [];
        }

        $summaries = [];
        $groups = $collection->groupBy(fn (PurchaseBill $bill) => $bill->user_id . ':' . $bill->supplier_id);

        foreach ($groups as $groupBills) {
            /** @var \Illuminate\Support\Collection<int, PurchaseBill> $groupBills */
            $first = $groupBills->first();
            if (! $first) {
                continue;
            }

            $coverage = self::billCoverageMap($first->user_id, $first->supplier_id);
            foreach ($groupBills as $bill) {
                $summaries[$bill->id] = $coverage[$bill->id]
                    ?? [
                        'total' => round((float) $bill->total_amount, 2),
                        'paid' => 0.0,
                        'raw_paid' => 0.0,
                        'due' => round((float) $bill->total_amount, 2),
                        'overpaid' => 0.0,
                        'status' => 'unpaid',
                    ];
            }
        }

        return $summaries;
    }

    /**
     * The supplier's purchase bills that still make up the current debt, newest first.
     *
     * @return Collection<int, array{bill: PurchaseBill, total: float, paid: float, due: float, overpaid: float, status: string}>
     */
    public static function openBills(Supplier $supplier): Collection
    {
        $outstanding = max(0, round(self::expectedBillRelatedBalance($supplier), 2));
        $open = collect();

        if ($outstanding <= 0) {
            return $open;
        }

        $bills = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $supplier->user_id)
            ->where('supplier_id', $supplier->id)
            ->where('total_amount', '>', 0)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $paidByBill = self::rows($supplier->user_id)
            ->where('supplier_id', $supplier->id)
            ->whereIn('purchase_bill_id', $bills->pluck('id'))
            ->where('amount', '>', 0)
            ->selectRaw('purchase_bill_id, SUM(amount) as paid')
            ->groupBy('purchase_bill_id')
            ->pluck('paid', 'purchase_bill_id');

        foreach ($bills as $bill) {
            if ($outstanding <= 0) {
                break;
            }

            $summary = self::billSummary($bill);
            $linkedPaid = round((float) ($paidByBill[$bill->id] ?? 0), 2);
            $due = min(max(0, round($summary['total'] - min($summary['total'], $linkedPaid), 2)), $outstanding);

            if ($due > 0) {
                $open->push([
                    'bill' => $bill,
                    'total' => $summary['total'],
                    'paid' => min($summary['total'], $linkedPaid),
                    'due' => $due,
                    'overpaid' => $summary['overpaid'],
                    'status' => $due <= 0 ? 'paid' : (min($summary['total'], $linkedPaid) > 0 ? 'partial' : 'unpaid'),
                ]);
                $outstanding = round($outstanding - $due, 2);
            }
        }

        return $open;
    }

    /**
     * Recompute the supplier balance from opening rows, purchase bills, and non-opening payments.
     */
    public static function recalcBalance(Supplier $supplier): float
    {
        $balance = self::expectedBalance($supplier);

        Supplier::withoutGlobalScopes()->whereKey($supplier->id)->update(['balance' => $balance]);
        $supplier->balance = $balance;
        self::forgetCoverageCache($supplier->user_id, $supplier->id);

        return $balance;
    }

    public static function expectedBalance(Supplier $supplier): float
    {
        $ownerId = $supplier->user_id;
        $supplierId = $supplier->id;

        $purchaseTotal = round((float) PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplierId)
            ->sum('total_amount'), 2);

        $opening = 0.0;
        $paymentTotal = 0.0;

        foreach (self::rows($ownerId)->where('supplier_id', $supplierId)->get() as $payment) {
            $amount = round((float) $payment->amount, 2);

            if (self::kindForRow($payment) === self::KIND_OPENING) {
                $opening += $amount;

                continue;
            }

            $paymentTotal += $amount;
        }

        return round($opening + $purchaseTotal - $paymentTotal, 2);
    }

    /**
     * The shop's built-in supplier for purchases from vendors that are not registered
     * (stock bought and paid in cash). Created on first use.
     */
    public static function walkInSupplier(int $ownerId): Supplier
    {
        return DB::transaction(function () use ($ownerId) {
            $existing = Supplier::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->where('system_key', self::WALK_IN_KEY)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                $supplier = new Supplier([
                    'name' => 'مشتريات نقدية - Cash purchases',
                    'balance' => 0,
                    'user_id' => $ownerId,
                    'system_key' => self::WALK_IN_KEY,
                ]);
                $supplier->save();

                return $supplier;
            } catch (QueryException $e) {
                if (! self::isDuplicateSystemKeyException($e)) {
                    throw $e;
                }

                return Supplier::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->where('system_key', self::WALK_IN_KEY)
                    ->orderBy('id')
                    ->firstOrFail();
            }
        });
    }

    public static function kindForRow(SupplierPayment $payment): string
    {
        $kind = $payment->kind;

        if (is_string($kind) && $kind !== '') {
            return $kind;
        }

        if ($payment->note === self::LEGACY_OPENING_NOTE) {
            return self::KIND_OPENING;
        }

        if ($payment->purchase_bill_id) {
            return self::KIND_BILL_PAYMENT;
        }

        return (float) $payment->amount < 0 ? self::KIND_REFUND : self::KIND_PAYMENT;
    }

    private static function createRow(
        Supplier $supplier,
        float $signedAmount,
        string $method,
        string $kind,
        ?PurchaseBill $bill,
        ?string $note,
        ?CarbonInterface $date = null,
    ): SupplierPayment {
        $row = new SupplierPayment([
            'supplier_id' => $supplier->id,
            'purchase_bill_id' => $bill?->id,
            'amount' => round($signedAmount, 2),
            'type' => self::normalizeMethod($method),
            'kind' => $kind,
            'note' => $note,
            'user_id' => $supplier->user_id,
            'payment_date' => ($date ?? now())->toDateString(),
        ]);
        $row->save();

        return $row;
    }

    private static function rowBalanceImpact(SupplierPayment $payment): float
    {
        $amount = round((float) $payment->amount, 2);

        if (self::kindForRow($payment) === self::KIND_OPENING) {
            return $amount;
        }

        return round(-1 * $amount, 2);
    }

    private static function expectedBillRelatedBalance(Supplier $supplier): float
    {
        $purchaseTotal = round((float) PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $supplier->user_id)
            ->where('supplier_id', $supplier->id)
            ->sum('total_amount'), 2);

        $paymentTotal = 0.0;
        foreach (self::rows($supplier->user_id)->where('supplier_id', $supplier->id)->get() as $payment) {
            if (self::kindForRow($payment) === self::KIND_OPENING) {
                continue;
            }

            $paymentTotal += round((float) $payment->amount, 2);
        }

        return round($purchaseTotal - $paymentTotal, 2);
    }

    /**
     * @return array<int, array{total: float, paid: float, raw_paid: float, due: float, overpaid: float, status: string}>
     */
    private static function billCoverageMap(int|string $ownerId, int|string|null $supplierId): array
    {
        if (! $supplierId) {
            return [];
        }

        $cacheKey = $ownerId . ':' . $supplierId;
        if (isset(self::$billCoverageCache[$cacheKey])) {
            return self::$billCoverageCache[$cacheKey];
        }

        $bills = PurchaseBill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('supplier_id', $supplierId)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get(['id', 'total_amount']);

        if ($bills->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($bills as $bill) {
            $map[$bill->id] = [
                'total' => round((float) $bill->total_amount, 2),
                'paid' => 0.0,
                'raw_paid' => 0.0,
                'due' => round((float) $bill->total_amount, 2),
                'overpaid' => 0.0,
                'status' => 'unpaid',
                'linked_paid' => 0.0,
            ];
        }

        $unlinkedPositive = 0.0;
        foreach (self::rows($ownerId)->where('supplier_id', $supplierId)->orderBy('payment_date')->orderBy('id')->get() as $payment) {
            if (self::kindForRow($payment) === self::KIND_OPENING) {
                continue;
            }

            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            if ($payment->purchase_bill_id && isset($map[$payment->purchase_bill_id])) {
                $map[$payment->purchase_bill_id]['linked_paid'] = round($map[$payment->purchase_bill_id]['linked_paid'] + $amount, 2);

                continue;
            }

            $unlinkedPositive = round($unlinkedPositive + $amount, 2);
        }

        foreach ($bills as $bill) {
            $id = $bill->id;
            $total = $map[$id]['total'];
            $linkedPaid = $map[$id]['linked_paid'];
            $linkedCounted = min($total, $linkedPaid);
            $legacyApplied = min(max(0, round($total - $linkedCounted, 2)), $unlinkedPositive);
            $rawPaid = round($linkedPaid + $legacyApplied, 2);
            $paid = min($total, $rawPaid);
            $due = round(max(0, $total - $paid), 2);

            $map[$id] = [
                'total' => $total,
                'paid' => $paid,
                'raw_paid' => $rawPaid,
                'due' => $due,
                'overpaid' => round(max(0, $linkedPaid - $total), 2),
                'status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            ];

            $unlinkedPositive = round($unlinkedPositive - $legacyApplied, 2);
        }

        return self::$billCoverageCache[$cacheKey] = $map;
    }

    private static function isDuplicateSystemKeyException(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, '1062');
    }

    private static function forgetCoverageCache(int|string $ownerId, int|string|null $supplierId): void
    {
        if ($supplierId === null) {
            return;
        }

        unset(self::$billCoverageCache[$ownerId . ':' . $supplierId]);
    }

    private static function supplierForPayment(SupplierPayment $payment): Supplier
    {
        return Supplier::withoutGlobalScopes()
            ->where('user_id', $payment->user_id)
            ->findOrFail($payment->supplier_id);
    }

    private static function normalizeMethod(string $method): string
    {
        return in_array($method, ['cash', 'card', 'transfer', 'check'], true) ? $method : 'cash';
    }

    private static function rows(int|string $ownerId)
    {
        return SupplierPayment::withoutGlobalScopes()->where('user_id', $ownerId);
    }
}
