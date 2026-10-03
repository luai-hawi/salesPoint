<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kitchen_tickets')) {
            Schema::create('kitchen_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedInteger('number');
                $table->date('local_service_date')->nullable();
                $table->longText('items');
                $table->string('status', 20)->default('new');
                $table->string('priority', 20)->default('normal');
                $table->string('station', 40)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('client_uuid', 64)->nullable();
                $table->text('notes')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status', 'sent_at']);
                $table->index(['user_id', 'local_service_date']);
                $table->unique(['user_id', 'client_uuid']);
            });

            return;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
    }
};
