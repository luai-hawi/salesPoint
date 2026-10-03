<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('batches')) {
            return;
        }

        Schema::table('batches', function (Blueprint $table) {
            if (! Schema::hasColumn('batches', 'purchase_bill_id')) {
                $table->unsignedBigInteger('purchase_bill_id')->nullable()->index();
            }
            if (! Schema::hasColumn('batches', 'intake_client_uuid')) {
                $table->string('intake_client_uuid', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('batches')) {
            return;
        }

        Schema::table('batches', function (Blueprint $table) {
            if (Schema::hasColumn('batches', 'purchase_bill_id')) {
                $table->dropColumn('purchase_bill_id');
            }
            if (Schema::hasColumn('batches', 'intake_client_uuid')) {
                $table->dropColumn('intake_client_uuid');
            }
        });
    }
};
