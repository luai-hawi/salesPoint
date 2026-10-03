<?php

use App\Models\PurchaseBill;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierLedger;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Builds;

uses(Builds::class);

function supplierBill($owner, $supplier, float $total, ?string $date = null): PurchaseBill
{
    $bill = new PurchaseBill([
        'supplier_id' => $supplier->id,
        'total_amount' => $total,
        'purchase_date' => $date ?? now()->toDateString(),
        'created_by' => $owner->id,
    ]);
    $bill->user_id = $owner->id;
    $bill->save();

    return $bill;
}

test('opening balances are ignored as cash movements while recalc keeps the supplier balance correct', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    SupplierLedger::recordOpeningBalance($supplier, 100);
    $bill = supplierBill($owner, $supplier, 80);
    SupplierLedger::charge($supplier, 80);
    SupplierLedger::pay($supplier->fresh(), 30, 'cash', null, now(), $bill);

    expect((float) $supplier->fresh()->balance)->toBe(150.0);

    $openBills = SupplierLedger::openBills($supplier->fresh());
    expect($openBills)->toHaveCount(1)
        ->and($openBills->first()['due'])->toBe(50.0);

    $supplier->update(['balance' => 999]);
    expect(SupplierLedger::recalcBalance($supplier->fresh()))->toBe(150.0)
        ->and((float) $supplier->fresh()->balance)->toBe(150.0);
});

test('open supplier bills allocate newest first over the real outstanding bill debt', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    $old = supplierBill($owner, $supplier, 100, now()->subDays(5)->toDateString());
    $new = supplierBill($owner, $supplier, 50);
    SupplierLedger::charge($supplier, 100);
    SupplierLedger::charge($supplier->fresh(), 50);
    SupplierLedger::pay($supplier->fresh(), 120);

    $open = SupplierLedger::openBills($supplier->fresh());

    expect((float) $supplier->fresh()->balance)->toBe(30.0)
        ->and($open)->toHaveCount(1)
        ->and($open->first()['bill']->id)->toBe($new->id)
        ->and($open->first()['due'])->toBe(30.0);
});

test('bill summary reports due and overpaid amounts', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $bill = supplierBill($owner, $supplier, 100);
    SupplierLedger::charge($supplier, 100);
    SupplierLedger::pay($supplier->fresh(), 120, 'cash', null, now(), $bill);

    $summary = SupplierLedger::billSummary($bill);

    expect($summary['paid'])->toBe(100.0)
        ->and($summary['due'])->toBe(0.0)
        ->and($summary['overpaid'])->toBe(20.0)
        ->and($summary['status'])->toBe('paid');
});

test('bill linked pay calls are stored with bill payment kind', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $bill = supplierBill($owner, $supplier, 40);
    SupplierLedger::charge($supplier, 40);

    $payment = SupplierLedger::pay($supplier->fresh(), 15, 'cash', null, now(), $bill);

    expect($payment->fresh()->kind)->toBe(SupplierLedger::KIND_BILL_PAYMENT);
});

test('deleting the same supplier payment twice only reverses the balance once', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);
    $bill = supplierBill($owner, $supplier, 60);
    SupplierLedger::charge($supplier, 60);
    $payment = SupplierLedger::pay($supplier->fresh(), 25, 'cash', 'First delete', now(), $bill);

    expect((float) $supplier->fresh()->balance)->toBe(35.0);

    SupplierLedger::deletePayment($payment);
    SupplierLedger::deletePayment($payment);

    expect((float) $supplier->fresh()->balance)->toBe(60.0)
        ->and(SupplierPayment::withoutGlobalScopes()->whereKey($payment->id)->exists())->toBeFalse();
});

test('supplier recalc command supports dry run and write mode', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner, ['balance' => 999]);
    $bill = supplierBill($owner, $supplier, 70);
    SupplierLedger::charge($supplier, 70);
    SupplierLedger::pay($supplier->fresh(), 20, 'cash', null, now(), $bill);
    $supplier->update(['balance' => 999]);

    $this->artisan('suppliers:recalc-balances --dry-run --owner=' . $owner->id)
        ->expectsOutputToContain("supplier={$supplier->id}")
        ->assertSuccessful();

    expect((float) $supplier->fresh()->balance)->toBe(999.0);

    $this->artisan('suppliers:recalc-balances --owner=' . $owner->id)
        ->expectsOutputToContain('Recalculated')
        ->assertSuccessful();

    expect((float) $supplier->fresh()->balance)->toBe(50.0);
});

