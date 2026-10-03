<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $duplicates = DB::table('suppliers')
                ->whereNotNull('system_key')
                ->select('user_id', 'system_key', DB::raw('COUNT(*) as total'))
                ->groupBy('user_id', 'system_key')
                ->having('total', '>', 1)
                ->get();

            foreach ($duplicates as $group) {
                $suppliers = DB::table('suppliers')
                    ->where('user_id', $group->user_id)
                    ->where('system_key', $group->system_key)
                    ->orderBy('id')
                    ->get(['id']);

                $keepId = (int) $suppliers->first()->id;
                $duplicateIds = $suppliers->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();

                if (! $duplicateIds) {
                    continue;
                }

                DB::transaction(function () use ($keepId, $duplicateIds) {
                    $balanceDelta = (float) (DB::table('suppliers')->whereIn('id', $duplicateIds)->sum('balance') ?? 0);

                    DB::table('purchase_bills')->whereIn('supplier_id', $duplicateIds)->update(['supplier_id' => $keepId]);
                    DB::table('supplier_payments')->whereIn('supplier_id', $duplicateIds)->update(['supplier_id' => $keepId]);

                    if (Schema::hasTable('product_imeis') && Schema::hasColumn('product_imeis', 'supplier_id')) {
                        DB::table('product_imeis')->whereIn('supplier_id', $duplicateIds)->update(['supplier_id' => $keepId]);
                    }

                    if ($balanceDelta != 0.0) {
                        DB::table('suppliers')->where('id', $keepId)->increment('balance', $balanceDelta);
                    }

                    DB::table('suppliers')->whereIn('id', $duplicateIds)->delete();
                });
            }
        } catch (\Throwable $e) {
            Log::warning('walk-in supplier de-duplication skipped: ' . $e->getMessage());
        }

        if (! Schema::hasIndex('suppliers', 'suppliers_user_id_system_key_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->unique(['user_id', 'system_key']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('suppliers', 'suppliers_user_id_system_key_unique')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropUnique('suppliers_user_id_system_key_unique');
            });
        }
    }
};
