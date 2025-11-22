<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string> */
    protected array $tenantTables = [
        'products',
        'customers',
        'bills',
        'customer_payments',
        'batches',
    ];

    public function up(): void
    {
        foreach ($this->tenantTables as $tableName) {
            // 1) Add nullable user_id + index if missing
            if (!Schema::hasColumn($tableName, 'user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                });
            }

            // 2) Add FK when the driver supports it (SQLite ALTER TABLE has limits)
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table($tableName, function (Blueprint $table) {
                    // Guard against duplicate FK creation on re-run
                    try {
                        $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                    } catch (\Throwable $e) {
                        // ignore if already exists
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tenantTables as $tableName) {
            if (Schema::hasColumn($tableName, 'user_id')) {
                // Drop FK if present (skip for SQLite)
                if (DB::getDriverName() !== 'sqlite') {
                    try {
                        Schema::table($tableName, function (Blueprint $table) {
                            $table->dropForeign(['user_id']);
                        });
                    } catch (\Throwable $e) {
                        // ignore if it wasn't created
                    }
                }

                // Drop index + column
                Schema::table($tableName, function (Blueprint $table) {
                    try { $table->dropIndex(['user_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('user_id');
                });
            }
        }
    }
};