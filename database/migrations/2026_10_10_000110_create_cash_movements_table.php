<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_movements')) {
            return;
        }

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->text('note')->nullable();
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
