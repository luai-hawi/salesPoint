<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_orders', 'open_table_key')) {
                $table->string('open_table_key', 80)->nullable();
            }
            if (! Schema::hasColumn('restaurant_orders', 'pending_bill_client_uuid')) {
                $table->string('pending_bill_client_uuid', 64)->nullable();
            }
        });

        if (Schema::hasTable('restaurant_orders')) {
            $orders = DB::table('restaurant_orders')
                ->where('status', 'open')
                ->whereNotNull('table_id')
                ->orderBy('id')
                ->get(['id', 'user_id', 'table_id']);

            $seen = [];
            foreach ($orders as $order) {
                $key = $order->user_id . ':' . $order->table_id;
                DB::table('restaurant_orders')
                    ->where('id', $order->id)
                    ->update(['open_table_key' => isset($seen[$key]) ? null : $key]);
                $seen[$key] = true;
            }
        }

        if (! Schema::hasIndex('restaurant_orders', 'restaurant_orders_open_table_key_unique')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->unique('open_table_key');
            });
        }

        if (! Schema::hasIndex('restaurant_orders', 'restaurant_orders_bill_id_unique')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->unique('bill_id');
            });
        }

        if (! Schema::hasIndex('restaurant_orders', 'restaurant_orders_pending_bill_client_uuid_index')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->index('pending_bill_client_uuid');
            });
        }

        if (! Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_user_id_local_service_date_number_unique')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->unique(['user_id', 'local_service_date', 'number']);
            });
        }

        if (! Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_user_id_status_updated_at_id_index')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'updated_at', 'id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_user_id_status_updated_at_id_index')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->dropIndex('kitchen_tickets_user_id_status_updated_at_id_index');
            });
        }

        if (Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_user_id_local_service_date_number_unique')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->dropUnique('kitchen_tickets_user_id_local_service_date_number_unique');
            });
        }

        if (Schema::hasIndex('restaurant_orders', 'restaurant_orders_pending_bill_client_uuid_index')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->dropIndex('restaurant_orders_pending_bill_client_uuid_index');
            });
        }

        if (Schema::hasIndex('restaurant_orders', 'restaurant_orders_bill_id_unique')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->dropUnique('restaurant_orders_bill_id_unique');
            });
        }

        if (Schema::hasIndex('restaurant_orders', 'restaurant_orders_open_table_key_unique')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->dropUnique('restaurant_orders_open_table_key_unique');
            });
        }

        Schema::table('restaurant_orders', function (Blueprint $table) {
            foreach (['pending_bill_client_uuid', 'open_table_key'] as $column) {
                if (Schema::hasColumn('restaurant_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
