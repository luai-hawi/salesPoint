<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes on (user_id, <sort/filter column>) for all tenant tables.
 *
 * Why: queries like `WHERE user_id = X ORDER BY created_at DESC` currently do a
 * full scan of all rows belonging to owner X. A composite index lets the DB jump
 * straight to the owner slice and walk it in sorted order — effectively the same
 * performance as if each owner had their own table.
 */
return new class extends Migration
{
    private array $indexes = [
        // table            => [composite columns]
        'bills'             => ['user_id', 'created_at'],
        'products'          => ['user_id', 'is_active'],
        'customers'         => ['user_id', 'name'],
        'customer_payments' => ['user_id', 'created_at'],
        'batches'           => ['user_id', 'created_at'],
        'expenses'          => ['user_id', 'expense_date'],
        'suppliers'         => ['user_id', 'name'],
        'supplier_payments' => ['user_id', 'payment_date'],
        'purchase_bills'    => ['user_id', 'purchase_date'],
        'capital_entries'   => ['user_id', 'entry_date'],
        'tags'              => ['user_id', 'name'],
        'product_variant_groups' => ['user_id', 'name'],
        'installment_plans' => ['user_id', 'created_at'],
        'installment_payments' => ['user_id', 'due_date'],
        'sales'             => ['user_id', 'start_date'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->index($columns);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                $blueprint->dropIndex([$table . '_' . implode('_', $columns) . '_index']);
            });
        }
    }
};
