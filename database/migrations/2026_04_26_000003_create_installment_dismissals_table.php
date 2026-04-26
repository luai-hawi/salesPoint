<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('dismissed_for_date');
            $table->timestamps();
            $table->unique(['installment_payment_id', 'user_id', 'dismissed_for_date'], 'dismissal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_dismissals');
    }
};
