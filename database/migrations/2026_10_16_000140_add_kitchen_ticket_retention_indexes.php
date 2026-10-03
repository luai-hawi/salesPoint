<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kitchen_tickets')) {
            return;
        }

        if (! Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_status_user_id_served_at_id_index')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->index(['status', 'user_id', 'served_at', 'id'], 'kitchen_tickets_status_user_id_served_at_id_index');
            });
        }

        if (! Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_status_user_id_cancelled_at_id_index')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->index(['status', 'user_id', 'cancelled_at', 'id'], 'kitchen_tickets_status_user_id_cancelled_at_id_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kitchen_tickets')) {
            return;
        }

        if (Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_status_user_id_cancelled_at_id_index')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->dropIndex('kitchen_tickets_status_user_id_cancelled_at_id_index');
            });
        }

        if (Schema::hasIndex('kitchen_tickets', 'kitchen_tickets_status_user_id_served_at_id_index')) {
            Schema::table('kitchen_tickets', function (Blueprint $table) {
                $table->dropIndex('kitchen_tickets_status_user_id_served_at_id_index');
            });
        }
    }
};
