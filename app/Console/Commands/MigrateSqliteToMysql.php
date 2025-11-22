<?php
// app/Console/Commands/MigrateSqliteToMysql.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateSqliteToMysql extends Command
{
    protected $signature = 'migrate:sqlite-to-mysql {sqlite_path}';
    protected $description = 'Migrate data from SQLite to MySQL';

    public function handle()
    {
        $sqlitePath = $this->argument('sqlite_path');
        
        if (!file_exists($sqlitePath)) {
            $this->error("SQLite file not found: {$sqlitePath}");
            return 1;
        }

        $this->info('Starting migration from SQLite to MySQL...');

        // Create SQLite connection
        $sqliteConfig = [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
        ];

        // Add SQLite connection temporarily
        config(['database.connections.sqlite_source' => $sqliteConfig]);
        
        try {
            $this->migrateAllTables();
            $this->info('Migration completed successfully!');
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function migrateAllTables()
    {
        // Define tables in order of dependencies (skip system tables)
        $tables = [
            'users',
            'products',
            'customers',
            'bills',
            'bill_product',
            'customer_payments',
            'batches',
            'employees',
            'employee_payments',
            'expenses',
        ];

        foreach ($tables as $table) {
            $this->migrateTable($table);
        }
    }

    private function migrateTable($tableName)
    {
        $this->info("Migrating table: {$tableName}");
        
        try {
            // Check if table exists in SQLite
            $sqliteConnection = DB::connection('sqlite_source');
            if (!$sqliteConnection->getSchemaBuilder()->hasTable($tableName)) {
                $this->warn("Table {$tableName} does not exist in SQLite, skipping...");
                return;
            }

            // Get column names for the table from MySQL (target)
            $mysqlConnection = DB::connection('mysql');
            $mysqlColumns = $mysqlConnection->getSchemaBuilder()->getColumnListing($tableName);
            
            if (empty($mysqlColumns)) {
                $this->warn("Table {$tableName} does not exist in MySQL, skipping...");
                return;
            }

            // Get all data from SQLite
            $data = $sqliteConnection->table($tableName)->get();
            
            if ($data->isEmpty()) {
                $this->info("Table {$tableName} is empty, skipping data migration...");
                return;
            }

            // Clear existing data in MySQL table
            $mysqlConnection->table($tableName)->truncate();

            // Disable foreign key checks temporarily
            $mysqlConnection->statement('SET FOREIGN_KEY_CHECKS=0;');
            
            try {
                // Convert data to proper format
                $convertedData = $data->map(function ($row) use ($mysqlColumns, $tableName) {
                    $rowArray = (array) $row;
                    $filteredRow = [];
                    
                    // Only include columns that exist in MySQL table
                    foreach ($mysqlColumns as $column) {
                        if (array_key_exists($column, $rowArray)) {
                            $filteredRow[$column] = $rowArray[$column];
                        } else {
                            // Set default value for missing columns
                            $filteredRow[$column] = $this->getDefaultValue($column);
                        }
                    }
                    
                    return $filteredRow;
                });

                // Insert data into MySQL in chunks
                $convertedData->chunk(100)->each(function ($chunk) use ($tableName, $mysqlConnection) {
                    $mysqlConnection->table($tableName)->insert($chunk->toArray());
                });
                
                $this->info("✓ Migrated {$data->count()} records to {$tableName}");
                
            } finally {
                // Re-enable foreign key checks
                $mysqlConnection->statement('SET FOREIGN_KEY_CHECKS=1;');
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Failed to migrate {$tableName}: " . $e->getMessage());
            throw $e;
        }
    }

    private function getDefaultValue($column)
    {
        // Handle common columns that might be missing
        switch ($column) {
            case 'created_at':
            case 'updated_at':
                return now();
            case 'email_verified_at':
                return null;
            case 'remember_token':
                return null;
            case 'role':
                return 'shop_owner';
            case 'shop_owner_id':
                return null;
            case 'user_id':
                return 1; // Default to first user
            case 'created_by':
                return null;
            case 'is_damaged':
                return false;
            case 'type':
                return 'payment';
            case 'balance':
                return 0;
            case 'discount':
                return 0;
            default:
                return null;
        }
    }

    private function resetAutoIncrement()
    {
        $this->info('Resetting auto-increment values...');
        
        $mysqlConnection = DB::connection('mysql');
        $tables = ['users', 'products', 'customers', 'bills', 'customer_payments', 'batches', 'employees', 'employee_payments', 'expenses'];
        
        foreach ($tables as $table) {
            try {
                $maxId = $mysqlConnection->table($table)->max('id') ?? 0;
                $nextId = $maxId + 1;
                $mysqlConnection->statement("ALTER TABLE {$table} AUTO_INCREMENT = {$nextId}");
                $this->info("✓ Reset auto-increment for {$table} to {$nextId}");
            } catch (\Exception $e) {
                $this->warn("Could not reset auto-increment for {$table}: " . $e->getMessage());
            }
        }
    }
}