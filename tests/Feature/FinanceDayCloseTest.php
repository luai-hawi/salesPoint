<?php

use App\Models\Bill;
use App\Models\CashMovement;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\SupplierPayment;
use App\Models\DayClosing;
use Tests\Support\Builds;

uses(Builds::class);

test('day close saves and updates a closing with the correct variance', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['cost_price' => 5, 'selling_price' => 15]);
    $supplier = $this->makeSupplier($owner);
    $employee = Employee::create(['shop_owner_id' => $owner->id, 'name' => 'Staff', 'monthly_salary' => 100]);

    CashMovement::create([
        'user_id' => $owner->id,
        'type' => 'opening',
        'amount' => 100,
        'reason' => 'Opening',
        'occurred_at' => now()->subDay(),
        'created_by' => $owner->id,
    ]);

    $bill = Bill::create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'total_price' => 60,
        'payment_method' => 'cash',
        'is_damaged' => false,
        'is_returned' => false,
    ]);
    $bill->products()->attach($product->id, ['quantity' => 1, 'cost_price' => 5, 'selling_price' => 15, 'discount' => 0]);

    Expense::create(['user_id' => $owner->id, 'title' => 'Utility', 'amount' => 15, 'expense_date' => now()->toDateString()]);
    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $owner->id,
        'amount' => 20,
        'type' => 'cash',
        'kind' => 'payment',
        'payment_date' => now()->toDateString(),
    ]);
    EmployeePayment::create([
        'employee_id' => $employee->id,
        'amount' => 5,
        'payment_date' => now()->toDateString(),
        'type' => 'cash',
    ]);

    $this->actingAs($owner)->post(route('finance.day-close.store'), [
        'closing_date' => now()->toDateString(),
        'counted_cash' => 120,
        'notes' => 'First close',
    ])->assertRedirect(route('finance.day-close.index', ['date' => now()->toDateString()]));

    $closing = DayClosing::withoutGlobalScopes()->where('user_id', $owner->id)->first();
    expect((float) $closing->expected_cash)->toBe(120.0)
        ->and((float) $closing->variance)->toBe(0.0);

    $this->actingAs($owner)->post(route('finance.day-close.store'), [
        'closing_date' => now()->toDateString(),
        'counted_cash' => 118,
        'notes' => 'Updated close',
    ])->assertRedirect();

    $closing->refresh();
    expect(DayClosing::withoutGlobalScopes()->where('user_id', $owner->id)->count())->toBe(1)
        ->and((float) $closing->counted_cash)->toBe(118.0)
        ->and((float) $closing->variance)->toBe(-2.0);
});

test('day close expected cash matches canonical cash drawer stream and ignores non-cash opening artifacts', function () {
    $owner = $this->makeOwner(['timezone' => 'Asia/Gaza']);
    $product = $this->makeProduct($owner, ['cost_price' => 5, 'selling_price' => 15]);
    $customer = $this->makeCustomer($owner);
    $supplier = $this->makeSupplier($owner);

    CashMovement::create([
        'user_id' => $owner->id,
        'type' => 'opening',
        'amount' => 100,
        'reason' => 'Opening',
        'occurred_at' => now()->subDay(),
        'created_by' => $owner->id,
    ]);

    $sale = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => 20, 'payment_method' => 'cash', 'is_returned' => false]);
    $sale->products()->attach($product->id, ['quantity' => 1, 'cost_price' => 5, 'selling_price' => 15, 'discount' => 0]);
    $return = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => -10, 'payment_method' => 'cash', 'is_returned' => true]);
    $return->products()->attach($product->id, ['quantity' => -1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);

    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => -4,
        'type' => 'cash',
        'kind' => 'adjustment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $owner->id,
        'amount' => -30,
        'type' => 'cash',
        'kind' => null,
        'note' => 'Initial balance',
        'payment_date' => now()->toDateString(),
    ]);

    $summary = app(\App\Services\Finance\FinanceInsightsService::class)->dayCloseSummary($owner->id, now()->toDateString());

    expect($summary['expected_cash'])->toBe(106.0)
        ->and($summary['cash_drawer']['closing_balance'])->toBe(106.0)
        ->and($summary['customer_refunds'])->toBe(4.0);
});
