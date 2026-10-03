<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_payments', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->after('bill_id');
            }
        });

        if (! Schema::hasIndex('customer_payments', 'customer_payments_user_id_client_uuid_unique')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->unique(['user_id', 'client_uuid'], 'customer_payments_user_id_client_uuid_unique');
            });
        }

        Schema::table('installment_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('installment_plans', 'client_uuid')) {
                $table->string('client_uuid', 64)->nullable()->after('bill_id');
            }
        });

        if (! Schema::hasIndex('installment_plans', 'installment_plans_user_id_client_uuid_unique')) {
            Schema::table('installment_plans', function (Blueprint $table) {
                $table->unique(['user_id', 'client_uuid'], 'installment_plans_user_id_client_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customer_payments', 'customer_payments_user_id_client_uuid_unique')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->dropUnique('customer_payments_user_id_client_uuid_unique');
            });
        }

        if (Schema::hasIndex('installment_plans', 'installment_plans_user_id_client_uuid_unique')) {
            Schema::table('installment_plans', function (Blueprint $table) {
                $table->dropUnique('installment_plans_user_id_client_uuid_unique');
            });
        }
    }
};
