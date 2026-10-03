<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class StorageOverviewService
{
    public function __construct(
        private readonly string $storagePublicPath = '',
        private readonly string $backupPath = '',
        private readonly string $logsPath = '',
        private readonly string $compiledViewsPath = '',
        private readonly string $frameworkCachePath = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(bool $fresh = false): array
    {
        $callback = function (): array {
            $publicPath = $this->storagePublicPath !== '' ? $this->storagePublicPath : storage_path('app/public');
            $backupPath = $this->backupPath !== '' ? $this->backupPath : storage_path('app/backups');
            $logsPath = $this->logsPath !== '' ? $this->logsPath : storage_path('logs');
            $compiledViewsPath = $this->compiledViewsPath !== '' ? $this->compiledViewsPath : storage_path('framework/views');
            $frameworkCachePath = $this->frameworkCachePath !== '' ? $this->frameworkCachePath : storage_path('framework/cache');
            $diskBase = base_path();

            try {
                $diskTotal = function_exists('disk_total_space') ? @disk_total_space($diskBase) : false;
                $diskFree = function_exists('disk_free_space') ? @disk_free_space($diskBase) : false;
            } catch (\Throwable $e) {
                $diskTotal = false;
                $diskFree = false;
            }

            return [
                'disk' => [
                    'total' => $diskTotal === false ? null : (int) $diskTotal,
                    'free' => $diskFree === false ? null : (int) $diskFree,
                    'used' => ($diskTotal === false || $diskFree === false) ? null : max(0, (int) $diskTotal - (int) $diskFree),
                ],
                'sizes' => [
                    'public' => $this->directorySize($publicPath),
                    'backups' => $this->directorySize($backupPath),
                    'logs' => $this->directorySize($logsPath),
                    'compiled_views' => $this->directorySize($compiledViewsPath),
                    'framework_cache' => $this->directorySize($frameworkCachePath),
                    'database' => $this->databaseSize(),
                ],
                'counts' => [
                    'sessions' => $this->safeCount('sessions'),
                    'cache' => $this->safeCount('cache'),
                    'jobs' => $this->safeCount('jobs'),
                    'failed_jobs' => $this->safeCount('failed_jobs'),
                    'activity_logs' => $this->safeCount('activity_logs'),
                ],
                'generated_at' => now()->toIso8601String(),
            ];
        };

        return $fresh
            ? tap($callback(), fn ($value) => Cache::put('admin_storage_overview', $value, 600))
            : Cache::remember('admin_storage_overview', 600, $callback);
    }

    public function forget(): void
    {
        Cache::forget('admin_storage_overview');
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;
        foreach (File::allFiles($path) as $file) {
            $bytes += (int) $file->getSize();
        }

        return $bytes;
    }

    private function safeCount(string $table): int
    {
        try {
            return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function databaseSize(): ?int
    {
        try {
            $driver = config('database.default');
            $config = config('database.connections.' . $driver);

            if ($driver === 'sqlite') {
                $database = $config['database'] ?? null;

                return ($database && $database !== ':memory:' && is_file($database))
                    ? (int) filesize($database)
                    : null;
            }

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                return null;
            }

            $databaseName = $config['database'] ?? null;
            if (! $databaseName) {
                return null;
            }

            $size = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS size_bytes FROM information_schema.tables WHERE table_schema = ?',
                [$databaseName]
            );

            return isset($size->size_bytes) ? (int) $size->size_bytes : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
