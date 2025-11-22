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
    Schema::table('expenses', function (Blueprint $table) {
        $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
    });

    // If you already have expenses, assign a default user id (for example, 1)
    DB::table('expenses')->update(['user_id' => 1]);

    // Then make it NOT NULL
    Schema::table('expenses', function (Blueprint $table) {
        $table->foreignId('user_id')->nullable(false)->change();
    });
}


public function down(): void
{
    Schema::table('expenses', function (Blueprint $table) {
        $table->dropForeign(['user_id']);
        $table->dropColumn('user_id');
    });
}
};
