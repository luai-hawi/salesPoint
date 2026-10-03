<?php

use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Services\StockIntake;
use App\Services\SupplierLedger;
use Tests\Support\Builds;

uses(Builds::class);

test('stock bought on credit becomes a supplier debt, not an expense', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner, ['quantity' => 20, 'cost_price' => 5]);

    $bill = app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 5],
    ], ['mode' => 'credit', 'supplier_id' => $supplier->id]);

    expect($bill)->toBeInstanceOf(PurchaseBill::class)
        ->and($bill->source)->toBe('stock_intake')
        ->and((float) $bill->total_amount)->toBe(100.0)
        ->and((float) $supplier->fresh()->balance)->toBe(100.0)
        ->and(SupplierPayment::withoutGlobalScopes()->where('supplier_id', $supplier->id)->count())->toBe(0)
        ->and($bill->products)->toHaveCount(1);
});

test('paying the supplier later reduces the debt', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 10],
    ], ['mode' => 'credit', 'supplier_id' => $supplier->id]);

    SupplierLedger::pay($supplier->fresh(), 40, 'transfer');

    expect((float) $supplier->fresh()->balance)->toBe(60.0);
});

test('a partial payment at intake leaves the rest owed and links the payment to the bill', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $bill = app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 10],
    ], ['mode' => 'partial', 'supplier_id' => $supplier->id, 'paid_amount' => 30, 'payment_method' => 'cash']);

    $payment = SupplierPayment::withoutGlobalScopes()->where('supplier_id', $supplier->id)->first();

    expect((float) $supplier->fresh()->balance)->toBe(70.0)
        ->and($payment->purchase_bill_id)->toBe($bill->id)
        ->and((float) $payment->amount)->toBe(30.0)
        ->and(SupplierLedger::billPaid($bill))->toBe(30.0);
});

test('paying cash without naming a supplier uses the built-in cash supplier and leaves no debt', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);

    $bill = app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 25],
    ], ['mode' => 'paid']);

    $walkIn = SupplierLedger::walkInSupplier($owner->id);

    expect($bill->supplier_id)->toBe($walkIn->id)
        ->and((float) $walkIn->fresh()->balance)->toBe(0.0)
        ->and(SupplierPayment::withoutGlobalScopes()->where('supplier_id', $walkIn->id)->sum('amount'))->toEqual(100);

    // the built-in supplier is created once per shop
    expect(SupplierLedger::walkInSupplier($owner->id)->id)->toBe($walkIn->id);
});

test('stock intake keeps legacy fallback behavior for unsupported payment methods', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $bill = app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 25],
    ], ['mode' => 'partial', 'supplier_id' => $supplier->id, 'paid_amount' => 20, 'payment_method' => 'other']);

    $payment = SupplierPayment::withoutGlobalScopes()->where('purchase_bill_id', $bill->id)->firstOrFail();

    expect($payment->type)->toBe('cash')
        ->and((float) $payment->amount)->toBe(20.0);
});

test('opening stock with no funding records nothing', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);

    $bill = app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 25],
    ], ['mode' => 'none']);

    expect($bill)->toBeNull()
        ->and(PurchaseBill::withoutGlobalScopes()->count())->toBe(0);
});

test('buying on credit requires a supplier', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);

    expect(fn () => app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1],
    ], ['mode' => 'credit']))->toThrow(InvalidArgumentException::class);
});

test('a supplier of another shop cannot be used', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $foreignSupplier = $this->makeSupplier($other);
    $product = $this->makeProduct($owner);

    expect(fn () => app(StockIntake::class)->record($owner, [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1],
    ], ['mode' => 'credit', 'supplier_id' => $foreignSupplier->id]))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('employees of a shop record intake against the shop owner', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['create_products']);
    $supplier = $this->makeSupplier($owner);
    $product = $this->makeProduct($owner);

    $bill = app(StockIntake::class)->record($employee, [
        ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 50],
    ], ['mode' => 'credit', 'supplier_id' => $supplier->id]);

    expect($bill->user_id)->toBe($owner->id)
        ->and($bill->created_by)->toBe($employee->id);
});
