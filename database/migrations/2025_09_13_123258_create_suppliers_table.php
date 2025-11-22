<?php
// Migration 1: create_suppliers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliersTable extends Migration
{
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('balance', 15, 2)->default(0); // positive = we owe them, negative = they owe us
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id'); // shop owner
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'name']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
}