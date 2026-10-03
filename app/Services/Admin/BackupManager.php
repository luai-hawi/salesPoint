<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Artisan;

class BackupManager
{
    public function __construct(
        private readonly string $backupDirectory = '',
    ) {
    }

    /**
     * @return list<array{filename: string, path: string, bytes: int, modified_at: int, deletable: bool}>
     */
    public function listBackups(int $keep = 7): array
    {
        $directory = $this->directory();
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . 'backup_*') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return collect($files)
            ->values()
            ->map(fn ($path, $index) => [
                'filename' => basename($path),
                'path' => $path,
                'bytes' => (int) filesize($path),
                'modified_at' => (int) filemtime($path),
                'deletable' => $index >= $keep,
            ])
            ->all();
    }

    /**
     * @return array{ok: bool, filename: string|null}
     */
    public function createBackup(int $keep = 7): array
    {
        $before = collect($this->listBackups(0))
            ->pluck('filename')
            ->flip();
        $startedAt = time();
        $exitCode = Artisan::call('db:backup', ['--keep' => $keep]);
        $backups = $this->listBackups($keep);
        $created = collect($backups)
            ->first(fn ($backup) => ! $before->has($backup['filename']) && $backup['modified_at'] >= $startedAt);

        return [
            'ok' => $exitCode === 0 && is_array($created),
            'filename' => $created['filename'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $filenames
     * @return array{deleted: int}
     */
    public function deleteBackups(array $filenames): array
    {
        $allowed = collect($this->listBackups(0))->keyBy('filename');
        $deleted = 0;

        foreach (array_values(array_unique($filenames)) as $filename) {
            $path = $allowed->get($filename)['path'] ?? null;
            if ($path && is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }

        return ['deleted' => $deleted];
    }

    public function resolve(string $filename): ?string
    {
        if ($filename !== basename($filename)) {
            return null;
        }

        $allowed = collect($this->listBackups(0))->keyBy('filename');
        $entry = $allowed->get($filename);
        if (! $entry) {
            return null;
        }

        $path = realpath($entry['path']);
        $directory = realpath($this->directory());

        if (! $path || ! $directory || dirname($path) !== $directory || ! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function directory(): string
    {
        return $this->backupDirectory !== '' ? $this->backupDirectory : storage_path('app/backups');
    }
}
