<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseBillsTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->decimal('total_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->string('reference_number')->nullable(); // supplier's invoice number
            $table->date('purchase_date');
            $table->unsignedBigInteger('user_id'); // shop owner
            $table->unsignedBigInteger('created_by'); // actual user who created it
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'purchase_date']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('purchase_bills');
    }
}