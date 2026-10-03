<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('day_closings')) {
            return;
        }

        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('closing_date');
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('counted_cash', 14, 2)->default(0);
            $table->decimal('variance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('snapshot')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'closing_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_closings');
    }
};
