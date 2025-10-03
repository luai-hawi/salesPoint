<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('last_sale_date')->nullable()->after('is_active');
            $table->integer('deactivation_warning_months')->default(5)->after('last_sale_date');
            $table->integer('deactivation_months')->default(6)->after('deactivation_warning_months');
            $table->timestamp('extended_until')->nullable()->after('deactivation_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['last_sale_date', 'deactivation_warning_months', 'deactivation_months', 'extended_until']);
        });
    }
};
