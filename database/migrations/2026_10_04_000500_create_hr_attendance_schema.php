<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR / attendance schema (additive only).
 *
 *  employees            + staff-portal login, pay type/rates, schedule, activity flags
 *  employee_payments    + kind / period (which month a salary payment covers)
 *  users                + staff_portal_key (secret path of the shop's staff page), attendance_settings (JSON)
 *  attendance_locations   allowed places (geofences) of a shop
 *  attendance_records     one row per check-in/out pair
 *  employee_devices       phones that stay signed in to the staff portal
 *  employee_credentials   WebAuthn (fingerprint / face) credentials
 *  employee_adjustments   bonuses / deductions / penalties per month
 *  employee_leaves        excused absences
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'username' => fn () => $table->string('username', 60)->nullable(),
                'password' => fn () => $table->string('password')->nullable(),
                'phone' => fn () => $table->string('phone', 30)->nullable(),
                'is_active' => fn () => $table->boolean('is_active')->default(true),
                'portal_enabled' => fn () => $table->boolean('portal_enabled')->default(false),
                'biometric_required' => fn () => $table->boolean('biometric_required')->nullable(),
                'salary_type' => fn () => $table->string('salary_type', 12)->default('monthly'),
                'hourly_rate' => fn () => $table->decimal('hourly_rate', 12, 2)->nullable(),
                'daily_rate' => fn () => $table->decimal('daily_rate', 12, 2)->nullable(),
                'hire_date' => fn () => $table->date('hire_date')->nullable(),
                'schedule' => fn () => $table->text('schedule')->nullable(),
                'staff_notes' => fn () => $table->text('staff_notes')->nullable(),
                'last_portal_login_at' => fn () => $table->timestamp('last_portal_login_at')->nullable(),
            ];

            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('employees', $name)) {
                    $add();
                }
            }
        });

        if (! Schema::hasIndex('employees', 'employees_shop_owner_id_username_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique(['shop_owner_id', 'username']);
            });
        }

        Schema::table('employee_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_payments', 'kind')) {
                $table->string('kind', 20)->nullable();
            }
            if (! Schema::hasColumn('employee_payments', 'period')) {
                $table->string('period', 7)->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'staff_portal_key')) {
                $table->string('staff_portal_key', 40)->nullable();
            }
            if (! Schema::hasColumn('users', 'attendance_settings')) {
                $table->text('attendance_settings')->nullable();
            }
        });

        if (! Schema::hasIndex('users', 'users_staff_portal_key_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('staff_portal_key');
            });
        }

        if (! Schema::hasTable('attendance_locations')) {
            Schema::create('attendance_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('name', 120);
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedInteger('radius_m')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_id');
                $table->date('work_date');

                $table->timestamp('check_in_at')->nullable();
                $table->decimal('check_in_lat', 10, 7)->nullable();
                $table->decimal('check_in_lng', 10, 7)->nullable();
                $table->unsignedInteger('check_in_accuracy_m')->nullable();
                $table->integer('check_in_distance_m')->nullable();
                $table->boolean('check_in_inside')->nullable();
                $table->unsignedBigInteger('check_in_location_id')->nullable();
                $table->string('check_in_method', 20)->nullable();
                $table->unsignedBigInteger('check_in_device_id')->nullable();

                $table->timestamp('check_out_at')->nullable();
                $table->decimal('check_out_lat', 10, 7)->nullable();
                $table->decimal('check_out_lng', 10, 7)->nullable();
                $table->unsignedInteger('check_out_accuracy_m')->nullable();
                $table->integer('check_out_distance_m')->nullable();
                $table->boolean('check_out_inside')->nullable();
                $table->unsignedBigInteger('check_out_location_id')->nullable();
                $table->string('check_out_method', 20)->nullable();
                $table->unsignedBigInteger('check_out_device_id')->nullable();
                $table->boolean('check_out_remote')->default(false);
                $table->timestamp('check_out_recorded_at')->nullable();

                $table->text('reason')->nullable();
                // open | closed | needs_review | approved | rejected
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('minutes_worked')->nullable();
                // portal | manual | offline | auto
                $table->string('source', 20)->default('portal');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'work_date']);
                $table->index(['employee_id', 'work_date']);
                $table->index(['employee_id', 'check_out_at']);
            });
        }

        if (! Schema::hasTable('employee_devices')) {
            Schema::create('employee_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('token_hash', 64)->unique();
                $table->string('label', 120)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index('employee_id');
            });
        }

        if (! Schema::hasTable('employee_credentials')) {
            Schema::create('employee_credentials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_id');
                $table->text('credential_id');
                $table->string('credential_hash', 64)->unique();
                $table->text('public_key');
                $table->integer('algorithm')->default(-7);
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->text('transports')->nullable();
                $table->string('label', 120)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('employee_id');
            });
        }

        if (! Schema::hasTable('employee_adjustments')) {
            Schema::create('employee_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('period', 7);
                // bonus | deduction | penalty
                $table->string('kind', 20);
                $table->decimal('amount', 12, 2);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'period']);
            });
        }

        if (! Schema::hasTable('employee_leaves')) {
            Schema::create('employee_leaves', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_id');
                $table->date('date_from');
                $table->date('date_to');
                // paid | unpaid | sick | vacation
                $table->string('type', 20)->default('paid');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'date_from']);
            });
        }
    }

    public function down(): void
    {
        foreach (['employee_leaves', 'employee_adjustments', 'employee_credentials', 'employee_devices', 'attendance_records', 'attendance_locations'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasIndex('users', 'users_staff_portal_key_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_staff_portal_key_unique');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['attendance_settings', 'staff_portal_key'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('employee_payments', function (Blueprint $table) {
            foreach (['period', 'kind'] as $column) {
                if (Schema::hasColumn('employee_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasIndex('employees', 'employees_shop_owner_id_username_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_shop_owner_id_username_unique');
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            foreach (['last_portal_login_at', 'staff_notes', 'schedule', 'hire_date', 'daily_rate', 'hourly_rate', 'salary_type',
                'biometric_required', 'portal_enabled', 'is_active', 'phone', 'password', 'username'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
