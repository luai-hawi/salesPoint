<?php

use App\Models\Bill;
use App\Models\CustomerPayment;
use App\Services\CustomerLedger;
use Tests\Support\Builds;

uses(Builds::class);

function ledgerBill($test, $owner, $customer, float $total): Bill
{
    $bill = new Bill([
        'total_price' => $total,
        'customer_id' => $customer->id,
        'created_by' => $owner->id,
    ]);
    $bill->user_id = $owner->id;
    $bill->save();

    return $bill;
}

test('a bill charged on account links its ledger row and moves the balance', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $bill = ledgerBill($this, $owner, $customer, 100);

    $row = CustomerLedger::chargeBill($bill);

    expect($row->bill_id)->toBe($bill->id)
        ->and($row->kind)->toBe('bill_charge')
        ->and((float) $row->amount)->toBe(-100.0)
        ->and((float) $customer->fresh()->balance)->toBe(-100.0);

    // charging twice never double counts
    CustomerLedger::chargeBill($bill);
    expect((float) $customer->fresh()->balance)->toBe(-100.0)
        ->and(CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->count())->toBe(1);
});

test('a partial payment at the counter only leaves the unpaid part as debt', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $bill = ledgerBill($this, $owner, $customer, 100);

    CustomerLedger::chargeBill($bill);
    CustomerLedger::receiveForBill($bill, 60, 'cash');

    expect((float) $customer->fresh()->balance)->toBe(-40.0);

    $summary = CustomerLedger::billSummary($bill);
    expect($summary['status'])->toBe('partial')
        ->and($summary['paid'])->toBe(60.0)
        ->and($summary['due'])->toBe(40.0);

    $open = CustomerLedger::openBills($customer->fresh());
    expect($open)->toHaveCount(1)
        ->and($open->first()['due'])->toBe(40.0);

    CustomerLedger::receiveForBill($bill, 40, 'card');
    expect(CustomerLedger::billSummary($bill)['status'])->toBe('paid')
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(CustomerLedger::openBills($customer->fresh()))->toHaveCount(0);
});

test('editing a bill total keeps the customer balance consistent', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $bill = ledgerBill($this, $owner, $customer, 100);
    CustomerLedger::chargeBill($bill);
    CustomerLedger::receiveForBill($bill, 30);

    $bill->total_price = 120;
    $bill->save();
    CustomerLedger::syncBillCharge($bill->fresh());

    expect((float) $customer->fresh()->balance)->toBe(-90.0);
});

test('deleting a bill removes its charge and payments and restores the balance', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $bill = ledgerBill($this, $owner, $customer, 100);
    CustomerLedger::chargeBill($bill);
    CustomerLedger::receiveForBill($bill, 60);

    $removed = CustomerLedger::removeBillRows($bill);

    expect($removed)->toBe(-40.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->count())->toBe(0);
});

test('rows saved before the bill link existed are still found by their note', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner, ['balance' => -100]);
    $bill = ledgerBill($this, $owner, $customer, 100);

    $legacy = new CustomerPayment([
        'customer_id' => $customer->id,
        'amount' => -100,
        'type' => 'cash',
        'note' => "Bill #{$bill->id} created as debt",
        'user_id' => $owner->id,
    ]);
    $legacy->save();

    expect(CustomerLedger::chargeRow($bill)?->id)->toBe($legacy->id);

    $removed = CustomerLedger::removeBillRows($bill);

    expect($removed)->toBe(-100.0)
        ->and((float) $customer->fresh()->balance)->toBe(0.0)
        ->and(CustomerPayment::withoutGlobalScopes()->whereKey($legacy->id)->exists())->toBeFalse();
});

test('open bill debt is spread newest first over the real customer debt', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $old = ledgerBill($this, $owner, $customer, 100);
    $old->created_at = now()->subDays(5);
    $old->save();
    $new = ledgerBill($this, $owner, $customer, 50);

    CustomerLedger::chargeBill($old);
    CustomerLedger::chargeBill($new);
    // a general payment that is not linked to any bill
    CustomerLedger::receive($customer->fresh(), 120);

    $open = CustomerLedger::openBills($customer->fresh());

    // customer still owes 30 in total: it belongs to the newest bill first
    expect((float) $customer->fresh()->balance)->toBe(-30.0)
        ->and($open)->toHaveCount(1)
        ->and($open->first()['bill']->id)->toBe($new->id)
        ->and($open->first()['due'])->toBe(30.0);
});

