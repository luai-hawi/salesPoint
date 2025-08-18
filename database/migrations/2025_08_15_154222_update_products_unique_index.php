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
    // Drop the old unique index
    Schema::table('products', function (Blueprint $table) {
        $table->dropUnique(['barcode']); // drop old unique constraint
    });

    // Add new unique composite index
    Schema::table('products', function (Blueprint $table) {
        $table->unique(['user_id', 'barcode']);
    });
}

public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropUnique(['user_id', 'barcode']);
        $table->unique('barcode'); // restore old unique
    });
}

};
