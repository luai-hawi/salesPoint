<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=7 : Number of backups to retain}';

    protected $description = 'Backup the database and keep only the most recent N backups';

    public function handle(): int
    {
        $keep     = (int) $this->option('keep');
        $driver   = config('database.default');
        $config   = config("database.connections.{$driver}");
        $backupDir = storage_path('app/backups');

        // Ensure backup directory exists
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = $this->backupMysql($config, $backupDir, $timestamp);
        } elseif ($driver === 'sqlite') {
            $result = $this->backupSqlite($config, $backupDir, $timestamp);
        } elseif ($driver === 'pgsql') {
            $result = $this->backupPgsql($config, $backupDir, $timestamp);
        } else {
            $this->error("Unsupported database driver: {$driver}");
            Log::error("BackupDatabase: Unsupported driver [{$driver}]");
            return self::FAILURE;
        }

        if (!$result) {
            return self::FAILURE;
        }

        // Rotate: keep only the $keep most recent backups
        $this->rotate($backupDir, $keep);

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Driver implementations
    // -------------------------------------------------------------------------

    private function backupMysql(array $config, string $dir, string $timestamp): bool
    {
        $database = $config['database'];
        $file     = "{$dir}/backup_{$timestamp}.sql.gz";

        $host     = escapeshellarg($config['host'] ?? '127.0.0.1');
        $port     = escapeshellarg($config['port'] ?? '3306');
        $user     = escapeshellarg($config['username'] ?? 'root');
        $pass     = $config['password'] ?? '';
        $db       = escapeshellarg($database);
        $fileArg  = escapeshellarg($file);

        // Pass password via env variable to avoid it appearing in process list
        $env     = !empty($pass) ? "MYSQL_PWD=" . escapeshellarg($pass) . " " : '';
        $command = "{$env}mysqldump --host={$host} --port={$port} --user={$user} --single-transaction --quick --lock-tables=false {$db} | gzip > {$fileArg} 2>&1";

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($file) || filesize($file) === 0) {
            $message = implode("\n", $output);
            $this->error("MySQL backup failed. Exit code: {$exitCode}. Output: {$message}");
            Log::error("BackupDatabase MySQL failed", ['exit_code' => $exitCode, 'output' => $output]);
            return false;
        }

        $size = $this->humanSize(filesize($file));
        $this->info("MySQL backup created: backup_{$timestamp}.sql.gz ({$size})");
        Log::info("BackupDatabase: MySQL backup created [{$file}] ({$size})");
        return true;
    }

    private function backupSqlite(array $config, string $dir, string $timestamp): bool
    {
        $source = $config['database'];

        if (!file_exists($source)) {
            $this->error("SQLite database file not found: {$source}");
            Log::error("BackupDatabase: SQLite file not found [{$source}]");
            return false;
        }

        $file = "{$dir}/backup_{$timestamp}.sqlite";

        // Use SQLite's online backup by simply copying the file
        // (SQLite allows safe reads; for write-busy DBs this is fine for small DBs)
        if (!copy($source, $file)) {
            $this->error("SQLite backup failed: could not copy database file.");
            Log::error("BackupDatabase: SQLite copy failed [{$source}] -> [{$file}]");
            return false;
        }

        $size = $this->humanSize(filesize($file));
        $this->info("SQLite backup created: backup_{$timestamp}.sqlite ({$size})");
        Log::info("BackupDatabase: SQLite backup created [{$file}] ({$size})");
        return true;
    }

    private function backupPgsql(array $config, string $dir, string $timestamp): bool
    {
        $database = $config['database'];
        $file     = "{$dir}/backup_{$timestamp}.sql.gz";

        $host    = escapeshellarg($config['host'] ?? '127.0.0.1');
        $port    = escapeshellarg($config['port'] ?? '5432');
        $user    = escapeshellarg($config['username'] ?? 'postgres');
        $pass    = $config['password'] ?? '';
        $db      = escapeshellarg($database);
        $fileArg = escapeshellarg($file);

        $env     = !empty($pass) ? "PGPASSWORD=" . escapeshellarg($pass) . " " : '';
        $command = "{$env}pg_dump --host={$host} --port={$port} --username={$user} {$db} | gzip > {$fileArg} 2>&1";

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($file) || filesize($file) === 0) {
            $message = implode("\n", $output);
            $this->error("PostgreSQL backup failed. Exit code: {$exitCode}. Output: {$message}");
            Log::error("BackupDatabase PgSQL failed", ['exit_code' => $exitCode, 'output' => $output]);
            return false;
        }

        $size = $this->humanSize(filesize($file));
        $this->info("PostgreSQL backup created: backup_{$timestamp}.sql.gz ({$size})");
        Log::info("BackupDatabase: PostgreSQL backup created [{$file}] ({$size})");
        return true;
    }

    // -------------------------------------------------------------------------
    // Rotation
    // -------------------------------------------------------------------------

    private function rotate(string $dir, int $keep): void
    {
        $pattern = $dir . '/backup_*';
        $files   = glob($pattern);

        if ($files === false || count($files) <= $keep) {
            return;
        }

        // Sort ascending by filename (timestamps sort naturally)
        sort($files);

        $toDelete = array_slice($files, 0, count($files) - $keep);

        foreach ($toDelete as $old) {
            if (unlink($old)) {
                $this->line("Deleted old backup: " . basename($old));
                Log::info("BackupDatabase: Deleted old backup [{$old}]");
            } else {
                $this->warn("Could not delete: " . basename($old));
                Log::warning("BackupDatabase: Could not delete [{$old}]");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
