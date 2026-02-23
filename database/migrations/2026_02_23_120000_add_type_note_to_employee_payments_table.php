<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            $table->enum('type', ['cash', 'card', 'transfer', 'check'])->default('cash')->after('amount');
            $table->text('note')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            $table->dropColumn(['type', 'note']);
        });
    }
};
