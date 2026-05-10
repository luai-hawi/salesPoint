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
        $file = "{$dir}/backup_{$timestamp}.sql.gz";

        if ($this->isExecAvailable()) {
            // Fast path: use mysqldump via shell
            $host    = escapeshellarg($config['host'] ?? '127.0.0.1');
            $port    = escapeshellarg($config['port'] ?? '3306');
            $user    = escapeshellarg($config['username'] ?? 'root');
            $pass    = $config['password'] ?? '';
            $db      = escapeshellarg($config['database']);
            $fileArg = escapeshellarg($file);

            // Pass password via env variable to avoid it appearing in process list
            $env     = !empty($pass) ? "MYSQL_PWD=" . escapeshellarg($pass) . " " : '';
            $command = "{$env}mysqldump --host={$host} --port={$port} --user={$user} --single-transaction --quick --lock-tables=false {$db} | gzip > {$fileArg} 2>&1";

            \exec($command, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($file) || filesize($file) === 0) {
                $message = implode("\n", $output);
                $this->error("MySQL backup failed. Exit code: {$exitCode}. Output: {$message}");
                Log::error("BackupDatabase MySQL failed", ['exit_code' => $exitCode, 'output' => $output]);
                return false;
            }
        } else {
            // Fallback: pure-PHP PDO dump (used when exec is disabled on the host)
            $this->info("exec() is unavailable. Using PHP PDO dump fallback.");
            Log::info("BackupDatabase: using PHP PDO fallback for MySQL backup");

            if (!$this->dumpMysqlViaPdo($config, $file)) {
                return false;
            }
        }

        $size = $this->humanSize(filesize($file));
        $this->info("MySQL backup created: backup_{$timestamp}.sql.gz ({$size})");
        Log::info("BackupDatabase: MySQL backup created [{$file}] ({$size})");
        return true;
    }

    /**
     * Dump a MySQL/MariaDB database to a gzipped SQL file using PDO only.
     * Used as a fallback when exec() is disabled on the host.
     */
    private function dumpMysqlViaPdo(array $config, string $file): bool
    {
        try {
            $host    = $config['host'] ?? '127.0.0.1';
            $port    = $config['port'] ?? '3306';
            $dbName  = $config['database'];
            $charset = $config['charset'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
            $pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $gz = \gzopen($file, 'wb9');
            if ($gz === false) {
                $this->error("MySQL backup failed: could not open gzip output file.");
                Log::error("BackupDatabase: gzopen failed for [{$file}]");
                return false;
            }

            \gzwrite($gz, "-- Database: {$dbName}\n");
            \gzwrite($gz, "-- Generated: " . date('Y-m-d H:i:s') . " (PHP PDO dump)\n\n");
            \gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n");
            \gzwrite($gz, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                \gzwrite($gz, "-- Table: `{$table}`\n");
                \gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");

                $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                \gzwrite($gz, $createRow['Create Table'] . ";\n\n");

                // Dump rows in chunks to avoid memory exhaustion on large tables
                $rowStmt = $pdo->query("SELECT * FROM `{$table}`");
                $chunk   = [];
                $columns = null;

                while ($row = $rowStmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($columns === null) {
                        $columns = '`' . implode('`, `', array_keys($row)) . '`';
                    }
                    $escaped = array_map(
                        fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    );
                    $chunk[] = '(' . implode(', ', $escaped) . ')';

                    if (count($chunk) >= 200) {
                        \gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $chunk) . ";\n");
                        $chunk = [];
                    }
                }

                if (!empty($chunk) && $columns !== null) {
                    \gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $chunk) . ";\n");
                }

                \gzwrite($gz, "\n");
            }

            \gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
            \gzclose($gz);

            return true;
        } catch (\Throwable $e) {
            $this->error("MySQL PDO backup failed: " . $e->getMessage());
            Log::error("BackupDatabase: PDO dump failed", ['error' => $e->getMessage()]);
            return false;
        }
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

        if (!$this->isExecAvailable()) {
            $this->error("PostgreSQL backup requires exec(), which is disabled on this host.");
            Log::error("BackupDatabase: exec() unavailable, cannot run pg_dump");
            return false;
        }

        $env     = !empty($pass) ? "PGPASSWORD=" . escapeshellarg($pass) . " " : '';
        $command = "{$env}pg_dump --host={$host} --port={$port} --username={$user} {$db} | gzip > {$fileArg} 2>&1";

        \exec($command, $output, $exitCode);

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

    /**
     * Check whether PHP's exec() function is available on this host.
     * Many shared hosts disable it via the disable_functions php.ini directive.
     */
    private function isExecAvailable(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if ($disabled) {
            $disabledList = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $disabledList, true)) {
                return false;
            }
        }
        return true;
    }

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
