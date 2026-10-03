<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('key', 80);
                $table->string('request_key', 80)->nullable();
                $table->string('method', 10);
                $table->string('path', 255);
                $table->unsignedSmallInteger('status');
                $table->longText('body')->nullable();
                $table->boolean('body_truncated')->default(false);
                $table->string('location', 500)->nullable();
                $table->string('content_type')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['user_id', 'key'], 'idempotency_keys_user_key_unique');
                $table->index('created_at', 'idempotency_keys_created_at_index');
            });

            return;
        }

        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (! Schema::hasColumn('idempotency_keys', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'key')) {
                $table->string('key', 80)->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'method')) {
                $table->string('method', 10)->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'request_key')) {
                $table->string('request_key', 80)->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'path')) {
                $table->string('path', 255)->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'status')) {
                $table->unsignedSmallInteger('status')->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'body')) {
                $table->longText('body')->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'body_truncated')) {
                $table->boolean('body_truncated')->default(false);
            }
            if (! Schema::hasColumn('idempotency_keys', 'location')) {
                $table->string('location', 500)->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'content_type')) {
                $table->string('content_type')->nullable();
            }
            if (! Schema::hasColumn('idempotency_keys', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
        });

        if (! Schema::hasIndex('idempotency_keys', 'idempotency_keys_user_key_unique')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->unique(['user_id', 'key'], 'idempotency_keys_user_key_unique');
            });
        }

        if (! Schema::hasIndex('idempotency_keys', 'idempotency_keys_created_at_index')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->index('created_at', 'idempotency_keys_created_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
