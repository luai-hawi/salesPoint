<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\SupplierLedger;
use Illuminate\Console\Command;

class RecalculateSupplierBalances extends Command
{
    protected $signature = 'suppliers:recalc-balances {--dry-run : Show changes without saving them} {--owner= : Limit to one shop owner id}';

    protected $description = 'Recompute supplier balances from opening rows, purchase bills, and supplier payments.';

    public function handle(): int
    {
        $query = Supplier::withoutGlobalScopes()->orderBy('id');

        if ($this->option('owner') !== null) {
            $query->where('user_id', (int) $this->option('owner'));
        }

        $count = 0;
        $changed = 0;

        $query->chunkById(200, function ($suppliers) use (&$count, &$changed) {
            foreach ($suppliers as $supplier) {
                $count++;
                $current = round((float) $supplier->balance, 2);
                $expected = SupplierLedger::expectedBalance($supplier);

                if ($current !== $expected) {
                    $changed++;
                }

                if ($this->option('dry-run')) {
                    if ($current !== $expected) {
                        $this->line("supplier={$supplier->id} owner={$supplier->user_id} current={$current} expected={$expected}");
                    }

                    continue;
                }

                SupplierLedger::recalcBalance($supplier);
            }
        });

        if ($this->option('dry-run')) {
            $this->info("Checked {$count} suppliers; {$changed} would change.");
        } else {
            $this->info("Recalculated {$count} suppliers; {$changed} changed.");
        }

        return self::SUCCESS;
    }
}
