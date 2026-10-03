<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting links:
 *  - bills.payment_method  how the sale was paid at the counter (null = cash / not recorded)
 *  - bills.client_uuid     idempotency key sent by offline clients so a retried sync never duplicates a bill
 *  - purchase_bills.source marks bills created automatically (e.g. "stock_intake") instead of typed in
 *  - supplier_payments.purchase_bill_id / kind  link a payment to the purchase bill it settles
 *  - suppliers.system_key  marks the built-in "cash purchases" supplier ("walk_in")
 * All columns are nullable, so every row saved earlier stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'payment_method')) {
                $table->string('payment_method', 20)->nullable();
            }
            if (! Schema::hasColumn('bills', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable();
            }
        });

        if (! Schema::hasIndex('bills', 'bills_user_id_client_uuid_unique')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->unique(['user_id', 'client_uuid']);
            });
        }

        Schema::table('purchase_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bills', 'source')) {
                $table->string('source', 30)->nullable();
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_payments', 'purchase_bill_id')) {
                $table->unsignedBigInteger('purchase_bill_id')->nullable();
            }
            if (! Schema::hasColumn('supplier_payments', 'kind')) {
                $table->string('kind', 30)->nullable();
            }
        });

        if (! Schema::hasIndex('supplier_payments', 'supplier_payments_purchase_bill_id_index')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->index('purchase_bill_id');
            });
        }

        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'system_key')) {
                $table->string('system_key', 30)->nullable();
            }
        });

        try {
            DB::table('supplier_payments')
                ->whereNull('kind')
                ->select('id', 'amount', 'note')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('supplier_payments')
                            ->where('id', $row->id)
                            ->whereNull('kind')
                            ->update([
                                'kind' => $row->note === 'Initial balance'
                                    ? 'opening_balance'
                                    : ((float) $row->amount < 0 ? 'refund' : 'payment'),
                            ]);
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('supplier_payments backfill skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('supplier_payments', 'supplier_payments_purchase_bill_id_index')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->dropIndex('supplier_payments_purchase_bill_id_index');
            });
        }

        if (Schema::hasIndex('bills', 'bills_user_id_client_uuid_unique')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropUnique('bills_user_id_client_uuid_unique');
            });
        }
        // Non-destructive rollback: keep data-bearing columns in place to avoid erasing saved links/history.
    }
};
