<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('image_optimizations')) {
            return;
        }

        Schema::create('image_optimizations', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedBigInteger('original_bytes')->default(0);
            $table->unsignedBigInteger('optimized_bytes')->default(0);
            $table->unsignedBigInteger('optimized_mtime')->nullable();
            $table->timestamp('optimized_at')->nullable();
            $table->timestamps();
            $table->index('optimized_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_optimizations');
    }
};
