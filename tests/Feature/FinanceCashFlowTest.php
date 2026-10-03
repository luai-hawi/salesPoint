<?php

use App\Models\Bill;
use App\Models\CashMovement;
use App\Models\CustomerPayment;
use App\Services\Finance\CashFlowService;
use Tests\Support\Builds;

uses(Builds::class);

function financeBill(\App\Models\User $owner, \App\Models\Product $product, array $attributes = [], array $pivot = []): Bill
{
    $bill = Bill::create(array_merge([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'total_price' => 0,
        'payment_method' => 'cash',
        'is_damaged' => false,
        'is_returned' => false,
    ], $attributes));

    $bill->products()->attach($product->id, array_merge([
        'quantity' => 1,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'discount' => 0,
    ], $pivot));

    return $bill;
}

test('cash flow service separates methods, ignores credit charges, keeps running balance and isolates tenants', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['cost_price' => 10, 'selling_price' => 20]);
    $otherProduct = $this->makeProduct($otherOwner);
    $customer = $this->makeCustomer($owner);

    CashMovement::create([
        'user_id' => $owner->id,
        'type' => 'opening',
        'amount' => 100,
        'reason' => 'Opening float',
        'occurred_at' => now()->subDay(),
        'created_by' => $owner->id,
    ]);

    financeBill($owner, $product, ['total_price' => 50, 'created_at' => now(), 'updated_at' => now(), 'payment_method' => 'cash']);
    financeBill($owner, $product, ['total_price' => 30, 'created_at' => now(), 'updated_at' => now(), 'payment_method' => 'card']);
    financeBill($otherOwner, $otherProduct, ['total_price' => 999, 'created_at' => now(), 'updated_at' => now(), 'payment_method' => 'cash']);

    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => -40,
        'type' => 'cash',
        'kind' => 'bill_charge',
        'note' => 'Bill #1 created as debt',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => 20,
        'type' => 'transfer',
        'kind' => 'payment',
        'note' => 'General collection',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => 15,
        'type' => 'cash',
        'kind' => null,
        'note' => 'Legacy payment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => -7,
        'type' => 'cash',
        'kind' => 'adjustment',
        'note' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CashMovement::create([
        'user_id' => $owner->id,
        'type' => 'out',
        'amount' => 10,
        'reason' => 'Bank deposit',
        'occurred_at' => now(),
        'created_by' => $owner->id,
    ]);

    $service = app(CashFlowService::class);
    $cash = $service->cashDrawerData($owner->id, now()->toDateString(), now()->toDateString(), 'cash');
    $all = $service->cashDrawerData($owner->id, now()->toDateString(), now()->toDateString(), 'all');

    expect($cash['has_running_balance'])->toBeTrue()
        ->and($cash['totals']['in'])->toBe(65.0)
        ->and($cash['totals']['out'])->toBe(17.0)
        ->and($cash['closing_balance'])->toBe(148.0)
        ->and(collect($cash['rows'])->where('category', 'collection')->count())->toBe(1)
        ->and(collect($cash['rows'])->where('category', 'customer_refund')->count())->toBe(1)
        ->and(collect($cash['rows'])->contains(fn ($row) => $row['document_label'] === '999'))->toBeFalse();

    expect($all['totals']['in'])->toBe(115.0)
        ->and($all['totals']['out'])->toBe(17.0)
        ->and($all['cash_closing_balance'])->toBe(148.0)
        ->and($all['closing_balance'])->toBeNull()
        ->and(collect($all['rows'])->where('method', 'card')->count())->toBe(1)
        ->and(collect($all['rows'])->where('method', 'transfer')->count())->toBe(1);
});

test('cash drawer page requires financial access and tier feature', function () {
    $owner = $this->makeOwner();
    $allowedEmployee = $this->makeEmployee($owner, ['view_financial']);
    $deniedEmployee = $this->makeEmployee($owner, []);
    $blockedOwner = $this->makeOwner(['blocked_features' => ['financial_dashboard']]);

    $this->actingAs($owner)->get(route('finance.cash-drawer.index'))->assertOk();
    $this->actingAs($allowedEmployee)->get(route('finance.cash-drawer.index'))->assertOk();
    $this->actingAs($deniedEmployee)->get(route('finance.cash-drawer.index'))->assertForbidden();
    $this->actingAs($blockedOwner)->get(route('finance.cash-drawer.index'))->assertForbidden();
});

test('cash drawer stores local datetime inputs in utc and dashboard custom period reuses main dates', function () {
    $owner = $this->makeOwner(['timezone' => 'Europe/Moscow']);
    $this->actingAs($owner)->post(route('finance.cash-drawer.store'), [
        'type' => 'in',
        'amount' => 10,
        'reason' => 'Petty cash',
        'occurred_at' => '2026-10-03T08:30',
        'note' => null,
    ])->assertRedirect();

    $movement = CashMovement::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();
    expect($movement->occurred_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-03 05:30');

    $response = $this->actingAs($owner)->get(route('dashboard.financial', [
        'cash_preset' => 'custom',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
    ]));

    expect($response->viewData('cashPeriod')['from_date'])->toBe('2026-10-01')
        ->and($response->viewData('cashPeriod')['to_date'])->toBe('2026-10-02');
});
