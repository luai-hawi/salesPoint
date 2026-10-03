<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_tables')) {
            Schema::create('restaurant_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name', 40);
                $table->string('zone', 40)->nullable();
                $table->unsignedTinyInteger('seats')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'name']);
                $table->index(['user_id', 'zone']);
                $table->index(['user_id', 'is_active']);
            });

            return;
        }

        Schema::table('restaurant_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_tables', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (! Schema::hasColumn('restaurant_tables', 'name')) {
                $table->string('name', 40)->nullable();
            }
            if (! Schema::hasColumn('restaurant_tables', 'zone')) {
                $table->string('zone', 40)->nullable();
            }
            if (! Schema::hasColumn('restaurant_tables', 'seats')) {
                $table->unsignedTinyInteger('seats')->nullable();
            }
            if (! Schema::hasColumn('restaurant_tables', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }
            if (! Schema::hasColumn('restaurant_tables', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
