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
        Schema::table('users', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('name');
            $table->enum('account_type', ['full', 'temp'])->default('temp')->after('image_limit');
            $table->integer('temp_period_days')->nullable()->after('account_type');
            $table->date('temp_expires_at')->nullable()->after('temp_period_days');
        });

        // Change image_limit default from 1000 to 100
        Schema::table('users', function (Blueprint $table) {
            $table->integer('image_limit')->default(100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'account_type', 'temp_period_days', 'temp_expires_at']);
            $table->integer('image_limit')->default(1000)->change();
        });
    }
};
