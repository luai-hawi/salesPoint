<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
            if (! Schema::hasColumn('users', 'subscription_currency')) {
                $table->string('subscription_currency', 8)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'disabled_from_role')) {
                $table->string('disabled_from_role', 30)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'disabled_at')) {
                $table->timestamp('disabled_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'disabled_reason')) {
                $table->string('disabled_reason', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'entry_limit_mode')) {
                $table->string('entry_limit_mode', 10)->nullable()->default('off')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['admin_notes', 'subscription_currency', 'disabled_from_role', 'disabled_at', 'disabled_reason', 'entry_limit_mode'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
