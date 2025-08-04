<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('bills', function (Blueprint $table) {
        $table->foreignId('customer_id')->nullable()->after('id')
              ->constrained('customers')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('bills', function (Blueprint $table) {
        $table->dropConstrainedForeignId('customer_id');
    });
}

};
