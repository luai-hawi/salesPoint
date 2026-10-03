<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CustomerLedger;
use Illuminate\Console\Command;

class RecalculateCustomerBalances extends Command
{
    protected $signature = 'customers:recalc-balances {--dry-run} {--owner=}';

    protected $description = 'Compare customer balances with ledger totals and optionally fix drift safely.';

    public function handle(): int
    {
        $query = Customer::withoutGlobalScopes()->orderBy('user_id')->orderBy('id');

        if ($owner = $this->option('owner')) {
            $query->where('user_id', $owner);
        }

        $checked = 0;
        $drifted = 0;
        $fixed = 0;

        $query->chunkById(200, function ($customers) use (&$checked, &$drifted, &$fixed) {
            foreach ($customers as $customer) {
                $checked++;
                $expected = round((float) $customer->payments()->withoutGlobalScopes()->sum('amount'), 2);
                $actual = round((float) $customer->balance, 2);

                if ($expected === $actual) {
                    continue;
                }

                $drifted++;
                $this->line("Customer #{$customer->id} ({$customer->name}) balance {$actual} -> {$expected}");

                if (! $this->option('dry-run')) {
                    CustomerLedger::recalcBalance($customer);
                    $fixed++;
                }
            }
        });

        $this->info("Checked {$checked} customers; drifted {$drifted}; fixed {$fixed}.");

        return self::SUCCESS;
    }
}
