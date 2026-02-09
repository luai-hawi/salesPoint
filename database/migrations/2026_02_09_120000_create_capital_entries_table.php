<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('capital_entries', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->date('entry_date');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('capital_entries');
    }
};
