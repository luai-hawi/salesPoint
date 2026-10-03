<?php

use App\Models\Bill;
use App\Models\CustomerPayment;
use App\Models\InstallmentPlan;
use Tests\Support\Builds;

uses(Builds::class);

test('offline sync reports mixed success duplicates and failures without duplicating bills', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'selling_price' => 10]);

    $payload = [
        'bills' => [
            [
                'local_id' => 'b-1',
                'client_uuid' => 'offline-bill-1',
                'customer_id' => $customer->id,
                'product_ids' => [$product->id],
                'quantities' => [1],
                'discounts' => [0],
                'cost_prices' => [5],
                'selling_prices' => [10],
            ],
            [
                'local_id' => 'b-2',
                'client_uuid' => 'offline-bill-1',
                'customer_id' => $customer->id,
                'product_ids' => [$product->id],
                'quantities' => [1],
                'discounts' => [0],
                'cost_prices' => [5],
                'selling_prices' => [10],
            ],
            [
                'local_id' => 'b-3',
                'customer_id' => $customer->id,
                'paid_amount' => 30,
                'product_ids' => [$product->id],
                'quantities' => [1],
                'discounts' => [0],
                'cost_prices' => [5],
                'selling_prices' => [10],
            ],
        ],
    ];

    $response = $this->actingAs($owner)->postJson('/offline/sync', $payload);

    $response->assertOk()
        ->assertJsonPath('bills.results.0.success', true)
        ->assertJsonPath('bills.results.1.duplicate', true)
        ->assertJsonPath('bills.results.2.success', false);

    expect(Bill::withoutGlobalScopes()->count())->toBe(1);
});

test('offline sync can link payments and installment initial payments to a bill created in the same batch', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'selling_price' => 50]);

    $response = $this->actingAs($owner)->postJson('/offline/sync', [
        'bills' => [[
            'local_id' => 'local-bill',
            'client_uuid' => 'offline-bill-2',
            'customer_id' => $customer->id,
            'product_ids' => [$product->id],
            'quantities' => [2],
            'discounts' => [0],
            'cost_prices' => [10],
            'selling_prices' => [50],
        ]],
        'payments' => [[
            'local_id' => 'payment-1',
            'customer_id' => $customer->id,
            'amount' => 30,
            'type' => 'cash',
            'bill_id' => 'local-bill',
        ]],
        'installments' => [[
            'local_id' => 'inst-1',
            'bill_id' => 'local-bill',
            'customer_id' => $customer->id,
            'total_amount' => 20,
            'initial_payment' => 20,
            'payments' => [],
        ]],
    ]);

    // debug
    // fwrite(STDERR, json_encode($response->json(), JSON_PRETTY_PRINT) . PHP_EOL);

    $response->assertOk()
        ->assertJsonPath('payments.results.0.success', true)
        ->assertJsonPath('installments.results.0.success', true);

    $bill = Bill::withoutGlobalScopes()->where('client_uuid', 'offline-bill-2')->firstOrFail();
    $plan = InstallmentPlan::withoutGlobalScopes()->first();

    expect($plan->bill_id)->toBe($bill->id)
        ->and(CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->where('kind', 'bill_payment')->count())->toBe(2)
        ->and((float) $customer->fresh()->balance)->toBe(-50.0);
});

test('offline payment and installment replays are idempotent by local id', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'selling_price' => 50]);

    $this->actingAs($owner)->postJson('/offline/sync', [
        'bills' => [[
            'local_id' => 'local-bill',
            'customer_id' => $customer->id,
            'product_ids' => [$product->id],
            'quantities' => [1],
            'discounts' => [0],
            'cost_prices' => [10],
            'selling_prices' => [50],
        ]],
    ])->assertOk();

    $response = $this->actingAs($owner)->postJson('/offline/sync', [
        'payments' => [[
            'local_id' => 'payment-1',
            'customer_id' => $customer->id,
            'amount' => 10,
            'type' => 'cash',
        ]],
        'installments' => [[
            'local_id' => 'inst-1',
            'customer_id' => $customer->id,
            'total_amount' => 20,
            'payments' => [],
        ]],
    ]);

    $response->assertOk();
    $this->actingAs($owner)->postJson('/offline/sync', [
        'payments' => [[
            'local_id' => 'payment-1',
            'customer_id' => $customer->id,
            'amount' => 10,
            'type' => 'cash',
        ]],
        'installments' => [[
            'local_id' => 'inst-1',
            'customer_id' => $customer->id,
            'total_amount' => 20,
            'payments' => [],
        ]],
    ])->assertOk()
        ->assertJsonPath('payments.results.0.duplicate', true)
        ->assertJsonPath('installments.results.0.duplicate', true);

    expect(CustomerPayment::withoutGlobalScopes()->where('client_uuid', 'payment-1')->count())->toBe(1)
        ->and(InstallmentPlan::withoutGlobalScopes()->where('client_uuid', 'inst-1')->count())->toBe(1);
});

