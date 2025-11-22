<?php
// app/Console/Commands/SimpleMigrateSqliteToMysql.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpleMigrateSqliteToMysql extends Command
{
    protected $signature = 'migrate:sqlite-simple {sqlite_path}';
    protected $description = 'Simple migration from SQLite to MySQL with explicit table handling';

    public function handle()
    {
        $sqlitePath = $this->argument('sqlite_path');
        
        if (!file_exists($sqlitePath)) {
            $this->error("SQLite file not found: {$sqlitePath}");
            return 1;
        }

        $this->info('Starting simple migration from SQLite to MySQL...');

        // Create SQLite connection
        config(['database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
        ]]);
        
        try {
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0;');
            
            // Migrate in dependency order
            $this->migrateUsers();
            $this->migrateProducts();
            $this->migrateCustomers();
            $this->migrateBills();
            $this->migrateBillProducts();
            $this->migrateCustomerPayments();
            $this->migrateBatches();
            $this->migrateEmployees();
            $this->migrateEmployeePayments();
            $this->migrateExpenses();
            
            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $this->info('Migration completed successfully!');
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function migrateUsers()
    {
        $this->info('Migrating users...');
        
        $users = DB::connection('sqlite_source')->table('users')->get();
        
        foreach ($users as $user) {
            DB::connection('mysql')->table('users')->insert([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token ?? null,
                'role' => $user->role ?? 'shop_owner',
                'shop_owner_id' => $user->shop_owner_id ?? null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }
        
        $this->info("Migrated {$users->count()} users");
    }

    private function migrateProducts()
    {
        $this->info('Migrating products...');
        
        $products = DB::connection('sqlite_source')->table('products')->get();
        
        foreach ($products as $product) {
            DB::connection('mysql')->table('products')->insert([
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'pictures' => $product->pictures,
                'quantity' => $product->quantity ?? 0,
                'cost_price' => $product->cost_price ?? 0,
                'selling_price' => $product->selling_price ?? 0,
                'user_id' => $product->user_id ?? 1,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ]);
        }
        
        $this->info("Migrated {$products->count()} products");
    }

    private function migrateCustomers()
    {
        $this->info('Migrating customers...');
        
        $customers = DB::connection('sqlite_source')->table('customers')->get();
        
        foreach ($customers as $customer) {
            DB::connection('mysql')->table('customers')->insert([
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'balance' => $customer->balance ?? 0,
                'user_id' => $customer->user_id ?? 1,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
            ]);
        }
        
        $this->info("Migrated {$customers->count()} customers");
    }

    private function migrateBills()
    {
        $this->info('Migrating bills...');
        
        $bills = DB::connection('sqlite_source')->table('bills')->get();
        
        foreach ($bills as $bill) {
            DB::connection('mysql')->table('bills')->insert([
                'id' => $bill->id,
                'customer_id' => $bill->customer_id,
                'total_price' => $bill->total_price ?? 0,
                'note' => $bill->note,
                'is_damaged' => $bill->is_damaged ?? false,
                'user_id' => $bill->user_id ?? 1,
                'created_by' => $bill->created_by,
                'created_at' => $bill->created_at,
                'updated_at' => $bill->updated_at,
            ]);
        }
        
        $this->info("Migrated {$bills->count()} bills");
    }

    private function migrateBillProducts()
    {
        $this->info('Migrating bill_product...');
        
        $billProducts = DB::connection('sqlite_source')->table('bill_product')->get();
        
        foreach ($billProducts as $bp) {
            DB::connection('mysql')->table('bill_product')->insert([
                'id' => $bp->id,
                'bill_id' => $bp->bill_id,
                'product_id' => $bp->product_id,
                'quantity' => $bp->quantity ?? 1,
                'discount' => $bp->discount ?? 0,
                'cost_price' => $bp->cost_price ?? 0,
                'selling_price' => $bp->selling_price ?? 0,
                'created_at' => $bp->created_at,
                'updated_at' => $bp->updated_at,
            ]);
        }
        
        $this->info("Migrated {$billProducts->count()} bill products");
    }

    private function migrateCustomerPayments()
    {
        $this->info('Migrating customer_payments...');
        
        $payments = DB::connection('sqlite_source')->table('customer_payments')->get();
        
        foreach ($payments as $payment) {
            DB::connection('mysql')->table('customer_payments')->insert([
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount ?? 0,
                'type' => $payment->type ?? 'payment',
                'note' => $payment->note,
                'user_id' => $payment->user_id ?? 1,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]);
        }
        
        $this->info("Migrated {$payments->count()} customer payments");
    }

    private function migrateBatches()
    {
        $this->info('Migrating batches...');
        
        $batches = DB::connection('sqlite_source')->table('batches')->get();
        
        foreach ($batches as $batch) {
            DB::connection('mysql')->table('batches')->insert([
                'id' => $batch->id,
                'product_id' => $batch->product_id,
                'quantity' => $batch->quantity ?? 0,
                'cost_price' => $batch->cost_price ?? 0,
                'user_id' => $batch->user_id ?? 1,
                'created_at' => $batch->created_at,
                'updated_at' => $batch->updated_at,
            ]);
        }
        
        $this->info("Migrated {$batches->count()} batches");
    }

    private function migrateEmployees()
    {
        $this->info('Migrating employees...');
        
        if (!DB::connection('sqlite_source')->getSchemaBuilder()->hasTable('employees')) {
            $this->warn('Employees table does not exist in SQLite, skipping...');
            return;
        }
        
        $employees = DB::connection('sqlite_source')->table('employees')->get();
        
        foreach ($employees as $employee) {
            DB::connection('mysql')->table('employees')->insert([
                'id' => $employee->id,
                'shop_owner_id' => $employee->shop_owner_id,
                'name' => $employee->name,
                'job_title' => $employee->job_title,
                'monthly_salary' => $employee->monthly_salary ?? 0,
                'created_at' => $employee->created_at,
                'updated_at' => $employee->updated_at,
            ]);
        }
        
        $this->info("Migrated {$employees->count()} employees");
    }

    private function migrateEmployeePayments()
    {
        $this->info('Migrating employee_payments...');
        
        if (!DB::connection('sqlite_source')->getSchemaBuilder()->hasTable('employee_payments')) {
            $this->warn('Employee_payments table does not exist in SQLite, skipping...');
            return;
        }
        
        $payments = DB::connection('sqlite_source')->table('employee_payments')->get();
        
        foreach ($payments as $payment) {
            DB::connection('mysql')->table('employee_payments')->insert([
                'id' => $payment->id,
                'employee_id' => $payment->employee_id,
                'amount' => $payment->amount ?? 0,
                'payment_date' => $payment->payment_date,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]);
        }
        
        $this->info("Migrated {$payments->count()} employee payments");
    }

    private function migrateExpenses()
    {
        $this->info('Migrating expenses...');
        
        if (!DB::connection('sqlite_source')->getSchemaBuilder()->hasTable('expenses')) {
            $this->warn('Expenses table does not exist in SQLite, skipping...');
            return;
        }
        
        $expenses = DB::connection('sqlite_source')->table('expenses')->get();
        
        foreach ($expenses as $expense) {
            DB::connection('mysql')->table('expenses')->insert([
                'id' => $expense->id,
                'title' => $expense->title,
                'amount' => $expense->amount ?? 0,
                'expense_date' => $expense->expense_date,
                'notes' => $expense->notes,
                'user_id' => $expense->user_id ?? 1,
                'created_at' => $expense->created_at,
                'updated_at' => $expense->updated_at,
            ]);
        }
        
        $this->info("Migrated {$expenses->count()} expenses");
    }
}