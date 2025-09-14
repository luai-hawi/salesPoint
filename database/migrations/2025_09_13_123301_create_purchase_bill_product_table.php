<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseBillProductTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_bill_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_bill_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 15, 2);
            $table->timestamps();
            
            $table->foreign('purchase_bill_id')->references('id')->on('purchase_bills')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['purchase_bill_id', 'product_id']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('purchase_bill_product');
    }
}