test('offline bill sync falls back to local id for bill idempotency', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'selling_price' => 25]);

    $payload = [
        'bills' => [[
            'local_id' => 'fallback-bill-1',
            'client_uuid' => null,
            'customer_id' => $customer->id,
            'product_ids' => [$product->id],
            'quantities' => [1],
            'discounts' => [0],
            'cost_prices' => [10],
            'selling_prices' => [25],
        ]],
    ];

    $this->actingAs($owner)->postJson('/offline/sync', $payload)
        ->assertOk()
        ->assertJsonPath('bills.results.0.duplicate', false);

    $this->actingAs($owner)->postJson('/offline/sync', $payload)
        ->assertOk()
        ->assertJsonPath('bills.results.0.duplicate', true);

    expect(Bill::withoutGlobalScopes()->where('client_uuid', 'fallback-bill-1')->count())->toBe(1);
});

test('offline dependent payment fails when referenced local bill was not created', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);

    $this->actingAs($owner)->postJson('/offline/sync', [
        'payments' => [[
            'local_id' => 'payment-2',
            'customer_id' => $customer->id,
            'amount' => 10,
            'type' => 'cash',
            'bill_id' => 'missing-local-bill',
        ]],
    ])->assertOk()
        ->assertJsonPath('payments.results.0.success', false)
        ->assertJsonPath('payments.results.0.reason_code', 'bill_reference_unresolved');
});

test('offline dependent installment fails when referenced local bill was not created', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);

    $this->actingAs($owner)->postJson('/offline/sync', [
        'installments' => [[
            'local_id' => 'inst-missing',
            'bill_id' => 'missing-local-bill',
            'customer_id' => $customer->id,
            'total_amount' => 20,
            'payments' => [],
        ]],
    ])->assertOk()
        ->assertJsonPath('installments.results.0.success', false)
        ->assertJsonPath('installments.results.0.reason_code', 'bill_reference_unresolved');

    expect(InstallmentPlan::withoutGlobalScopes()->count())->toBe(0);
});

test('offline bill sync hashes oversized fallback ids to fit bill client uuids', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'selling_price' => 15]);
    $localId = str_repeat('b', 100);

    $payload = [
        'bills' => [[
            'local_id' => $localId,
            'customer_id' => $customer->id,
            'product_ids' => [$product->id],
            'quantities' => [1],
            'discounts' => [0],
            'cost_prices' => [5],
            'selling_prices' => [15],
        ]],
    ];

    $this->actingAs($owner)->postJson('/offline/sync', $payload)
        ->assertOk()
        ->assertJsonPath('bills.results.0.duplicate', false);

    $this->actingAs($owner)->postJson('/offline/sync', $payload)
        ->assertOk()
        ->assertJsonPath('bills.results.0.duplicate', true);

    $bill = Bill::withoutGlobalScopes()->firstOrFail();

    expect(strlen((string) $bill->client_uuid))->toBe(64)
        ->and($bill->client_uuid)->toBe(hash('sha256', $localId));
});

test('offline payment and installment keys stay within 64 characters and remain idempotent', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $paymentLocalId = str_repeat('p', 100);
    $installmentLocalId = str_repeat('i', 64);

    $payload = [
        'payments' => [[
            'local_id' => $paymentLocalId,
            'customer_id' => $customer->id,
            'amount' => 10,
            'type' => 'cash',
        ]],
        'installments' => [[
            'local_id' => $installmentLocalId,
            'customer_id' => $customer->id,
            'total_amount' => 20,
            'initial_payment' => 5,
            'payments' => [],
        ]],
    ];

    $this->actingAs($owner)->postJson('/offline/sync', $payload)->assertOk();
    $this->actingAs($owner)->postJson('/offline/sync', $payload)
        ->assertOk()
        ->assertJsonPath('payments.results.0.duplicate', true)
        ->assertJsonPath('installments.results.0.duplicate', true);

    $payment = CustomerPayment::withoutGlobalScopes()
        ->where('client_uuid', hash('sha256', $paymentLocalId))
        ->firstOrFail();
    $initialPayment = CustomerPayment::withoutGlobalScopes()
        ->where('note', __('receivables.installment_initial_payment_general_note'))
        ->firstOrFail();
    $plan = InstallmentPlan::withoutGlobalScopes()->firstOrFail();

    expect($payment->client_uuid)->toBe(hash('sha256', $paymentLocalId))
        ->and(strlen((string) $payment->client_uuid))->toBe(64)
        ->and($plan->client_uuid)->toBe($installmentLocalId)
        ->and(strlen((string) $initialPayment->client_uuid))->toBe(64)
        ->and($initialPayment->client_uuid)->toBe(hash('sha256', $installmentLocalId . ':initial'))
        ->and(CustomerPayment::withoutGlobalScopes()->where('client_uuid', $payment->client_uuid)->count())->toBe(1)
        ->and(CustomerPayment::withoutGlobalScopes()->where('client_uuid', $initialPayment->client_uuid)->count())->toBe(1);
});
