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
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Make sure there are no nulls before altering
                DB::table($tableName)->whereNull('user_id')->update(['user_id' => 2]); // fallback to admin or another user

                // Change to NOT NULL
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });

            // Add FK constraint if missing (skip SQLite because of alter table limits)
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table($tableName, function (Blueprint $table) {
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
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }
};
