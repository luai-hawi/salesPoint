<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_payments')) {
            return;
        }

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8);
            $table->unsignedSmallInteger('months');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('paid_at');
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
