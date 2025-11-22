<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            // Drop the old enum column
            $table->dropColumn('type');
        });
        
        Schema::table('customer_payments', function (Blueprint $table) {
            // Add the new type column with payment method types
            $table->enum('type', ['cash', 'card', 'transfer', 'check'])->default('cash')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            // Drop the new column
            $table->dropColumn('type');
        });
        
        Schema::table('customer_payments', function (Blueprint $table) {
            // Restore the old enum column
            $table->enum('type', ['payment', 'refund'])->default('payment')->after('amount');
        });
    }
};