<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillService
{
    /**
     * @return array{bill: \App\Models\Bill, duplicate: bool}
     */
    public function create(User $actor, array $data): array
    {
        $ownerId = $actor->ownerId();
        if (! $ownerId) {
            abort(403, 'Unauthorized');
        }

        $clientUuid = $this->normalizeClientUuid($data['client_uuid'] ?? null);
        if ($clientUuid) {
            $existing = $this->existingByClientUuid($ownerId, $clientUuid);
            if ($existing) {
                return ['bill' => $existing->loadMissing(['products', 'customer', 'creator']), 'duplicate' => true];
            }
        }

        try {
            $bill = DB::transaction(function () use ($actor, $ownerId, $data, $clientUuid) {
                $isDamaged = (bool) ($data['is_damaged'] ?? false);
                $isReturned = (bool) ($data['is_returned'] ?? false);
                $billDate = $this->billDate($data['bill_date'] ?? null);
                $customer = $this->resolveCustomer($ownerId, $data['customer_id'] ?? null);
                $paymentMethod = $this->paymentMethod($data['payment_method'] ?? null);
                $lines = $this->prepareLines($data, $isReturned);
                $products = $this->lockProducts($ownerId, array_column($lines, 'product_id'));

                $noteText = $this->composeNote($data['note'] ?? null, $isDamaged, $isReturned);

                $bill = new Bill([
                    'note' => $noteText,
                    'total_price' => 0,
                    'customer_id' => $customer?->id,
                    'user_id' => $ownerId,
                    'created_by' => $actor->id,
                    'is_damaged' => $isDamaged,
                    'is_returned' => $isReturned,
                    'payment_method' => $paymentMethod,
                    'client_uuid' => $clientUuid,
                ]);
                $bill->created_at = $billDate;
                $bill->updated_at = $billDate;
                $bill->save();

                $total = 0.0;
                $isRestaurant = $actor->isRestaurantAccount();

                foreach ($lines as $line) {
                    $product = $products[$line['product_id']] ?? null;
                    if (! $product) {
                        abort(404);
                    }

                    $qty = $line['qty'];
                    $costPrice = $line['cost_price'];
                    $sellingPrice = $line['selling_price'];
                    $discountType = $line['discount_type'];
                    $tags = $line['tags'];
                    $discount = $line['discount'];

                    if (! $isRestaurant) {
                        $this->applyStockMovement($product, $qty, $costPrice, $ownerId, $isReturned);
                    }

                    $tagsTotal = $this->calculateTagsTotal($tags);
                    if ($isDamaged) {
                        $discount = ($sellingPrice + $tagsTotal) * abs($qty);
                        $lineTotal = 0.0;
                    } else {
                        $lineTotal = ($sellingPrice * $qty) - $discount + ($tagsTotal * $qty);
                    }

                    $bill->products()->attach($product->id, [
                        'quantity' => $qty,
                        'discount' => $discount,
                        'cost_price' => $costPrice,
                        'selling_price' => $sellingPrice,
                        'tags' => $tags,
                        'imeis' => null,
                    ]);

                    $this->markImeis($data, $bill, $product->id, $ownerId, $sellingPrice, $isReturned);

                    $total += $isReturned ? $lineTotal : max(0, $lineTotal);
                }

                $bill->update(['total_price' => round($total, 2)]);

                if ($isReturned && ! $isRestaurant) {
                    foreach ($lines as $line) {
                        $product = $products[$line['product_id']] ?? null;

                        if (! $product) {
                            continue;
                        }

                        $returnQty = abs($line['original_qty']);
                        $returnCostPrice = $line['cost_price'];

                        $originalQty = $product->quantity - $returnQty;
                        $currentQty = max(0, $originalQty);
                        $currentCostPrice = (float) $product->cost_price;
                        $returnAbsQty = abs($returnQty);

                        if (($currentQty + $returnAbsQty) > 0) {
                            $newCostPrice = ($currentQty * $currentCostPrice + $returnAbsQty * $returnCostPrice) / ($currentQty + $returnAbsQty);
                        } else {
                            $newCostPrice = $returnCostPrice;
                        }

                        $product->cost_price = round($newCostPrice, 2);
                        $product->save();
                    }
                }

                $paidAmount = $this->validatedPaidAmount(
                    $bill,
                    (float) ($data['paid_amount'] ?? 0),
                    $isDamaged,
                    $isReturned,
                    $paymentMethod,
                );

                if ($bill->customer_id && $bill->total_price > 0) {
                    CustomerLedger::chargeBill($bill);

                    if ($paidAmount > 0) {
                        CustomerLedger::receiveForBill(
                            $bill,
                            $paidAmount,
                            $paymentMethod,
                            __('receivables.bill_payment_note', ['bill' => $bill->id]),
                            $billDate,
                            null,
                        );
                    }
                }

                return $bill->fresh(['products', 'customer', 'creator']);
            });
        } catch (QueryException $e) {
            if ($clientUuid && $this->isClientUuidDuplicate($e)) {
                $existing = $this->existingByClientUuid($ownerId, $clientUuid);
                if ($existing) {
                    return ['bill' => $existing->loadMissing(['products', 'customer', 'creator']), 'duplicate' => true];
                }
            }

            throw $e;
        }

        return ['bill' => $bill, 'duplicate' => false];
    }

    private function resolveCustomer(int $ownerId, mixed $customerId): ?Customer
    {
        if (! $customerId) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->findOrFail($customerId);
    }

    private function billDate(?string $value): Carbon
    {
        if (! $value) {
            return now();
        }

        return Carbon::parse($value)->setTime(now()->hour, now()->minute, now()->second);
    }

    private function paymentMethod(?string $value): string
    {
        return in_array($value, ['cash', 'card', 'transfer', 'check'], true) ? $value : 'cash';
    }

    private function normalizeClientUuid(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    public function composeNote(?string $note, bool $isDamaged = false, bool $isReturned = false): string
    {
        $userNote = trim((string) $note);
        $markers = [];

        if ($isDamaged) {
            $markers[] = 'Damaged Bill';
        }

        if ($isReturned) {
            $markers[] = 'Returned Bill';
        }

        $suffix = implode(' - ', $markers);
        if ($suffix === '') {
            return mb_substr($userNote, 0, 255);
        }

        $separator = $userNote !== '' ? ' - ' : '';
        $available = 255 - mb_strlen($suffix) - mb_strlen($separator);
        if ($available < 0) {
            return mb_substr($suffix, 0, 255);
        }

        $userNote = mb_substr($userNote, 0, $available);

        return $userNote !== ''
            ? $userNote . $separator . $suffix
            : $suffix;
    }

    private function existingByClientUuid(int $ownerId, string $clientUuid): ?Bill
    {
        return Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('client_uuid', $clientUuid)
            ->first();
    }

    private function isClientUuidDuplicate(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return in_array((string) $e->getCode(), ['19', '23000', '23505'], true)
            || str_contains($message, 'bills_user_id_client_uuid_unique')
            || str_contains($message, 'unique constraint failed: bills.user_id, bills.client_uuid')
            || str_contains($message, 'duplicate entry');
    }

    private function validatedPaidAmount(Bill $bill, float $paidAmount, bool $isDamaged, bool $isReturned, string $method): float
    {
        if (! $bill->customer_id || $bill->total_price <= 0 || $isDamaged || $isReturned) {
            return 0.0;
        }

        $paidAmount = round(max(0, $paidAmount), 2);
        if ($paidAmount > round((float) $bill->total_price, 2)) {
            throw ValidationException::withMessages([
                'paid_amount' => __('receivables.validation.paid_amount_max'),
            ]);
        }

        return $paidAmount;
    }

    private function applyStockMovement(Product $product, float $qty, float $costPrice, int $ownerId, bool $isReturned): void
    {
        $product->quantity -= $qty;
        $product->last_sale_date = now();
        $product->save();

        $remainingQty = $qty;
        $batches = $product->batches()->where('quantity', '>', 0)->orderBy('id')->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $consume = min($batch->quantity, $remainingQty);
            $batch->quantity -= $consume;
            $batch->save();
            $remainingQty -= $consume;
        }

        if ($remainingQty > 0) {
            $lastBatch = $product->batches()->orderByDesc('id')->lockForUpdate()->first();
            if ($lastBatch) {
                $lastBatch->quantity -= $remainingQty;
                $lastBatch->save();
            } else {
                $product->batches()->create([
                    'quantity' => -1 * $remainingQty,
                    'cost_price' => $product->cost_price,
                    'user_id' => $ownerId,
                ]);
            }
        } elseif ($remainingQty < 0 && $isReturned) {
            $product->batches()->create([
                'quantity' => abs($remainingQty),
                'cost_price' => $costPrice,
                'user_id' => $ownerId,
            ]);
        }
    }

    private function prepareLines(array $data, bool $isReturned): array
    {
        $productIds = array_values($data['product_ids'] ?? []);
        $quantities = array_values($data['quantities'] ?? []);
        $discounts = array_values($data['discounts'] ?? []);
        $costPrices = array_values($data['cost_prices'] ?? []);
        $sellingPrices = array_values($data['selling_prices'] ?? []);
        $discountTypes = array_values($data['discount_types'] ?? array_fill(0, count($productIds), 'total'));
        $productTags = array_values($data['product_tags'] ?? array_fill(0, count($productIds), null));
        $returnCosts = array_values($data['return_costs'] ?? array_fill(0, count($productIds), null));

        $expected = count($productIds);
        foreach ([$quantities, $discounts, $costPrices, $sellingPrices, $discountTypes, $productTags, $returnCosts] as $array) {
            if (count($array) !== $expected) {
                throw ValidationException::withMessages([
                    'product_ids' => __('receivables.validation.bill_lines_misaligned'),
                ]);
            }
        }

        $lines = [];
        foreach ($productIds as $index => $productId) {
            $qty = (float) $quantities[$index];
            $costPrice = (float) $costPrices[$index];
            $sellingPrice = (float) $sellingPrices[$index];
            $discount = (float) $discounts[$index];

            if (! $productId || $qty <= 0 || $costPrice < 0 || $sellingPrice <= 0 || $discount < 0) {
                throw ValidationException::withMessages([
                    'product_ids' => __('receivables.validation.bill_lines_invalid'),
                ]);
            }

            $effectiveCost = $costPrice;
            $effectiveQty = $qty;
            $effectiveDiscount = (($discountTypes[$index] ?? 'total') === 'per-unit') ? ($discount * abs($qty)) : $discount;
            if ($isReturned) {
                if ($returnCosts[$index] !== null && $returnCosts[$index] !== '') {
                    $effectiveCost = (float) $returnCosts[$index];
                    if ($effectiveCost < 0) {
                        throw ValidationException::withMessages([
                            'return_costs' => __('receivables.validation.bill_lines_invalid'),
                        ]);
                    }
                }
                $effectiveQty = -1 * abs($qty);
                $effectiveDiscount = -1 * abs($effectiveDiscount);
            }

            $lines[] = [
                'product_id' => (int) $productId,
                'original_qty' => $qty,
                'qty' => $effectiveQty,
                'cost_price' => $effectiveCost,
                'selling_price' => $sellingPrice,
                'discount' => $effectiveDiscount,
                'discount_type' => $discountTypes[$index] ?? 'total',
                'tags' => $productTags[$index] ?: null,
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, Product>
     */
    private function lockProducts(int $ownerId, array $productIds): array
    {
        return Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('id', array_values(array_unique(array_map('intval', $productIds))))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function markImeis(array $data, Bill $bill, int $productId, int $ownerId, float $sellingPrice, bool $isReturned): void
    {
        $imeiCodes = $data["imeis_product_{$productId}"] ?? [];
        if (! is_array($imeiCodes) || $imeiCodes === [] || $isReturned) {
            return;
        }

        $savedImeis = [];

        foreach ($imeiCodes as $imeiCode) {
            $imeiCode = trim((string) $imeiCode);
            if ($imeiCode === '') {
                continue;
            }

            $imeiRecord = ProductImei::where('user_id', $ownerId)
                ->where('product_id', $productId)
                ->where('imei', $imeiCode)
                ->whereNull('sale_bill_id')
                ->first();

            if ($imeiRecord) {
                $imeiRecord->update([
                    'sale_bill_id' => $bill->id,
                    'sold_at' => now(),
                    'selling_price' => $sellingPrice,
                ]);
            } else {
                ProductImei::create([
                    'user_id' => $ownerId,
                    'product_id' => $productId,
                    'imei' => $imeiCode,
                    'sale_bill_id' => $bill->id,
                    'sold_at' => now(),
                    'selling_price' => $sellingPrice,
                    'purchased_at' => now()->toDateString(),
                ]);
            }

            $savedImeis[] = $imeiCode;
        }

        if ($savedImeis !== []) {
            $bill->products()->updateExistingPivot($productId, [
                'imeis' => json_encode($savedImeis),
            ]);
        }
    }

    private function calculateTagsTotal(?string $tagsString): float
    {
        if (! $tagsString) {
            return 0.0;
        }

        $total = 0.0;
        foreach (explode('&', $tagsString) as $pair) {
            if (! str_contains($pair, '@')) {
                continue;
            }

            $parts = explode('@', $pair);
            if (count($parts) === 2) {
                $total += (float) $parts[1];
            }
        }

        return $total;
    }
}