test('supplier payment kind backfill classifies legacy rows safely', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    SupplierPayment::withoutGlobalScopes()->insert([
        [
            'supplier_id' => $supplier->id,
            'amount' => 40,
            'type' => 'cash',
            'kind' => null,
            'note' => 'Initial balance',
            'payment_date' => now()->toDateString(),
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'supplier_id' => $supplier->id,
            'amount' => 25,
            'type' => 'cash',
            'kind' => null,
            'note' => 'Manual payment',
            'payment_date' => now()->toDateString(),
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'supplier_id' => $supplier->id,
            'amount' => -5,
            'type' => 'cash',
            'kind' => null,
            'note' => 'Refund',
            'payment_date' => now()->toDateString(),
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration = require base_path('database/migrations/2026_10_12_000100_backfill_supplier_payment_kinds.php');
    $migration->up();

    $kinds = SupplierPayment::withoutGlobalScopes()->orderBy('id')->pluck('kind')->all();
    expect($kinds)->toBe(['opening_balance', 'payment', 'refund']);
});

test('accounting links migration backfills supplier kinds in chunks and keeps columns on down', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner);

    SupplierPayment::withoutGlobalScopes()->insert([
        [
            'supplier_id' => $supplier->id,
            'amount' => 10,
            'type' => 'cash',
            'kind' => null,
            'note' => 'Initial balance',
            'payment_date' => now()->toDateString(),
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'supplier_id' => $supplier->id,
            'amount' => -3,
            'type' => 'cash',
            'kind' => null,
            'note' => 'Legacy refund',
            'payment_date' => now()->toDateString(),
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration = require base_path('database/migrations/2026_10_04_000300_add_accounting_links.php');
    $migration->up();

    expect(SupplierPayment::withoutGlobalScopes()->orderBy('id')->pluck('kind')->all())->toBe(['opening_balance', 'refund']);

    $migration->down();

    expect(Schema::hasColumn('supplier_payments', 'purchase_bill_id'))->toBeTrue()
        ->and(Schema::hasColumn('supplier_payments', 'kind'))->toBeTrue()
        ->and(Schema::hasColumn('purchase_bills', 'source'))->toBeTrue();
});

test('legacy null kind opening rows are treated as opening balances during recalculation', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner, ['balance' => 0]);

    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'amount' => 100,
        'type' => 'cash',
        'kind' => null,
        'note' => 'Initial balance',
        'payment_date' => now()->toDateString(),
        'user_id' => $owner->id,
    ]);

    $this->artisan('suppliers:recalc-balances --owner=' . $owner->id)->assertSuccessful();

    expect((float) $supplier->fresh()->balance)->toBe(100.0);
});

test('walk in supplier unique migration keeps the oldest supplier and repoints children', function () {
    $owner = $this->makeOwner();
    $migration = require base_path('database/migrations/2026_10_12_000101_enforce_unique_walk_in_suppliers.php');
    $migration->down();

    $first = Supplier::withoutGlobalScopes()->create([
        'name' => 'Walk-in 1',
        'balance' => 15,
        'user_id' => $owner->id,
        'system_key' => SupplierLedger::WALK_IN_KEY,
    ]);
    $second = Supplier::withoutGlobalScopes()->create([
        'name' => 'Walk-in 2',
        'balance' => 40,
        'user_id' => $owner->id,
        'system_key' => SupplierLedger::WALK_IN_KEY,
    ]);

    $bill = supplierBill($owner, $second, 30);
    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $second->id,
        'amount' => 10,
        'type' => 'cash',
        'kind' => 'payment',
        'note' => 'Duplicate supplier payment',
        'payment_date' => now()->toDateString(),
        'user_id' => $owner->id,
    ]);

    $migration->up();

    expect(Supplier::withoutGlobalScopes()->where('user_id', $owner->id)->where('system_key', SupplierLedger::WALK_IN_KEY)->count())->toBe(1)
        ->and(PurchaseBill::withoutGlobalScopes()->find($bill->id)->supplier_id)->toBe($first->id)
        ->and(SupplierPayment::withoutGlobalScopes()->first()->supplier_id)->toBe($first->id)
        ->and((float) $first->fresh()->balance)->toBe(55.0);
});
