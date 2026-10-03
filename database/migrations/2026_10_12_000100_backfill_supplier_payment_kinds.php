<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::table('supplier_payments')
                ->whereNull('kind')
                ->select('id', 'amount', 'note')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $kind = $this->classifyRow($row->note, (float) $row->amount);

                        DB::table('supplier_payments')
                            ->where('id', $row->id)
                            ->whereNull('kind')
                            ->update(['kind' => $kind]);
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('supplier_payments kind backfill skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Keep saved classifications; downgrades must not erase ledger meaning.
    }

    private function classifyRow(?string $note, float $amount): string
    {
        if ($note === 'Initial balance') {
            return 'opening_balance';
        }

        return $amount >= 0 ? 'payment' : 'refund';
    }
};
