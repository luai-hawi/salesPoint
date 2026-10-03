<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Links customer ledger rows to the bill they belong to.
 *
 * Until now a bill's debt row was found again by matching its note ("Bill #12 created as debt"),
 * which breaks as soon as the note is edited or translated. The new columns make the link explicit:
 *
 *   bill_id  - the bill this row belongs to (null for general account movements)
 *   kind     - what the row represents: bill_charge | bill_payment | payment | adjustment | opening_balance
 *
 * Existing rows are classified and linked here. Rows that cannot be linked safely stay NULL and the
 * application keeps its legacy note matching as a fallback, so nothing saved earlier is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_payments', 'bill_id')) {
                $table->unsignedBigInteger('bill_id')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('customer_payments', 'kind')) {
                $table->string('kind', 30)->nullable()->after('type');
            }
        });

        if (! Schema::hasIndex('customer_payments', 'customer_payments_bill_id_index')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->index('bill_id');
            });
        }

        try {
            $this->classifyExistingRows();
            $this->linkExistingBillCharges();
            $this->linkExistingBillPayments();
        } catch (\Throwable $e) {
            // Never block a deployment: the legacy note matching still works for unlinked rows.
            Log::warning('customer_payments backfill skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customer_payments', 'customer_payments_bill_id_index')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->dropIndex('customer_payments_bill_id_index');
            });
        }
    }

    private function classifyExistingRows(): void
    {
        $this->chunkedUpdate(fn ($query) => $query->whereNull('kind')->where('note', 'like', 'Bill #% created as debt'), ['kind' => 'bill_charge']);
        $this->chunkedUpdate(fn ($query) => $query->whereNull('kind')->where('note', 'Initial balance'), ['kind' => 'opening_balance']);
        $this->chunkedUpdate(fn ($query) => $query->whereNull('kind')->where('amount', '>', 0), ['kind' => 'payment']);
        $this->chunkedUpdate(fn ($query) => $query->whereNull('kind')->where('amount', '<', 0), ['kind' => 'adjustment']);
    }

    private function linkExistingBillCharges(): void
    {
        DB::table('customer_payments')
            ->where('kind', 'bill_charge')
            ->whereNull('bill_id')
            ->select('id', 'user_id', 'customer_id', 'note')
            ->chunkById(500, function ($rows) {
                $wanted = [];
                foreach ($rows as $row) {
                    if (preg_match('/^Bill #(\d+) created as debt$/', (string) $row->note, $m)) {
                        $wanted[$row->id] = (int) $m[1];
                    }
                }

                if (! $wanted) {
                    return;
                }

                $bills = DB::table('bills')->whereIn('id', array_unique($wanted))
                    ->get(['id', 'user_id', 'customer_id'])->keyBy('id');

                foreach ($rows as $row) {
                    $billId = $wanted[$row->id] ?? null;
                    $bill = $billId ? $bills->get($billId) : null;

                    if ($bill && (int) $bill->user_id === (int) $row->user_id && (int) $bill->customer_id === (int) $row->customer_id) {
                        DB::table('customer_payments')->where('id', $row->id)->update(['bill_id' => $bill->id]);
                    }
                }
            });
    }

    private function linkExistingBillPayments(): void
    {
        DB::table('customer_payments')
            ->where(function ($query) {
                $query->where('kind', 'payment')
                    ->orWhereNull('kind');
            })
            ->whereNull('bill_id')
            ->where('amount', '>', 0)
            ->whereNotNull('note')
            ->select('id', 'user_id', 'customer_id', 'note')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $wanted = [];
                foreach ($rows as $row) {
                    $billId = $this->legacyBillPaymentId($row->note);
                    if ($billId !== null) {
                        $wanted[$row->id] = $billId;
                    }
                }

                if (! $wanted) {
                    return;
                }

                $bills = DB::table('bills')
                    ->whereIn('id', array_unique($wanted))
                    ->get(['id', 'user_id', 'customer_id'])
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $billId = $wanted[$row->id] ?? null;
                    $bill = $billId ? $bills->get($billId) : null;

                    if ($bill && (int) $bill->user_id === (int) $row->user_id && (int) $bill->customer_id === (int) $row->customer_id) {
                        DB::table('customer_payments')
                            ->where('id', $row->id)
                            ->update([
                                'bill_id' => $bill->id,
                                'kind' => 'bill_payment',
                            ]);
                    }
                }
            });
    }

    private function chunkedUpdate(callable $scope, array $values): void
    {
        $base = DB::table('customer_payments')->select('id')->orderBy('id');
        $scope($base)->chunkById(500, function ($rows) use ($values) {
            DB::table('customer_payments')->whereIn('id', collect($rows)->pluck('id'))->update($values);
        });
    }

    private function legacyBillPaymentId(?string $note): ?int
    {
        $note = trim((string) $note);
        if ($note === '') {
            return null;
        }

        foreach ([
            '/^Counter payment for bill #\s*(\d+)$/i',
            '/^Installment initial payment for bill #\s*(\d+)$/i',
            '/^Payment for bill #\s*(\d+)$/i',
            '/^دفعة عند البيع للفاتورة #\s*(\d+)$/u',
            '/^دفعة أولى تقسيط للفاتورة #\s*(\d+)$/u',
            '/^دفعة لفاتورة رقم #\s*(\d+)$/u',
        ] as $pattern) {
            if (preg_match($pattern, $note, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }
};
