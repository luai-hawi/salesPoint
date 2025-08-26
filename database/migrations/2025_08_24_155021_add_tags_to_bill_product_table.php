<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bill_product', function (Blueprint $table) {
            $table->text('tags')->nullable()->after('selling_price');
        });
    }

    public function down()
    {
        Schema::table('bill_product', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};