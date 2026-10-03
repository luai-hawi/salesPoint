<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_records') && ! Schema::hasIndex('attendance_records', 'attendance_records_user_id_status_work_date_index')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'work_date']);
            });
        }

        if (Schema::hasTable('employee_payments') && Schema::hasColumn('employee_payments', 'period') && ! Schema::hasIndex('employee_payments', 'employee_payments_employee_id_period_index')) {
            Schema::table('employee_payments', function (Blueprint $table) {
                $table->index(['employee_id', 'period']);
            });
        }

        if (Schema::hasTable('employee_payments') && ! Schema::hasIndex('employee_payments', 'employee_payments_employee_id_payment_date_index')) {
            Schema::table('employee_payments', function (Blueprint $table) {
                $table->index(['employee_id', 'payment_date']);
            });
        }

        if (Schema::hasTable('employee_adjustments') && ! Schema::hasIndex('employee_adjustments', 'employee_adjustments_user_id_period_index')) {
            Schema::table('employee_adjustments', function (Blueprint $table) {
                $table->index(['user_id', 'period']);
            });
        }

        if (Schema::hasTable('employee_leaves') && ! Schema::hasIndex('employee_leaves', 'employee_leaves_user_id_date_from_date_to_index')) {
            Schema::table('employee_leaves', function (Blueprint $table) {
                $table->index(['user_id', 'date_from', 'date_to']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_leaves') && Schema::hasIndex('employee_leaves', 'employee_leaves_user_id_date_from_date_to_index')) {
            Schema::table('employee_leaves', function (Blueprint $table) {
                $table->dropIndex('employee_leaves_user_id_date_from_date_to_index');
            });
        }

        if (Schema::hasTable('employee_adjustments') && Schema::hasIndex('employee_adjustments', 'employee_adjustments_user_id_period_index')) {
            Schema::table('employee_adjustments', function (Blueprint $table) {
                $table->dropIndex('employee_adjustments_user_id_period_index');
            });
        }

        if (Schema::hasTable('employee_payments') && Schema::hasIndex('employee_payments', 'employee_payments_employee_id_payment_date_index')) {
            Schema::table('employee_payments', function (Blueprint $table) {
                $table->dropIndex('employee_payments_employee_id_payment_date_index');
            });
        }

        if (Schema::hasTable('employee_payments') && Schema::hasIndex('employee_payments', 'employee_payments_employee_id_period_index')) {
            Schema::table('employee_payments', function (Blueprint $table) {
                $table->dropIndex('employee_payments_employee_id_period_index');
            });
        }

        if (Schema::hasTable('attendance_records') && Schema::hasIndex('attendance_records', 'attendance_records_user_id_status_work_date_index')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropIndex('attendance_records_user_id_status_work_date_index');
            });
        }
    }
};
