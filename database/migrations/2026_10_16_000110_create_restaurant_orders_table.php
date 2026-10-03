<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_orders')) {
            Schema::create('restaurant_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('table_id')->nullable();
                $table->string('order_type', 20)->default('dine_in');
                $table->string('status', 20)->default('open');
                $table->string('label')->nullable();
                $table->unsignedTinyInteger('guests')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 40)->nullable();
                $table->string('customer_address')->nullable();
                $table->longText('cart')->nullable();
                $table->longText('sent_snapshot')->nullable();
                $table->decimal('total', 14, 2)->default(0);
                $table->unsignedBigInteger('opened_by')->nullable();
                $table->unsignedBigInteger('bill_id')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['user_id', 'table_id']);
                $table->index(['user_id', 'bill_id']);
            });

            return;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_orders');
    }
};
