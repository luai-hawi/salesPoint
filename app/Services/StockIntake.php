<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Financial side of adding stock outside a purchase bill (new product with stock, "add quantity", new batch).
 *
 * The stock itself (product quantity, batches, average cost) is changed by the caller as before.
 * This service only records the money, so supplier balances and the cash drawer stay correct and the
 * owner never needs to book the same purchase again as an "expense" (which would count the cost twice,
 * because profit already subtracts the cost of every product sold).
 *
 * funding.mode:
 *   none     no money movement (opening stock / goods already owned)  -> returns null
 *   credit   bought on account: the supplier is owed the full amount
 *   paid     paid in full now (registered supplier, or the built-in cash supplier when none is given)
 *   partial  part paid now (funding.paid_amount), the rest stays owed
 */
class StockIntake
{
    public const MODES = ['none', 'credit', 'paid', 'partial'];

    public const PAYMENT_METHODS = ['cash', 'card', 'transfer', 'check'];

    /**
     * @param  list<array{product_id: int, quantity: float|int|string, unit_cost: float|int|string}>  $lines
     * @param  array{mode?: string, supplier_id?: int|null, paid_amount?: float|int|string|null, payment_method?: string, date?: string|null, note?: string|null}  $funding
     */
    public function record(User $actor, array $lines, array $funding): ?PurchaseBill
    {
        $mode = $funding['mode'] ?? 'none';
        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Unknown stock funding mode [{$mode}].");
        }

        $lines = array_values(array_filter($lines, fn ($line) => (float) ($line['quantity'] ?? 0) > 0));
        if ($mode === 'none' || ! $lines) {
            return null;
        }

        $ownerId = $actor->ownerId();
        if (! $ownerId) {
            throw new \InvalidArgumentException('The acting user does not belong to a shop.');
        }

        $supplier = $this->resolveSupplier($ownerId, $funding['supplier_id'] ?? null, $mode);

        $total = 0.0;
        foreach ($lines as $line) {
            $total += round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
        }
        $total = round($total, 2);

        $paid = match ($mode) {
            'paid' => $total,
            'partial' => min($total, max(0.0, round((float) ($funding['paid_amount'] ?? 0), 2))),
            default => 0.0,
        };

        $date = ! empty($funding['date']) ? Carbon::parse($funding['date']) : now();

        return DB::transaction(function () use ($actor, $ownerId, $supplier, $lines, $total, $paid, $funding, $date) {
            // Idempotency is handled by the callers through batches.intake_client_uuid, not on the purchase bill.
            $bill = new PurchaseBill([
                'supplier_id' => $supplier->id,
                'total_amount' => $total,
                'notes' => $funding['note'] ?? null,
                'reference_number' => null,
                'purchase_date' => $date->toDateString(),
                'created_by' => $actor->id,
                'source' => 'stock_intake',
            ]);
            $bill->user_id = $ownerId;
            $bill->save();

            foreach ($lines as $line) {
                $product = Product::withoutGlobalScopes()
                    ->where('user_id', $ownerId)
                    ->findOrFail($line['product_id']);

                $quantity = (float) $line['quantity'];
                $unitCost = (float) $line['unit_cost'];

                $bill->products()->attach($product->id, [
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($quantity * $unitCost, 2),
                    'barcodes' => json_encode([]),
                ]);
            }

            SupplierLedger::charge($supplier, $total);

            if ($paid > 0) {
                SupplierLedger::pay(
                    $supplier,
                    $paid,
                    $funding['payment_method'] ?? 'cash',
                    null,
                    $date,
                    $bill,
                );
            }

            return $bill;
        });
    }

    private function resolveSupplier(int $ownerId, mixed $supplierId, string $mode): Supplier
    {
        if ($supplierId) {
            return Supplier::withoutGlobalScopes()
                ->where('user_id', $ownerId)
                ->findOrFail($supplierId);
        }

        if ($mode === 'paid') {
            return SupplierLedger::walkInSupplier($ownerId);
        }

        throw new \InvalidArgumentException('A supplier is required when stock is bought on account.');
    }
}
