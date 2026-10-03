<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (! Schema::hasColumn('idempotency_keys', 'request_key')) {
                $table->string('request_key', 80)->nullable()->after('key');
            }
            if (! Schema::hasColumn('idempotency_keys', 'body_truncated')) {
                $table->boolean('body_truncated')->default(false)->after('body');
            }
        });

        try {
            DB::table('idempotency_keys')
                ->select('id', 'key', 'method', 'path')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $method = strtoupper((string) $row->method);
                        $path = '/' . ltrim((string) $row->path, '/');
                        $rawKey = (string) $row->key;

                        DB::table('idempotency_keys')
                            ->where('id', $row->id)
                            ->update([
                                'request_key' => $rawKey,
                                'key' => sha1($method . '|' . $path . '|' . $rawKey),
                            ]);
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('idempotency_keys route backfill skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Legacy raw key values cannot be reconstructed safely.
    }
};