test('legacy general payments settle the oldest charged bill before later bill specific payments', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $old = ledgerBill($this, $owner, $customer, 100);
    $old->created_at = now()->subDays(2);
    $old->save();
    $new = ledgerBill($this, $owner, $customer, 50);

    CustomerLedger::chargeBill($old);
    CustomerLedger::chargeBill($new);
    CustomerLedger::receive($customer->fresh(), 100, 'cash', 'Legacy settlement');

    expect(CustomerLedger::billSummary($old)['status'])->toBe('paid')
        ->and(CustomerLedger::billSummary($new)['status'])->toBe('unpaid');

    expect(fn () => CustomerLedger::receiveForBill($old, 1))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('legacy bill payment notes stay attached to the named bill at runtime', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner, ['balance' => -100]);
    $old = ledgerBill($this, $owner, $customer, 100);
    $old->created_at = now()->subDays(2);
    $old->save();
    $new = ledgerBill($this, $owner, $customer, 20);

    CustomerPayment::withoutGlobalScopes()->insert([
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => -100,
            'type' => 'cash',
            'kind' => null,
            'note' => "Bill #{$old->id} created as debt",
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ],
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => -20,
            'type' => 'cash',
            'kind' => null,
            'note' => "Bill #{$new->id} created as debt",
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ],
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => 20,
            'type' => 'cash',
            'kind' => 'payment',
            'note' => "Payment for bill #{$new->id}",
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(CustomerLedger::billSummary($old)['status'])->toBe('unpaid')
        ->and(CustomerLedger::billSummary($new)['status'])->toBe('paid');

    $removed = CustomerLedger::removeBillRows($new);

    expect($removed)->toBe(0.0)
        ->and((float) $customer->fresh()->balance)->toBe(-100.0)
        ->and(CustomerPayment::withoutGlobalScopes()->where('note', "Payment for bill #{$new->id}")->exists())->toBeFalse();
});

test('bill link migration backfills legacy bill payment notes safely', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $otherCustomer = $this->makeCustomer($owner);
    $bill = ledgerBill($this, $owner, $customer, 100);
    $otherBill = ledgerBill($this, $owner, $otherCustomer, 50);

    CustomerPayment::withoutGlobalScopes()->insert([
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => -100,
            'type' => 'cash',
            'kind' => null,
            'note' => "Bill #{$bill->id} created as debt",
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ],
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => 30,
            'type' => 'cash',
            'kind' => null,
            'note' => "Counter payment for bill #{$bill->id}",
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ],
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => 25,
            'type' => 'cash',
            'kind' => null,
            'note' => "Installment initial payment for bill #{$bill->id}",
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'amount' => 15,
            'type' => 'cash',
            'kind' => null,
            'note' => "Payment for bill #{$otherBill->id}",
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration = require base_path('database/migrations/2026_10_04_000200_add_bill_link_to_customer_payments.php');
    $migration->up();

    $linkedKinds = CustomerPayment::withoutGlobalScopes()
        ->whereIn('note', [
            "Counter payment for bill #{$bill->id}",
            "Installment initial payment for bill #{$bill->id}",
        ])
        ->orderBy('id')
        ->get(['bill_id', 'kind'])
        ->map(fn ($row) => ['bill_id' => $row->bill_id, 'kind' => $row->kind])
        ->all();

    expect($linkedKinds)->toBe([
        ['bill_id' => $bill->id, 'kind' => 'bill_payment'],
        ['bill_id' => $bill->id, 'kind' => 'bill_payment'],
    ])
        ->and(CustomerPayment::withoutGlobalScopes()->where('note', "Payment for bill #{$otherBill->id}")->first()?->bill_id)->toBeNull();
});

test('legacy unlinked rows still allocate oldest first for pre-upgrade settled bills', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner, ['balance' => -50]);
    $old = ledgerBill($this, $owner, $customer, 100);
    $old->created_at = now()->subDays(2);
    $old->save();
    $new = ledgerBill($this, $owner, $customer, 50);

    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => -100,
        'type' => 'cash',
        'note' => "Bill #{$old->id} created as debt",
    ]);

    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => -50,
        'type' => 'cash',
        'note' => "Bill #{$new->id} created as debt",
    ]);

    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => 100,
        'type' => 'cash',
        'note' => 'Legacy general payment',
    ]);

    expect(CustomerLedger::billSummary($old)['status'])->toBe('paid')
        ->and(CustomerLedger::billSummary($new)['due'])->toBe(50.0);

    expect(fn () => CustomerLedger::receiveForBill($old, 1))->toThrow(\Illuminate\Validation\ValidationException::class);
});
