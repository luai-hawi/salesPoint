<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place that should write customer ledger rows (customer_payments) and move customers.balance.
 *
 * Conventions (unchanged from the original application):
 *   customers.balance = sum of customer_payments.amount
 *   negative amount   = the customer owes us (a bill charged on account)
 *   positive amount   = the customer paid us
 *
 * Rows now carry an explicit link to their bill (bill_id) and a kind:
 *   bill_charge | bill_payment | payment | adjustment | opening_balance
 * Rows saved before the link existed are still found through their legacy note ("Bill #12 created as debt").
 */
class CustomerLedger
{
    public const LEGACY_CHARGE_NOTE = 'Bill #%d created as debt';

    public const KIND_BILL_CHARGE = 'bill_charge';
    public const KIND_BILL_PAYMENT = 'bill_payment';
    public const KIND_PAYMENT = 'payment';
    public const KIND_ADJUSTMENT = 'adjustment';
    public const KIND_OPENING = 'opening_balance';

    private const LEGACY_BILL_PAYMENT_NOTE_PATTERNS = [
        '/^Counter payment for bill #\s*(\d+)$/i',
        '/^Installment initial payment for bill #\s*(\d+)$/i',
        '/^Payment for bill #\s*(\d+)$/i',
        '/^دفعة عند البيع للفاتورة #\s*(\d+)$/u',
        '/^دفعة أولى تقسيط للفاتورة #\s*(\d+)$/u',
        '/^دفعة لفاتورة رقم #\s*(\d+)$/u',
    ];

    /**
     * The row that records a bill's total as money owed by the customer (null for plain cash sales).
     */
    public static function chargeRow(Bill $bill): ?CustomerPayment
    {
        $row = self::rows($bill->user_id)
            ->where('bill_id', $bill->id)
            ->where('kind', self::KIND_BILL_CHARGE)
            ->first();

        if ($row || ! $bill->customer_id) {
            return $row;
        }

        // Legacy rows saved before bill_id existed.
        return self::rows($bill->user_id)
            ->where('customer_id', $bill->customer_id)
            ->whereNull('bill_id')
            ->where('note', sprintf(self::LEGACY_CHARGE_NOTE, $bill->id))
            ->first();
    }

    /**
     * Charge the bill total to its customer's account. Idempotent: a bill is only charged once.
     */
    public static function chargeBill(Bill $bill): ?CustomerPayment
    {
        $total = round((float) $bill->total_price, 2);
        if (! $bill->customer_id || $total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($bill, $total) {
            $lockedBill = Bill::withoutGlobalScopes()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $existing = self::chargeRow($lockedBill);
            if ($existing) {
                return $existing;
            }

            return self::createRow(
                self::customerFor($lockedBill, true),
                -$total,
                'cash',
                self::KIND_BILL_CHARGE,
                $lockedBill,
                sprintf(self::LEGACY_CHARGE_NOTE, $lockedBill->id),
                $lockedBill->created_at,
            );
        });
    }

    /**
     * Money the customer handed over for this bill (at the counter or later).
     */
    public static function receiveForBill(
        Bill $bill,
        float $amount,
        string $method = 'cash',
        ?string $note = null,
        ?CarbonInterface $at = null,
        ?string $clientUuid = null,
    ): CustomerPayment {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('A received amount must be greater than zero.');
        }

