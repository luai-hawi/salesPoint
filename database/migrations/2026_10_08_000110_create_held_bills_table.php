<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('held_bills')) {
            return;
        }

        Schema::create('held_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('label', 120)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('table_label', 60)->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->longText('payload');
            $table->string('client_uuid', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('held_bills')) {
            Schema::drop('held_bills');
        }
    }
};
