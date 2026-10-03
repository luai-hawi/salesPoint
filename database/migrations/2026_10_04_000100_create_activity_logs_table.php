<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Tenant the action belongs to (shop owner id). Admin actions use the admin id.
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->string('action', 40);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->text('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['owner_id', 'created_at']);
            $table->index(['owner_id', 'actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
