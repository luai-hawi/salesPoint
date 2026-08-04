<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
* Batch-syncs offline data (bills, customer payments, installment plans).
* Security: ownership is resolved entirely from the authenticated session;
* client-supplied userId/ownerId is never trusted.
*/
class OfflineSyncController extends Controller
{
public function sync(Request $request): JsonResponse
{
$user = auth()->user();
$ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

if ($user->role === 'employee' && ! $user->hasPermission('create_bills')) {
return response()->json(['error' => 'Unauthorized'], 403);
}

$validated = $request->validate([
// Bills
'bills' => 'nullable|array|max:100',
'bills.*.local_id' => 'required|string|max:100',
'bills.*.product_ids' => 'required|array|min:1',
'bills.*.product_ids.*' => 'required|integer|min:1',
'bills.*.quantities' => 'required|array',
'bills.*.quantities.*' => 'required|numeric',
'bills.*.discounts' => 'required|array',
'bills.*.discounts.*' => 'nullable|numeric',
'bills.*.cost_prices' => 'required|array',
'bills.*.cost_prices.*' => 'nullable|numeric',
'bills.*.selling_prices' => 'required|array',
'bills.*.selling_prices.*' => 'nullable|numeric',
'bills.*.discount_types' => 'nullable|array',
'bills.*.discount_types.*' => 'nullable|string|in:total,per-unit',
'bills.*.product_tags' => 'nullable|array',
'bills.*.product_tags.*' => 'nullable|string|max:500',
'bills.*.return_costs' => 'nullable|array',
'bills.*.return_costs.*' => 'nullable|numeric',
'bills.*.customer_id' => 'nullable|integer',
'bills.*.note' => 'nullable|string|max:1000',
'bills.*.bill_date' => 'nullable|date',
'bills.*.is_damaged' => 'nullable|boolean',
'bills.*.is_returned' => 'nullable|boolean',

// Customer payments
'payments' => 'nullable|array|max:200',
'payments.*.local_id' => 'required|string|max:100',
'payments.*.customer_id' => 'required|integer|min:1',
'payments.*.amount' => 'required|numeric',
'payments.*.type' => 'required|string|in:cash,card,transfer,check',
'payments.*.note' => 'nullable|string|max:255',
'payments.*.payment_date' => 'nullable|date',

// Installment plans
'installments' => 'nullable|array|max:100',
'installments.*.local_id' => 'required|string|max:100',
'installments.*.bill_id' => 'nullable|integer',
'installments.*.customer_id' => 'required|integer|min:1',
'installments.*.total_amount' => 'required|numeric|min:0.01',
'installments.*.initial_payment' => 'nullable|numeric|min:0',
'installments.*.note' => 'nullable|string|max:1000',
'installments.*.payments' => 'nullable|array',
'installments.*.payments.*.due_date' => 'nullable|date',
'installments.*.payments.*.amount' => 'nullable|numeric|min:0.01',
'installments.*.payments.*.note' => 'nullable|string|max:500',
]);

$billResults = $this->syncBills($validated['bills'] ?? [], $user, $ownerId);
$paymentResults = $this->syncPayments($validated['payments'] ?? [], $user, $ownerId);
$installmentResults = $this->syncInstallments($validated['installments'] ?? [], $user, $ownerId);

return response()->json([
'bills' => [
'synced' => count(array_filter($billResults, fn ($r) => $r['success'])),
'results' => $billResults,
],
'payments' => [
'synced' => count(array_filter($paymentResults, fn ($r) => $r['success'])),
'results' => $paymentResults,
],
'installments' => [
'synced' => count(array_filter($installmentResults, fn ($r) => $r['success'])),
'results' => $installmentResults,
],
]);
}

// ── Bills ──────────────────────────────────────────────────────────────
private function syncBills(array $items, $user, int $ownerId): array
{
$results = [];
foreach ($items as $item) {
try {
$bill = DB::transaction(fn () => $this->createBill($item, $user, $ownerId));
$results[] = ['local_id' => $item['local_id'], 'success' => true, 'bill_id' => $bill->id];
} catch (\Throwable $e) {
$results[] = ['local_id' => $item['local_id'], 'success' => false, 'error' => $e->getMessage()];
}
}
return $results;
}

private function createBill(array $data, $user, int $ownerId): Bill
{
$isDamaged = (bool) ($data['is_damaged'] ?? false);
$isReturned = (bool) ($data['is_returned'] ?? false);
$noteText = $data['note'] ?? '';

if ($isDamaged) { $noteText .= ($noteText ? ' - ' : '') . 'Damaged Bill'; }
if ($isReturned) { $noteText .= ($noteText ? ' - ' : '') . 'Returned Bill'; }

$billDate = ! empty($data['bill_date'])
? \Carbon\Carbon::parse($data['bill_date'])->setTime(now()->hour, now()->minute, now()->second)
: now();

$customerId = null;
if (! empty($data['customer_id'])) {
$c = Customer::where('id', $data['customer_id'])->where('user_id', $ownerId)->first();
if ($c) $customerId = $c->id;
}

$bill = new Bill([
'note' => $noteText,
'total_price' => 0,
'customer_id' => $customerId,
'user_id' => $ownerId,
'created_by' => $user->id,
'is_damaged' => $isDamaged,
'is_returned' => $isReturned,
]);
$bill->created_at = $billDate;
$bill->updated_at = $billDate;
$bill->save();

$isRestaurantRole = $user->role === 'restaurant' ||
($user->role === 'employee' && optional($user->shopOwner)->role === 'restaurant');

$productIds = $data['product_ids'];
$quantities = $data['quantities'];
$discounts = $data['discounts'] ?? [];
$costPrices = $data['cost_prices'] ?? [];
$sellingPrices = $data['selling_prices'] ?? [];
$discountTypes = $data['discount_types'] ?? [];
$productTags = $data['product_tags'] ?? [];
$returnCosts = $data['return_costs'] ?? [];
$total = 0;

foreach ($productIds as $idx => $productId) {
if (empty($productId)) continue;

$qty = (float) ($quantities[$idx] ?? 1);
$costPrice = (float) ($costPrices[$idx] ?? 0);
$sellingPrice = (float) ($sellingPrices[$idx] ?? 0);
$discountType = $discountTypes[$idx] ?? 'total';
$tags = $productTags[$idx] ?? null;
$discount = $isDamaged ? ($qty * $sellingPrice) : (float) ($discounts[$idx] ?? 0);

if ($isReturned) {
if (! empty($returnCosts[$idx]) && $returnCosts[$idx] !== '') {
$costPrice = (float) $returnCosts[$idx];
}
$qty = -1 * abs($qty);
$discount = -1 * abs($discount);
}

if ($discountType === 'per-unit' && ! $isDamaged) {
$discount = $discount * abs($qty);
}

$product = Product::where('id', $productId)->where('user_id', $ownerId)->firstOrFail();

if (! $isRestaurantRole) {
$product->quantity -= $qty;
$product->last_sale_date = now();
$product->save();

$remaining = $qty;
if ($remaining > 0) {
foreach ($product->batches()->where('quantity', '>', 0)->orderBy('created_at')->get() as $batch) {
if ($remaining <= 0) break;
    $consume=min($batch->quantity, $remaining);
    $batch->quantity -= $consume;
    $batch->save();
    $remaining -= $consume;
    }
    if ($remaining > 0) {
    $last = $product->batches()->latest()->first();
    if ($last) { $last->quantity -= $remaining; $last->save(); }
    else { $product->batches()->create(['quantity' => -1 * $remaining, 'cost_price' => $product->cost_price, 'user_id' => $ownerId]); }
    }
    }
    }

    $tagsExtra = $this->calcTagsTotal($tags);
    $lineTotal = ($sellingPrice * $qty) - $discount + ($tagsExtra * $qty);

    $bill->products()->attach($productId, [
    'quantity' => $qty,
    'discount' => $discount,
    'cost_price' => $costPrice,
    'selling_price' => $sellingPrice,
    'tags' => $tags,
    'imeis' => null,
    ]);

    $total += $isReturned ? $lineTotal : max(0, $lineTotal);
    }

    $bill->update(['total_price' => $total]);

    if ($bill->customer_id && $total > 0) {
    $bill->customer->payments()->create([
    'amount' => -1 * $total,
    'type' => 'cash',
    'note' => "Bill #{$bill->id} (offline sync)",
    'user_id' => $ownerId,
    ]);
    $bill->customer->update(['balance' => $bill->customer->balance - $total]);
    }

    return $bill;
    }

