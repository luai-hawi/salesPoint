<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imeis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->string('imei');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('purchase_bill_id')->nullable();
            $table->unsignedBigInteger('sale_bill_id')->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->date('purchased_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('purchase_bill_id')->references('id')->on('purchase_bills')->onDelete('set null');
            $table->foreign('sale_bill_id')->references('id')->on('bills')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['user_id', 'product_id']);
            $table->index(['imei']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imeis');
    }
};
