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
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedBigInteger('shop_owner_id')->nullable()->after('role');

        $table->foreign('shop_owner_id')->references('id')->on('users')->onDelete('set null');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['shop_owner_id']);
        $table->dropColumn('shop_owner_id');
    });
}

};
