<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variant_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Base product name (e.g., "T-Shirt")
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Add variant_group_id to products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('variant_group_id')->nullable()->after('user_id')->constrained('product_variant_groups')->onDelete('set null');
            $table->string('variant_name')->nullable()->after('variant_group_id'); // e.g., "S", "M", "L", "XL"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['variant_group_id']);
            $table->dropColumn(['variant_group_id', 'variant_name']);
        });

        Schema::dropIfExists('product_variant_groups');
    }
};
