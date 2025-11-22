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
                DB::table($tableName)->whereNull('user_id')->update(['user_id' => 1]); // fallback to first user

                // Change to NOT NULL
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });

            // Only add FK constraint if it doesn't exist and we're not using SQLite
            if (DB::getDriverName() !== 'sqlite') {
                $foreignKeyName = $tableName . '_user_id_foreign';
                
                // Check if foreign key already exists
                $foreignKeyExists = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ? 
                    AND CONSTRAINT_NAME = ?
                ", [$tableName, $foreignKeyName]);

                if (empty($foreignKeyExists)) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                    });
                }
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