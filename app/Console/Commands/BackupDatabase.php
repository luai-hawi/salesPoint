<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=7 : Number of backups to retain}';

    protected $description = 'Backup the database and keep only the most recent N backups';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');
        $driver = config('database.default');
        $config = config("database.connections.{$driver}");
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        $result = match ($driver) {
            'mysql', 'mariadb' => $this->backupMysql($config, $backupDir, $timestamp),
            'sqlite' => $this->backupSqlite($config, $backupDir, $timestamp),
            'pgsql' => $this->backupPgsql($config, $backupDir, $timestamp),
            default => null,
        };

        if ($result === null) {
            $this->error("Unsupported database driver: {$driver}");
            Log::error("BackupDatabase: Unsupported driver [{$driver}]");

            return self::FAILURE;
        }

        if (! $result['ok']) {
            return self::FAILURE;
        }

        $this->rotate($backupDir, $keep);

        return self::SUCCESS;
    }

    /**
     * @return array{ok: bool, file: string|null}
     */
    private function backupMysql(array $config, string $dir, string $timestamp): array
    {
        $finalFile = "{$dir}/backup_{$timestamp}.sql.gz";

        if ($this->isExecAvailable()) {
            $sqlTemp = $this->tempPath($dir, 'mysql', 'sql');
            $gzipTemp = $this->tempPath($dir, 'mysql', 'sql.gz');

            try {
                $command = [
                    'mysqldump',
                    '--host=' . ($config['host'] ?? '127.0.0.1'),
                    '--port=' . ($config['port'] ?? '3306'),
                    '--user=' . ($config['username'] ?? 'root'),
                    '--single-transaction',
                    '--quick',
                    '--lock-tables=false',
                    '--skip-comments',
                    '--result-file=' . $sqlTemp,
                    (string) ($config['database'] ?? ''),
                ];

                $process = $this->runProcess($command, [
                    'MYSQL_PWD' => (string) ($config['password'] ?? ''),
                ]);

                if ($process['exit_code'] !== 0 || ! $this->isValidSqlDump($sqlTemp)) {
                    $this->error("MySQL backup failed. Exit code: {$process['exit_code']}. Output: {$process['stderr']}");
                    Log::error('BackupDatabase MySQL failed', $process);
                    $this->cleanupFiles([$sqlTemp, $gzipTemp]);

                    return ['ok' => false, 'file' => null];
                }

                if (! $this->gzipFile($sqlTemp, $gzipTemp) || ! $this->isValidGzipDump($gzipTemp)) {
                    $this->error('MySQL backup failed while compressing the dump.');
                    Log::error('BackupDatabase MySQL compression failed', ['file' => $gzipTemp]);
                    $this->cleanupFiles([$sqlTemp, $gzipTemp]);

                    return ['ok' => false, 'file' => null];
                }

                $this->atomicRename($gzipTemp, $finalFile);
                $this->cleanupFiles([$sqlTemp]);
            } catch (\Throwable $e) {
                $this->cleanupFiles([$sqlTemp ?? null, $gzipTemp ?? null]);
                $this->error('MySQL backup failed: ' . $e->getMessage());
                Log::error('BackupDatabase MySQL failed', ['error' => $e->getMessage()]);

                return ['ok' => false, 'file' => null];
            }
        } else {
            $this->info('exec() is unavailable. Using PHP PDO dump fallback.');
            Log::info('BackupDatabase: using PHP PDO fallback for MySQL backup');

            if (! $this->dumpMysqlViaPdo($config, $finalFile)) {
                return ['ok' => false, 'file' => null];
            }
        }

        $size = $this->humanSize((int) filesize($finalFile));
        $this->info("MySQL backup created: " . basename($finalFile) . " ({$size})");
        Log::info("BackupDatabase: MySQL backup created [{$finalFile}] ({$size})");

        return ['ok' => true, 'file' => $finalFile];
    }

    private function dumpMysqlViaPdo(array $config, string $finalFile): bool
    {
        $sqlTemp = $this->tempPath(dirname($finalFile), 'mysql-fallback', 'sql');
        $gzipTemp = $this->tempPath(dirname($finalFile), 'mysql-fallback', 'sql.gz');

        try {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? '3306';
            $dbName = $config['database'];
            $charset = $config['charset'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
            $pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ]);

            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

            $handle = fopen($sqlTemp, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open SQL temp file for writing.');
            }

            fwrite($handle, "-- Database: {$dbName}\n");
            fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . " (PHP PDO dump)\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_COLUMN, 0);

            foreach ($tables as $table) {
                fwrite($handle, "-- Table: `{$table}`\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

                $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                fwrite($handle, $createRow['Create Table'] . ";\n\n");

                $rowStmt = $pdo->query("SELECT * FROM `{$table}`");
                $chunk = [];
                $columns = null;

                while ($row = $rowStmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($columns === null) {
                        $columns = '`' . implode('`, `', array_keys($row)) . '`';
                    }

                    $escaped = array_map(
                        fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                        array_values($row)
                    );
                    $chunk[] = '(' . implode(', ', $escaped) . ')';

                    if (count($chunk) >= 200) {
                        fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $chunk) . ";\n");
                        $chunk = [];
                    }
                }

                if ($chunk !== [] && $columns !== null) {
                    fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $chunk) . ";\n");
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
            $pdo->exec('COMMIT');

            if (! $this->isValidSqlDump($sqlTemp) || ! $this->gzipFile($sqlTemp, $gzipTemp) || ! $this->isValidGzipDump($gzipTemp)) {
                throw new \RuntimeException('Generated fallback dump is invalid.');
            }

            $this->atomicRename($gzipTemp, $finalFile);
            $this->cleanupFiles([$sqlTemp]);

            return true;
        } catch (\Throwable $e) {
            if (isset($pdo)) {
                try {
                    $pdo->exec('ROLLBACK');
                } catch (\Throwable) {
                }
            }

            $this->cleanupFiles([$sqlTemp ?? null, $gzipTemp ?? null]);
            $this->error('MySQL PDO backup failed: ' . $e->getMessage());
            Log::error('BackupDatabase: PDO dump failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{ok: bool, file: string|null}
     */
    private function backupSqlite(array $config, string $dir, string $timestamp): array
    {
        $source = $config['database'] ?? null;
        if (! $source || $source === ':memory:') {
            $this->error('SQLite backup requires a file-based database.');
            Log::error('BackupDatabase: SQLite backup unsupported for in-memory database');

            return ['ok' => false, 'file' => null];
        }

        if (! file_exists($source)) {
            $this->error("SQLite database file not found: {$source}");
            Log::error("BackupDatabase: SQLite file not found [{$source}]");

            return ['ok' => false, 'file' => null];
        }

        $finalFile = "{$dir}/backup_{$timestamp}.sqlite";
        $tempFile = $this->tempPath($dir, 'sqlite', 'sqlite');

        try {
            $pdo = new \PDO('sqlite:' . $source, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $quoted = str_replace("'", "''", $tempFile);
            $pdo->exec("VACUUM INTO '{$quoted}'");

            if (! $this->isValidSqliteBackup($tempFile)) {
                throw new \RuntimeException('SQLite backup file is invalid.');
            }

            $this->atomicRename($tempFile, $finalFile);
        } catch (\Throwable $e) {
            $this->cleanupFiles([$tempFile ?? null]);
            $this->error('SQLite backup failed: ' . $e->getMessage());
            Log::error('BackupDatabase: SQLite backup failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'file' => null];
        }

        $size = $this->humanSize((int) filesize($finalFile));
        $this->info("SQLite backup created: " . basename($finalFile) . " ({$size})");
        Log::info("BackupDatabase: SQLite backup created [{$finalFile}] ({$size})");

        return ['ok' => true, 'file' => $finalFile];
    }

    /**
     * @return array{ok: bool, file: string|null}
     */
    private function backupPgsql(array $config, string $dir, string $timestamp): array
    {
        if (! $this->isExecAvailable()) {
            $this->error('PostgreSQL backup requires exec(), which is disabled on this host.');
            Log::error('BackupDatabase: exec() unavailable, cannot run pg_dump');

            return ['ok' => false, 'file' => null];
        }

        $finalFile = "{$dir}/backup_{$timestamp}.sql.gz";
        $sqlTemp = $this->tempPath($dir, 'pgsql', 'sql');
        $gzipTemp = $this->tempPath($dir, 'pgsql', 'sql.gz');

        try {
            $command = [
                'pg_dump',
                '--host=' . ($config['host'] ?? '127.0.0.1'),
                '--port=' . ($config['port'] ?? '5432'),
                '--username=' . ($config['username'] ?? 'postgres'),
                '--file=' . $sqlTemp,
                (string) ($config['database'] ?? ''),
            ];

            $process = $this->runProcess($command, [
                'PGPASSWORD' => (string) ($config['password'] ?? ''),
            ]);

            if ($process['exit_code'] !== 0 || ! $this->isValidSqlDump($sqlTemp)) {
                $this->error("PostgreSQL backup failed. Exit code: {$process['exit_code']}. Output: {$process['stderr']}");
                Log::error('BackupDatabase PgSQL failed', $process);
                $this->cleanupFiles([$sqlTemp, $gzipTemp]);

                return ['ok' => false, 'file' => null];
            }

            if (! $this->gzipFile($sqlTemp, $gzipTemp) || ! $this->isValidGzipDump($gzipTemp)) {
                throw new \RuntimeException('Compressed PostgreSQL dump is invalid.');
            }

            $this->atomicRename($gzipTemp, $finalFile);
            $this->cleanupFiles([$sqlTemp]);
        } catch (\Throwable $e) {
            $this->cleanupFiles([$sqlTemp ?? null, $gzipTemp ?? null]);
            $this->error('PostgreSQL backup failed: ' . $e->getMessage());
            Log::error('BackupDatabase PgSQL failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'file' => null];
        }

        $size = $this->humanSize((int) filesize($finalFile));
        $this->info("PostgreSQL backup created: " . basename($finalFile) . " ({$size})");
        Log::info("BackupDatabase: PostgreSQL backup created [{$finalFile}] ({$size})");

        return ['ok' => true, 'file' => $finalFile];
    }

    private function rotate(string $dir, int $keep): void
    {
        $files = glob($dir . '/backup_*');

        if ($files === false || count($files) <= $keep) {
            return;
        }

        sort($files);
        foreach (array_slice($files, 0, count($files) - $keep) as $old) {
            if (@unlink($old)) {
                $this->line('Deleted old backup: ' . basename($old));
                Log::info("BackupDatabase: Deleted old backup [{$old}]");
            } else {
                $this->warn('Could not delete: ' . basename($old));
                Log::warning("BackupDatabase: Could not delete [{$old}]");
            }
        }
    }

    private function isExecAvailable(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = ini_get('disable_functions');
        if ($disabled) {
            $disabledList = array_map('trim', explode(',', $disabled));
            if (in_array('proc_open', $disabledList, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runProcess(array $command, array $env = []): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, array_merge($_ENV, $_SERVER, $env), ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new \RuntimeException('Unable to start backup process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => (int) $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];
    }

    private function gzipFile(string $source, string $destination): bool
    {
        $in = fopen($source, 'rb');
        $out = gzopen($destination, 'wb9');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                gzclose($out);
            }

            return false;
        }

        while (! feof($in)) {
            $chunk = fread($in, 8192);
            if ($chunk === false) {
                fclose($in);
                gzclose($out);

                return false;
            }

            gzwrite($out, $chunk);
        }

        fclose($in);
        gzclose($out);

        return true;
    }

    private function isValidSqlDump(string $file): bool
    {
        if (! is_file($file) || filesize($file) < 64) {
            return false;
        }

        $sample = file_get_contents($file, false, null, 0, 4096);
        if (! is_string($sample) || $sample === '') {
            return false;
        }

        return str_contains($sample, 'CREATE TABLE')
            || str_contains($sample, 'INSERT INTO')
            || str_contains($sample, '-- Database:')
            || str_contains($sample, 'PostgreSQL database dump');
    }

    private function isValidGzipDump(string $file): bool
    {
        if (! is_file($file) || filesize($file) < 64) {
            return false;
        }

        $handle = gzopen($file, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = gzread($handle, 4096);
        gzclose($handle);

        return is_string($sample) && $sample !== '' && (
            str_contains($sample, 'CREATE TABLE')
            || str_contains($sample, 'INSERT INTO')
            || str_contains($sample, '-- Database:')
            || str_contains($sample, 'PostgreSQL database dump')
        );
    }

    private function isValidSqliteBackup(string $file): bool
    {
        if (! is_file($file) || filesize($file) < 512) {
            return false;
        }

        $header = file_get_contents($file, false, null, 0, 16);

        return $header === "SQLite format 3\0";
    }

    private function atomicRename(string $source, string $destination): void
    {
        if (file_exists($destination)) {
            @unlink($destination);
        }

        if (! @rename($source, $destination)) {
            throw new \RuntimeException('Unable to finalize backup file.');
        }
    }

    /**
     * @param  array<int, string|null>  $files
     */
    private function cleanupFiles(array $files): void
    {
        foreach ($files as $file) {
            if ($file && is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function tempPath(string $dir, string $prefix, string $extension): string
    {
        return $dir . DIRECTORY_SEPARATOR . $prefix . '_' . uniqid('', true) . '.' . $extension;
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