    // ── Customer Payments ──────────────────────────────────────────────────
    private function syncPayments(array $items, $user, int $ownerId): array
    {
    $results = [];
    foreach ($items as $item) {
    try {
    DB::transaction(function () use ($item, $ownerId) {
    $customer = Customer::where('id', $item['customer_id'])
    ->where('user_id', $ownerId)
    ->firstOrFail();

    $payment = new CustomerPayment([
    'amount' => $item['amount'],
    'type' => $item['type'],
    'note' => $item['note'] ?? null,
    'user_id' => $ownerId,
    ]);

    if (! empty($item['payment_date'])) {
    $payment->created_at = $item['payment_date'] . ' ' . now()->format('H:i:s');
    }

    $payment->customer()->associate($customer);
    $payment->save();

    $customer->balance += $item['amount'];
    $customer->save();
    });

    $results[] = ['local_id' => $item['local_id'], 'success' => true];
    } catch (\Throwable $e) {
    $results[] = ['local_id' => $item['local_id'], 'success' => false, 'error' => $e->getMessage()];
    }
    }
    return $results;
    }

    // ── Installment Plans ──────────────────────────────────────────────────
    private function syncInstallments(array $items, $user, int $ownerId): array
    {
    // Installments require the installments tier feature — silently skip if unavailable
    if (! $user->canAccessFeature('installments')) {
    return array_map(fn ($i) => [
    'local_id' => $i['local_id'],
    'success' => false,
    'error' => 'Installments feature not available on this account',
    ], $items);
    }

    if ($user->role === 'employee' && ! $user->hasPermission('create_installments')) {
    return array_map(fn ($i) => [
    'local_id' => $i['local_id'],
    'success' => false,
    'error' => 'Unauthorized',
    ], $items);
    }

    $results = [];
    foreach ($items as $item) {
    try {
    DB::transaction(function () use ($item, $user, $ownerId) {
    // Verify customer
    $customer = Customer::where('id', $item['customer_id'])
    ->where('user_id', $ownerId)
    ->firstOrFail();

    // Verify bill if provided
    $billId = null;
    if (! empty($item['bill_id'])) {
    $bill = Bill::where('id', $item['bill_id'])->where('user_id', $ownerId)->firstOrFail();
    $billId = $bill->id;
    }

    $initial = (float) ($item['initial_payment'] ?? 0);

    $plan = InstallmentPlan::create([
    'user_id' => $ownerId,
    'customer_id' => $customer->id,
    'bill_id' => $billId,
    'total_amount' => $item['total_amount'],
    'initial_payment' => $initial,
    'note' => $item['note'] ?? null,
    'is_standalone' => $billId === null,
    'created_by' => $user->id,
    ]);

    foreach (($item['payments'] ?? []) as $p) {
    if (empty($p['due_date']) || empty($p['amount'])) continue;
    InstallmentPayment::create([
    'installment_plan_id' => $plan->id,
    'user_id' => $ownerId,
    'amount' => $p['amount'],
    'due_date' => $p['due_date'],
    'note' => $p['note'] ?? null,
    ]);
    }

    if ($initial > 0) {
    CustomerPayment::create([
    'customer_id' => $customer->id,
    'amount' => $initial,
    'type' => 'cash',
    'note' => "Installment initial payment (offline sync)",
    'user_id' => $ownerId,
    ]);
    $customer->balance += $initial;
    $customer->save();
    }
    });

    $results[] = ['local_id' => $item['local_id'], 'success' => true];
    } catch (\Throwable $e) {
    $results[] = ['local_id' => $item['local_id'], 'success' => false, 'error' => $e->getMessage()];
    }
    }
    return $results;
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    private function calcTagsTotal(?string $tagsString): float
    {
    if (! $tagsString) return 0.0;
    $total = 0.0;
    foreach (explode('&', $tagsString) as $pair) {
    if (str_contains($pair, '@')) {
    $parts = explode('@', $pair, 2);
    if (count($parts) === 2) $total += (float) $parts[1];
    }
    }
    return $total;
    }
    }