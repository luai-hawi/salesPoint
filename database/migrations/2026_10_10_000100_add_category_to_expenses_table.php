<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'category')) {
                $table->string('category')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses') || ! Schema::hasColumn('expenses', 'category')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
