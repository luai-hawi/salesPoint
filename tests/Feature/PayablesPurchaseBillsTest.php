<?php

use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Services\SupplierLedger;
use Tests\Support\Builds;

uses(Builds::class);

function purchaseBillPayload($supplier, $product, array $overrides = []): array
{
    $payload = [
        'supplier_id' => $supplier->id,
        'purchase_date' => now()->toDateString(),
        'reference_number' => 'REF-100',
        'notes' => 'Created in test',
        'product_ids' => [$product->id],
        'quantities' => [10],
        'unit_costs' => [10],
        "barcodes_{$product->id}" => [],
        "imeis_{$product->id}" => [],
        'paid_now' => 0,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'payment_note' => 'Initial payment',
    ];

    return array_replace_recursive($payload, $overrides);
}

test('purchase bill can be created with an immediate payment and stock average cost stays correct', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'cost_price' => 5]);

    $response = $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product, [
        'paid_now' => 40,
    ]));

    $response->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->with('payments')->first();
    expect((float) $bill->total_amount)->toBe(100.0)
        ->and($bill->payments)->toHaveCount(1)
        ->and((float) $supplier->fresh()->balance)->toBe(60.0)
        ->and((float) $product->fresh()->quantity)->toBe(20.0)
        ->and((float) $product->fresh()->cost_price)->toBe(7.5);
});

test('purchase bill can be created fully on account', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product))
        ->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->first();
    expect($bill)->not()->toBeNull()
        ->and((float) $supplier->fresh()->balance)->toBe(100.0)
        ->and(SupplierPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('purchase bill payment status filter is applied before pagination', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    foreach (range(1, 25) as $index) {
        $bill = PurchaseBill::withoutGlobalScopes()->create([
            'supplier_id' => $supplier->id,
            'user_id' => $owner->id,
            'purchase_date' => now()->subDays(30 - $index)->toDateString(),
            'reference_number' => 'REF-' . $index,
            'total_amount' => 50,
            'created_by' => $owner->id,
        ]);
        SupplierLedger::charge($supplier->fresh(), 50);
    }

    $targetBill = PurchaseBill::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $owner->id,
        'purchase_date' => now()->toDateString(),
        'reference_number' => 'MATCHED-BILL',
        'total_amount' => 80,
        'created_by' => $owner->id,
    ]);
    SupplierLedger::charge($supplier->fresh(), 80);
    SupplierLedger::pay($supplier->fresh(), 80, 'cash', 'Paid bill', now(), $targetBill);

    $this->actingAs($owner)->get(route('purchase-bills.index', ['payment_status' => 'paid']))
        ->assertOk()
        ->assertSee('MATCHED-BILL')
        ->assertDontSee('REF-1');
});

test('bill payments can be added and undone', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product))
        ->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->first();

    $this->actingAs($owner)->post(route('purchase-bills.payments.store', $bill), [
        'amount' => 30,
        'type' => 'transfer',
        'payment_date' => now()->toDateString(),
        'note' => 'Settlement',
    ])->assertRedirect(route('purchase-bills.show', $bill));

    $payment = SupplierPayment::withoutGlobalScopes()->first();
    expect((float) $supplier->fresh()->balance)->toBe(70.0);

    $this->actingAs($owner)->delete(route('purchase-bills.payments.destroy', [$bill, $payment]))
        ->assertRedirect(route('purchase-bills.show', $bill));

    expect((float) $supplier->fresh()->balance)->toBe(100.0)
        ->and(SupplierPayment::withoutGlobalScopes()->count())->toBe(0);
});

test('editing a bill below what was paid leaves supplier credit instead of dropping payments', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner, ['quantity' => 10, 'cost_price' => 5]);

    $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product, [
        'paid_now' => 80,
    ]))->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->with('payments')->first();

    $response = $this->actingAs($owner)->put(route('purchase-bills.update', $bill), purchaseBillPayload($supplier, $product, [
        'quantities' => [5],
        'unit_costs' => [10],
    ]));

    $response->assertRedirect(route('purchase-bills.show', $bill));
    $response->assertSessionHas('success');

    $bill->refresh()->load('payments');
    $summary = SupplierLedger::billSummary($bill);

    expect((float) $bill->total_amount)->toBe(50.0)
        ->and((float) $supplier->fresh()->balance)->toBe(-30.0)
        ->and($summary['overpaid'])->toBe(30.0)
        ->and($bill->payments)->toHaveCount(1);
});

test('deleting a bill with linked payments is blocked', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product, [
        'paid_now' => 25,
    ]))->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->first();

    $this->actingAs($owner)->delete(route('purchase-bills.destroy', $bill))
        ->assertSessionHas('error');

    expect(PurchaseBill::withoutGlobalScopes()->count())->toBe(1);
});

test('supplier payments keep the balance right and recent payment endpoints keep their json shape', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $bill = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => 50,
        'purchase_date' => now()->toDateString(),
        'created_by' => $owner->id,
    ]);
    $bill->user_id = $owner->id;
    $bill->save();
    SupplierLedger::charge($supplier, 50);

    $this->actingAs($owner)->post(route('suppliers.payments.store', $supplier), [
        'amount' => 20,
        'type' => 'cash',
        'payment_date' => now()->toDateString(),
        'purchase_bill_id' => $bill->id,
        'note' => 'Paid on account',
    ])->assertRedirect(route('suppliers.edit', $supplier));

    expect((float) $supplier->fresh()->balance)->toBe(30.0);

    $recent = $this->actingAs($owner)->getJson(route('suppliers.recent-payments', $supplier));
    $recent->assertOk()->assertJsonStructure([
        'payments' => [['id', 'amount', 'type', 'note', 'payment_date', 'created_at_human']],
        'last_bill_amount',
        'last_bill_id',
        'last_bill_date',
    ]);

    $more = $this->actingAs($owner)->getJson(route('suppliers.more-payments', $supplier) . '?offset=0&limit=10');
    $more->assertOk()->assertJsonStructure([
        'payments' => [['id', 'amount', 'type', 'note', 'payment_date', 'created_at_human']],
        'has_more',
    ]);

    $payment = SupplierPayment::withoutGlobalScopes()->first();
    $this->actingAs($owner)->delete(route('supplier-payments.destroy', $payment))
        ->assertSessionHas('success');

    expect((float) $supplier->fresh()->balance)->toBe(50.0);
});

