<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('license_expires_at')->nullable()->after('temp_expires_at');
            $table->integer('last_payment_months')->nullable()->after('license_expires_at');
            $table->decimal('last_payment_amount', 8, 2)->nullable()->after('last_payment_months');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['license_expires_at', 'last_payment_months', 'last_payment_amount']);
        });
    }
};
