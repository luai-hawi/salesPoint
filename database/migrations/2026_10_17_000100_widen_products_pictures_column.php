<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'pictures')) {
            return;
        }

        $column = collect(Schema::getColumns('products'))->firstWhere('name', 'pictures');
        $typeName = strtolower((string) ($column['type_name'] ?? ''));

        if ($typeName === '' || in_array($typeName, ['text', 'mediumtext', 'longtext'], true)) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->text('pictures')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as a no-op: narrowing this column again could truncate live data.
    }
};