        return DB::transaction(function () use ($bill, $amount, $method, $note, $at, $clientUuid) {
            $lockedBill = Bill::withoutGlobalScopes()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $customer = self::customerFor($lockedBill, true);
            $summary = self::billSummary($lockedBill, true);

            if ($amount > round((float) $summary['due'], 2)) {
                throw ValidationException::withMessages([
                    'amount' => __('receivables.validation.bill_payment_exceeds_due'),
                ]);
            }

            return self::createRow($customer, $amount, $method, self::KIND_BILL_PAYMENT, $lockedBill, $note, $at, $clientUuid);
        });
    }

    /**
     * Record money received from a customer. $bill links it to a specific bill (optional).
     */
    public static function receive(
        Customer $customer,
        float $amount,
        string $method = 'cash',
        ?string $note = null,
        ?CarbonInterface $at = null,
        ?Bill $bill = null,
        string $kind = self::KIND_PAYMENT,
        ?string $clientUuid = null,
    ): CustomerPayment {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('A received amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $amount, $method, $kind, $bill, $note, $at, $clientUuid) {
            $lockedCustomer = Customer::withoutGlobalScopes()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            return self::createRow($lockedCustomer, $amount, $method, $kind, $bill, $note, $at, $clientUuid);
        });
    }

    /**
     * Free-form ledger entry (positive = customer paid, negative = customer owes more).
     */
    public static function adjust(
        Customer $customer,
        float $signedAmount,
        string $method = 'cash',
        ?string $note = null,
        ?CarbonInterface $at = null,
        string $kind = self::KIND_ADJUSTMENT,
        ?string $clientUuid = null,
    ): CustomerPayment {
        return DB::transaction(function () use ($customer, $signedAmount, $method, $note, $at, $kind, $clientUuid) {
            $lockedCustomer = Customer::withoutGlobalScopes()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            return self::createRow($lockedCustomer, round($signedAmount, 2), $method, $kind, null, $note, $at, $clientUuid);
        });
    }

    /**
     * After a bill total changed: bring its charge row (and the balance) in line with the new total.
     */
    public static function syncBillCharge(Bill $bill): void
    {
        if (! $bill->customer_id) {
            return;
        }

        DB::transaction(function () use ($bill) {
            $newTotal = max(0, round((float) $bill->total_price, 2));
            $row = self::chargeRow($bill);

            if (! $row) {
                self::chargeBill($bill);

                return;
            }

            $oldTotal = abs((float) $row->amount);
            $delta = round($newTotal - $oldTotal, 2);

            $row->amount = -$newTotal;
            $row->bill_id = $bill->id;
            $row->kind = self::KIND_BILL_CHARGE;
            $row->save();

            if ($delta != 0) {
                self::moveBalance(self::customerFor($bill), -$delta);
            }
        });
    }

    /**
     * Delete every ledger row of a bill (charge and payments) and give the balance back.
     * Returns the signed sum that was removed (e.g. -40 when 100 was charged and 60 paid).
     */
    public static function removeBillRows(Bill $bill): float
    {
        return DB::transaction(function () use ($bill) {
            $lockedBill = Bill::withoutGlobalScopes()
                ->whereKey($bill->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rows = self::rows($lockedBill->user_id)
                ->where(function ($query) use ($lockedBill) {
                    $query->where('bill_id', $lockedBill->id);

                    if ($lockedBill->customer_id) {
                        $query->orWhere(function ($legacy) use ($lockedBill) {
                            $legacy->whereNull('bill_id')
                                ->where('customer_id', $lockedBill->customer_id);
                        });
                    }
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (CustomerPayment $row) => (int) $row->bill_id === (int) $lockedBill->id
                    || self::legacyBillIdFromNote($row->note) === (int) $lockedBill->id)
                ->values();

            if ($rows->isEmpty()) {
                return 0.0;
            }

            $customer = $lockedBill->customer_id ? self::customerFor($lockedBill, true) : null;
            $sum = round((float) $rows->sum('amount'), 2);

            self::rows($lockedBill->user_id)->whereIn('id', $rows->pluck('id'))->delete();

            if ($customer && $sum != 0) {
                self::moveBalance($customer, -$sum);
            }

            return $sum;
        });
    }

    /**
     * Total money received for one bill (rows linked to it with a positive amount).
     */
    public static function billPaid(Bill $bill): float
    {
        return round((float) self::rows($bill->user_id)
            ->where('bill_id', $bill->id)
            ->where('amount', '>', 0)
            ->sum('amount'), 2);
    }

    /**
     * @return array{total: float, paid: float, due: float, status: string}
     *   status: cash (no customer) | paid | partial | unpaid
     */
    public static function billSummary(Bill $bill, bool $lock = false): array
    {
        $total = round((float) $bill->total_price, 2);

        if (! $bill->customer_id) {
            return ['total' => $total, 'paid' => $total, 'due' => 0.0, 'status' => 'cash'];
        }

        $customer = self::customerFor($bill, $lock);
        $summaries = self::allocatedBillSummaries($customer, $lock);

        return $summaries[$bill->id] ?? ['total' => $total, 'paid' => $total, 'due' => 0.0, 'status' => 'cash'];
    }

    /**
     * Derive the effective row kind for legacy rows that predate explicit kinds/links.
     */
    public static function kindForRow(CustomerPayment $row): string
    {
        if ($row->kind) {
            return $row->kind;
        }

        if (self::legacyChargeBillIdFromNote($row->note) !== null) {
            return self::KIND_BILL_CHARGE;
        }

        if ((string) $row->note === 'Initial balance') {
            return self::KIND_OPENING;
        }

        if (self::legacyBillPaymentBillIdFromNote($row->note) !== null) {
            return self::KIND_BILL_PAYMENT;
        }

        return (float) $row->amount >= 0 ? self::KIND_PAYMENT : self::KIND_ADJUSTMENT;
    }

    /**
     * @param  iterable<int, Bill>  $bills
     * @return array<int, array{total: float, paid: float, due: float, status: string}>
     */
    public static function summariesForBills(iterable $bills): array
    {
        $collection = collect($bills)->filter(fn ($bill) => $bill instanceof Bill)->values();
        if ($collection->isEmpty()) {
            return [];
        }

        $summaries = [];
        $grouped = $collection->groupBy(function (Bill $bill) {
            return $bill->customer_id
                ? $bill->user_id . ':' . $bill->customer_id
                : 'cash:' . $bill->id;
        });

        foreach ($grouped as $groupKey => $groupBills) {
            /** @var \Illuminate\Support\Collection<int, Bill> $groupBills */
            $first = $groupBills->first();
            if (! $first || ! $first->customer_id) {
                foreach ($groupBills as $bill) {
                    $total = round((float) $bill->total_price, 2);
                    $summaries[$bill->id] = ['total' => $total, 'paid' => $total, 'due' => 0.0, 'status' => 'cash'];
                }

                continue;
            }

            $customer = new Customer();
            $customer->id = $first->customer_id;
            $customer->user_id = $first->user_id;

            $groupSummaries = self::allocatedBillSummaries($customer, false);
            foreach ($groupBills as $bill) {
                $total = round((float) $bill->total_price, 2);
                $summaries[$bill->id] = $groupSummaries[$bill->id]
                    ?? ['total' => $total, 'paid' => $total, 'due' => 0.0, 'status' => 'cash'];
            }
        }

        return $summaries;
    }

    /**
     * Update one ledger row and keep customers.balance aligned by the delta.
     */
    public static function updateRow(CustomerPayment $row, float $signedAmount, string $method, ?string $note = null): CustomerPayment
    {
        $signedAmount = round($signedAmount, 2);
        $kind = self::kindForRow($row);

        if ($kind === self::KIND_BILL_CHARGE) {
            throw ValidationException::withMessages([
                'amount' => __('receivables.validation.bill_charge_cannot_be_edited'),
            ]);
        }

        return DB::transaction(function () use ($row, $signedAmount, $method, $note, $kind) {
            $lockedRow = CustomerPayment::withoutGlobalScopes()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::withoutGlobalScopes()->whereKey($lockedRow->customer_id)->lockForUpdate()->firstOrFail();
            $previous = round((float) $lockedRow->amount, 2);
            $delta = round($signedAmount - $previous, 2);

            $lockedRow->amount = $signedAmount;
            $lockedRow->type = in_array($method, ['cash', 'card', 'transfer', 'check'], true) ? $method : 'cash';
            $lockedRow->note = $note;
            $lockedRow->kind = $kind;
            $lockedRow->save();

            self::moveBalance($customer, $delta);

            return $lockedRow;
        });
    }

    /**
     * Delete one free-standing payment/adjustment row and restore the customer balance.
     */
    public static function deleteRow(CustomerPayment $row): void
    {
        $kind = self::kindForRow($row);
        if ($kind === self::KIND_BILL_CHARGE) {
            throw ValidationException::withMessages([
                'payment' => __('receivables.validation.bill_charge_cannot_be_deleted'),
            ]);
        }

        DB::transaction(function () use ($row) {
            $lockedRow = CustomerPayment::withoutGlobalScopes()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::withoutGlobalScopes()->whereKey($lockedRow->customer_id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $lockedRow->amount, 2);

            $lockedRow->delete();
            self::moveBalance($customer, -$amount);
        });
    }

    /**
     * The customer's bills that still carry debt, newest first.
     *
     * The customer's real debt (-balance) is spread over the bills newest-first, so the per-bill amounts
     * always add up to what the customer actually owes, even for bills saved before payments were linked.
     *
     * @return Collection<int, array{bill: Bill, total: float, paid: float, due: float}>
     */
    public static function openBills(Customer $customer): Collection
    {
        if (! $customer->user_id || ! $customer->id) {
            return collect();
        }

        $summaries = self::allocatedBillSummaries($customer, false);
        $bills = Bill::withoutGlobalScopes()
            ->where('user_id', $customer->user_id)
            ->where('customer_id', $customer->id)
            ->where('total_price', '>', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $bills->map(function (Bill $bill) use ($summaries) {
            $summary = $summaries[$bill->id] ?? null;
            if (! $summary || $summary['due'] <= 0 || $summary['status'] === 'cash') {
                return null;
            }

            return ['bill' => $bill, 'total' => $summary['total'], 'paid' => $summary['paid'], 'due' => $summary['due']];
        })->filter()->values();
    }

    /**
     * Integrity tool: recompute customers.balance from the ledger rows.
     */
    public static function recalcBalance(Customer $customer): float
    {
        if (! $customer->user_id || ! $customer->id) {
            $customer->balance = 0.0;

            return 0.0;
        }

        $sum = round((float) self::rows($customer->user_id)->where('customer_id', $customer->id)->sum('amount'), 2);

        Customer::withoutGlobalScopes()->whereKey($customer->id)->update(['balance' => $sum]);
        $customer->balance = $sum;

        return $sum;
    }

    // ── internals ──────────────────────────────────────────────────────

    private static function createRow(
        Customer $customer,
        float $signedAmount,
        string $method,
        string $kind,
        ?Bill $bill,
        ?string $note,
        ?CarbonInterface $at = null,
        ?string $clientUuid = null,
    ): CustomerPayment {
        $row = new CustomerPayment([
            'customer_id' => $customer->id,
            'amount' => $signedAmount,
            'type' => in_array($method, ['cash', 'card', 'transfer', 'check'], true) ? $method : 'cash',
            'note' => $note,
            'user_id' => $customer->user_id,
            'client_uuid' => $clientUuid,
        ]);
        $row->bill_id = $bill?->id;
        $row->kind = $kind;

        if ($at) {
            $row->created_at = $at;
        }

        $row->save();

        self::moveBalance($customer, $signedAmount);

        return $row;
    }

    private static function moveBalance(Customer $customer, float $delta): void
    {
        if ($delta == 0.0) {
            return;
        }

        $customer->increment('balance', $delta);
    }

    private static function customerFor(Bill $bill, bool $lock = false): Customer
    {
        $query = Customer::withoutGlobalScopes()
            ->where('user_id', $bill->user_id)
            ->whereKey($bill->customer_id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private static function rows(int|string|null $ownerId)
    {
        if ($ownerId === null || $ownerId === '') {
            return CustomerPayment::withoutGlobalScopes()->whereRaw('1 = 0');
        }

        return CustomerPayment::withoutGlobalScopes()->where('user_id', $ownerId);
    }

    public static function legacyBillIdFromNote(?string $note): ?int
    {
        return self::legacyChargeBillIdFromNote($note)
            ?? self::legacyBillPaymentBillIdFromNote($note);
    }

    /**
     * @return array<int, array{total: float, paid: float, due: float, status: string}>
     */
    private static function allocatedBillSummaries(Customer $customer, bool $lock = false): array
    {
        $billsQuery = Bill::withoutGlobalScopes()
            ->where('user_id', $customer->user_id)
            ->where('customer_id', $customer->id)
            ->where('total_price', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id');

        $rowsQuery = self::rows($customer->user_id)
            ->where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($lock) {
            $billsQuery->lockForUpdate();
            $rowsQuery->lockForUpdate();
        }

        $bills = $billsQuery->get()->keyBy('id');
        $rows = $rowsQuery->get();
        $summaries = [];
        $charged = [];

        foreach ($bills as $bill) {
            $summaries[$bill->id] = [
                'total' => round((float) $bill->total_price, 2),
                'paid' => 0.0,
                'due' => round((float) $bill->total_price, 2),
                'status' => 'cash',
            ];
        }

        $unlinkedCredits = 0.0;
        foreach ($rows as $row) {
            $kind = self::kindForRow($row);
            $billId = $row->bill_id ? (int) $row->bill_id : self::legacyBillIdFromNote($row->note);

            if ($billId !== null && ! isset($summaries[$billId])) {
                $billId = null;
            }

            if ($kind === self::KIND_BILL_CHARGE && $billId && isset($summaries[$billId])) {
                $charged[$billId] = true;
                $summaries[$billId]['status'] = 'unpaid';
                continue;
            }

            if ((float) $row->amount > 0) {
                if ($billId && isset($summaries[$billId])) {
                    $summaries[$billId]['paid'] += round((float) $row->amount, 2);
                } else {
                    $unlinkedCredits += round((float) $row->amount, 2);
                }
            }
        }

        foreach ($bills as $billId => $bill) {
            if (! ($charged[$billId] ?? false)) {
                $summaries[$billId] = [
                    'total' => round((float) $bill->total_price, 2),
                    'paid' => round((float) $bill->total_price, 2),
                    'due' => 0.0,
                    'status' => 'cash',
                ];
                continue;
            }

            $summaries[$billId]['paid'] = min($summaries[$billId]['total'], round($summaries[$billId]['paid'], 2));
            $summaries[$billId]['due'] = round(max(0, $summaries[$billId]['total'] - $summaries[$billId]['paid']), 2);
        }

        foreach ($bills as $billId => $bill) {
            if (! ($charged[$billId] ?? false) || $unlinkedCredits <= 0) {
                continue;
            }

            $alloc = min($summaries[$billId]['due'], $unlinkedCredits);
            if ($alloc <= 0) {
                continue;
            }

            $summaries[$billId]['paid'] = round($summaries[$billId]['paid'] + $alloc, 2);
            $summaries[$billId]['due'] = round(max(0, $summaries[$billId]['total'] - $summaries[$billId]['paid']), 2);
            $unlinkedCredits = round($unlinkedCredits - $alloc, 2);
        }

        foreach ($summaries as $billId => $summary) {
            if ($summary['status'] === 'cash') {
                continue;
            }

            $summaries[$billId]['status'] = $summary['due'] <= 0 ? 'paid' : ($summary['paid'] > 0 ? 'partial' : 'unpaid');
        }

        return $summaries;
    }

    private static function legacyChargeBillIdFromNote(?string $note): ?int
    {
        if (preg_match('/^Bill #\s*(\d+) created as debt$/i', trim((string) $note), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function legacyBillPaymentBillIdFromNote(?string $note): ?int
    {
        $note = trim((string) $note);
        if ($note === '') {
            return null;
        }

        foreach (self::LEGACY_BILL_PAYMENT_NOTE_PATTERNS as $pattern) {
            if (preg_match($pattern, $note, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
