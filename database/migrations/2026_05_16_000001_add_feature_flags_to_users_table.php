<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // JSON array of blocked feature keys, e.g. ["installments","sales_promotions","financial_dashboard"]
            // NULL or [] means everything is allowed (default = tier 4 behaviour)
            $table->json('blocked_features')->nullable()->after('visibility_settings');

            // Maximum combined record count (bills + products + customers + purchase_bills).
            // NULL means unlimited (tier 4).
            $table->unsignedInteger('entry_limit')->nullable()->after('blocked_features');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blocked_features', 'entry_limit']);
        });
    }
};
