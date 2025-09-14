<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplierPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->decimal('amount', 15, 2); // positive = we paid them, negative = they paid us
            $table->enum('type', ['cash', 'card', 'transfer', 'check']);
            $table->text('note')->nullable();
            $table->date('payment_date');
            $table->unsignedBigInteger('user_id'); // shop owner
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'payment_date']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('supplier_payments');
    }
}