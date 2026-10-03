<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\CapitalEntry;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Observers\ActivityObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Safety net: destructive database commands (migrate:fresh / refresh / reset / rollback, db:wipe)
        // only run against throw-away databases (SQLite, *_test, *_testing, *_scratch, salespoint_compat).
        // Set ALLOW_DESTRUCTIVE_DB_COMMANDS=true in .env to lift the guard on purpose.
        DB::prohibitDestructiveCommands(! $this->isThrowawayDatabase());

        foreach ([
            Bill::class,
            CustomerPayment::class,
            SupplierPayment::class,
            PurchaseBill::class,
            Expense::class,
            CapitalEntry::class,
            Customer::class,
            Supplier::class,
            Product::class,
            EmployeePayment::class,
            Employee::class,
            User::class,
        ] as $model) {
            $model::observe(ActivityObserver::class);
        }
    }

    private function isThrowawayDatabase(): bool
    {
        if (config('app.allow_destructive_db_commands')) {
            return true;
        }

        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) === 'sqlite') {
            return true;
        }

        return (bool) preg_match('/(_test|_testing|_scratch)$|^salespoint_compat$/i', (string) ($config['database'] ?? ''));
    }
}