test('legacy supplier payment rows with null kind still render on supplier pages', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'amount' => 10,
        'type' => 'cash',
        'kind' => null,
        'note' => 'Legacy row',
        'payment_date' => now()->toDateString(),
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)->get(route('suppliers.edit', $supplier))
        ->assertOk()
        ->assertSee('Legacy row');
});

test('legacy unlinked supplier payments settle the oldest bill first', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    $oldest = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => 100,
        'purchase_date' => now()->subDays(2)->toDateString(),
        'created_by' => $owner->id,
    ]);
    $oldest->user_id = $owner->id;
    $oldest->save();

    $newest = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => 50,
        'purchase_date' => now()->toDateString(),
        'created_by' => $owner->id,
    ]);
    $newest->user_id = $owner->id;
    $newest->save();

    SupplierLedger::charge($supplier, 100);
    SupplierLedger::charge($supplier->fresh(), 50);

    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'amount' => 100,
        'type' => 'cash',
        'kind' => null,
        'note' => 'Legacy general payment',
        'payment_date' => now()->toDateString(),
        'user_id' => $owner->id,
    ]);

    $oldSummary = SupplierLedger::billSummary($oldest);
    $newSummary = SupplierLedger::billSummary($newest);

    expect($oldSummary['status'])->toBe('paid')
        ->and($oldSummary['due'])->toBe(0.0)
        ->and($newSummary['status'])->toBe('unpaid')
        ->and($newSummary['due'])->toBe(50.0);

    $this->actingAs($owner)->post(route('purchase-bills.payments.store', $oldest), [
        'amount' => 1,
        'type' => 'cash',
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');
});

test('walk in supplier cannot be renamed or deleted', function () {
    $owner = $this->makeOwner();
    $walkIn = SupplierLedger::walkInSupplier($owner->id);

    $this->actingAs($owner)->put(route('suppliers.update', $walkIn), [
        'name' => 'Changed',
        'phone' => '',
        'email' => '',
        'address' => '',
        'notes' => '',
    ])->assertSessionHasErrors('name');

    $this->actingAs($owner)->delete(route('suppliers.destroy', $walkIn))
        ->assertSessionHas('error');
});

test('authorization and tenant isolation are enforced for payables routes', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $supplier = $this->makeSupplier($other);
    $product = $this->makeProduct($other);
    $employee = $this->makeEmployee($owner, ['view_suppliers']);

    $this->actingAs($employee)->post(route('suppliers.payments.store', $supplier), [
        'amount' => 10,
        'type' => 'cash',
        'payment_date' => now()->toDateString(),
    ])->assertNotFound();

    $this->actingAs($owner)->get(route('suppliers.edit', $supplier))->assertNotFound();

    $bill = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => 30,
        'purchase_date' => now()->toDateString(),
        'created_by' => $other->id,
    ]);
    $bill->user_id = $other->id;
    $bill->save();
    $bill->products()->attach($product->id, [
        'quantity' => 3,
        'unit_cost' => 10,
        'total_cost' => 30,
        'barcodes' => json_encode([]),
    ]);

    $this->actingAs($owner)->get(route('purchase-bills.show', $bill))->assertNotFound();
});

test('same tenant employees without permissions get 403 on protected payables endpoints', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, []);
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product, [
        'paid_now' => 10,
    ]))->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->first();
    $payment = SupplierPayment::withoutGlobalScopes()->first();

    $this->actingAs($employee)->getJson(route('suppliers.recent-payments', $supplier))->assertForbidden();
    $this->actingAs($employee)->getJson(route('suppliers.more-payments', $supplier) . '?offset=0&limit=10')->assertForbidden();
    $this->actingAs($employee)->get(route('suppliers.print-report', [
        'supplier' => $supplier,
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
        'report_type' => 'both',
    ]))->assertForbidden();
    $this->actingAs($employee)->getJson(route('api.suppliers.search'))->assertForbidden();
    $this->actingAs($employee)->delete(route('supplier-payments.destroy', $payment))->assertForbidden();
    $this->actingAs($employee)->getJson(route('api.purchase-bills.search'))->assertForbidden();
    $this->actingAs($employee)->post(route('purchase-bills.payments.store', $bill), [
        'amount' => 5,
        'type' => 'cash',
        'payment_date' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($employee)->delete(route('purchase-bills.payments.destroy', [$bill, $payment]))->assertForbidden();
    $this->actingAs($employee)->getJson(route('products.get-suppliers', ['product_id' => $product->id]))->assertForbidden();
});

test('supplier statement and printable purchase bill render', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $this->actingAs($owner)->post(route('purchase-bills.store'), purchaseBillPayload($supplier, $product))
        ->assertRedirect();

    $bill = PurchaseBill::withoutGlobalScopes()->first();

    $this->actingAs($owner)->get(route('purchase-bills.print', $bill))
        ->assertOk()
        ->assertSee((string) $bill->id);

    $this->actingAs($owner)->get(route('suppliers.print-report', [
        'supplier' => $supplier,
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
        'report_type' => 'both',
    ]))->assertOk()->assertSee($supplier->name);
});
