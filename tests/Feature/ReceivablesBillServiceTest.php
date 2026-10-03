<?php

use App\Models\Bill;
use App\Models\CustomerPayment;
use App\Models\ProductImei;
use App\Services\CustomerLedger;
use Tests\Support\Builds;

uses(Builds::class);

function receivablesBillPayload($product, array $overrides = []): array
{
    $payload = [
        'product_ids' => [$product->id],
        'quantities' => [2],
        'discounts' => [0],
        'cost_prices' => [(float) $product->cost_price],
        'selling_prices' => [(float) $product->selling_price],
        'discount_types' => ['total'],
        'product_tags' => [null],
    ];

    return array_replace_recursive($payload, $overrides);
}

test('cash sale creates no customer ledger rows', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 10, 'cost_price' => 4, 'selling_price' => 10]);
    $product->batches()->create(['quantity' => 10, 'cost_price' => 4, 'user_id' => $owner->id]);

    $response = $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'payment_method' => 'cash',
    ]));

    $response->assertOk()->assertJson([
        'success' => true,
        'duplicate' => false,
        'paid' => 20,
        'due' => 0,
    ]);

    $bill = Bill::withoutGlobalScopes()->first();

    expect($bill->payment_method)->toBe('cash')
        ->and((float) $product->fresh()->quantity)->toBe(8.0)
        ->and(CustomerPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('credit sale and partial payment use linked bill ledger rows', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 12]);

    $response = $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'paid_amount' => 10,
        'payment_method' => 'card',
    ]));

    $response->assertOk()->assertJson([
        'paid' => 10,
        'due' => 14,
    ]);

    $bill = Bill::withoutGlobalScopes()->first();
    $summary = CustomerLedger::billSummary($bill);

    expect($summary['status'])->toBe('partial')
        ->and((float) $customer->fresh()->balance)->toBe(-14.0)
        ->and(CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->count())->toBe(2)
        ->and(CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->where('kind', 'bill_payment')->first()->type)->toBe('card');
});

test('overpay is rejected and stock plus ledger changes roll back', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 10]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'paid_amount' => 50,
    ]))->assertStatus(422)->assertJsonValidationErrors(['paid_amount']);

    expect(Bill::withoutGlobalScopes()->count())->toBe(0)
        ->and((float) $product->fresh()->quantity)->toBe(10.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(CustomerPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('returned bills stay out of the customer ledger and restore stock', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 5, 'cost_price' => 3, 'selling_price' => 7]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'is_returned' => true,
        'return_costs' => [3],
    ]))->assertOk();

    $bill = Bill::withoutGlobalScopes()->first();

    expect((bool) $bill->is_returned)->toBeTrue()
        ->and((float) $bill->total_price)->toBe(-14.0)
        ->and((float) $product->fresh()->quantity)->toBe(7.0)
        ->and(CustomerPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('restaurant bills do not move stock quantities', function () {
    $owner = $this->makeRestaurant();
    $product = $this->makeProduct($owner, ['quantity' => 10]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product))->assertOk();

    expect((float) $product->fresh()->quantity)->toBe(10.0);
});

test('damaged bills keep total at zero and do not create debt', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 9]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'is_damaged' => true,
    ]))->assertOk();

    $bill = Bill::withoutGlobalScopes()->first();

    expect((float) $bill->total_price)->toBe(0.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(CustomerPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('damaged tagged items still total zero and create no debt', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 9]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'is_damaged' => true,
        'product_tags' => ['tag@5'],
    ]))->assertOk()->assertJsonPath('bill.total_price', fn ($total) => (float) $total === 0.0);

    expect((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(CustomerPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('normal bill rejects negative quantities before any stock move', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 9]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'quantities' => [-10],
    ]))->assertStatus(422)->assertJsonValidationErrors(['product_ids']);

    expect((float) $product->fresh()->quantity)->toBe(10.0)
        ->and(Bill::withoutGlobalScopes()->count())->toBe(0);
});

test('bill creation marks supplied imeis against the sale bill', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['quantity' => 5]);
    ProductImei::create([
        'user_id' => $owner->id,
        'product_id' => $product->id,
        'imei' => 'IMEI-001',
        'purchased_at' => now()->toDateString(),
    ]);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        "imeis_product_{$product->id}" => ['IMEI-001'],
    ]))->assertOk();

    $bill = Bill::withoutGlobalScopes()->first();
    $imei = ProductImei::where('imei', 'IMEI-001')->first();

    expect($imei->sale_bill_id)->toBe($bill->id)
        ->and($bill->products()->first()->pivot->imeis)->toContain('IMEI-001');
});

test('client uuid makes bill creation idempotent', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 10]);

    $payload = receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'client_uuid' => 'bill-uuid-1',
        'paid_amount' => 5,
    ]);

    $first = $this->actingAs($owner)->postJson('/bills', $payload);
    $second = $this->actingAs($owner)->postJson('/bills', $payload);

    $first->assertOk()->assertJson(['duplicate' => false]);
    $second->assertOk()->assertJson(['duplicate' => true]);

    expect(Bill::withoutGlobalScopes()->count())->toBe(1)
        ->and(CustomerPayment::withoutGlobalScopes()->count())->toBe(2);
});

test('bill creation truncates oversized notes while preserving damaged and returned markers', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'selling_price' => 10]);
    $longNote = str_repeat('ن', 1000);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'note' => $longNote,
        'is_damaged' => true,
    ]))->assertOk();

    $damagedBill = Bill::withoutGlobalScopes()->orderByDesc('id')->firstOrFail();

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'note' => $longNote,
        'is_returned' => true,
        'return_costs' => [5],
    ]))->assertOk();

    $returnedBill = Bill::withoutGlobalScopes()->orderByDesc('id')->firstOrFail();
    $normal = app(\App\Services\BillService::class)->composeNote($longNote, false, false);

    expect(mb_strlen((string) $damagedBill->note))->toBeLessThanOrEqual(255)
        ->and($damagedBill->note)->toEndWith('Damaged Bill')
        ->and(mb_strlen((string) $returnedBill->note))->toBeLessThanOrEqual(255)
        ->and($returnedBill->note)->toEndWith('Returned Bill')
        ->and(mb_strlen($normal))->toBe(255);
});

test('bill updates also truncate oversized notes without dropping markers', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'selling_price' => 10]);
    $longNote = str_repeat('abc', 333);

    $this->actingAs($owner)->postJson('/bills', receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'is_damaged' => true,
    ]))->assertOk();

    $bill = Bill::withoutGlobalScopes()->firstOrFail();

    $this->actingAs($owner)->put(route('bills.update', $bill), array_merge(receivablesBillPayload($product, [
        'customer_id' => $customer->id,
        'note' => $longNote,
    ]), [
        'remove_products' => [],
        'new_product_id' => null,
        'new_quantity' => null,
        'new_discount' => null,
        'new_cost_price' => null,
        'new_selling_price' => null,
        'dynamic_product_ids' => [],
        'dynamic_quantities' => [],
        'dynamic_discounts' => [],
        'dynamic_cost_prices' => [],
        'dynamic_selling_prices' => [],
    ]))->assertRedirect(route('bills.show', $bill));

    expect(mb_strlen((string) $bill->fresh()->note))->toBeLessThanOrEqual(255)
        ->and($bill->fresh()->note)->toEndWith('Damaged Bill');
});
