<?php

use App\Models\Bill;
use App\Models\CapitalEntry;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\PurchaseBill;
use App\Models\SupplierPayment;
use App\Services\CustomerLedger;
use App\Services\Finance\FinanceInsightsService;
use Tests\Support\Builds;

uses(Builds::class);

function extractSelectColumnNames(string $sql): array
{
    if (! preg_match('/select\s+(.*?)\s+from\s/si', $sql, $matches)) {
        return [];
    }

    $select = trim($matches[1]);
    if ($select === '*') {
        return ['*'];
    }

    $parts = preg_split('/\s*,\s*/', $select) ?: [];

    return array_map(function (string $part) {
        $part = trim($part);
        if (preg_match('/\s+as\s+[`"]?([^`"\s]+)[`"]?/i', $part, $alias)) {
            return strtolower($alias[1]);
        }

        $segments = preg_split('/\./', preg_replace('/[`"]/', '', $part)) ?: [$part];

        return strtolower((string) end($segments));
    }, $parts);
}

test('profit and loss uses discounts returns damaged loss and payroll correctly', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner, ['cost_price' => 5, 'selling_price' => 10, 'quantity' => 4]);
    $employee = Employee::create(['shop_owner_id' => $owner->id, 'name' => 'Staff', 'monthly_salary' => 100]);

    $sale = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => 18, 'is_damaged' => false, 'is_returned' => false]);
    $sale->products()->attach($product->id, ['quantity' => 2, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 2]);

    $return = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => -10, 'is_damaged' => false, 'is_returned' => true]);
    $return->products()->attach($product->id, ['quantity' => -1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);

    $damaged = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => 0, 'is_damaged' => true, 'is_returned' => false]);
    $damaged->products()->attach($product->id, ['quantity' => 1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);

    $this->actingAs($owner);
    Expense::create(['user_id' => $owner->id, 'title' => 'Rent', 'category' => 'rent', 'amount' => 3, 'expense_date' => now()->toDateString()]);
    EmployeePayment::create(['employee_id' => $employee->id, 'amount' => 2, 'payment_date' => now()->toDateString(), 'type' => 'cash']);

    $report = app(FinanceInsightsService::class)->profitLoss($owner->id, now()->toDateString(), now()->toDateString());

    expect($report['revenue'])->toBe(18.0)
        ->and($report['returns'])->toBe(10.0)
        ->and($report['gross_profit'])->toBe(3.0)
        ->and($report['expenses_total'])->toBe(3.0)
        ->and($report['staff_payments'])->toBe(2.0)
        ->and($report['damaged_loss'])->toBe(5.0)
        ->and($report['net_profit'])->toBe(-7.0);
});

test('aging, inventory valuation and balances summary are computed', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $supplier = $this->makeSupplier($owner, ['balance' => 90]);
    $product = $this->makeProduct($owner, ['cost_price' => 5, 'selling_price' => 10, 'quantity' => 4, 'category' => 'snacks']);
    $deadStockProduct = $this->makeProduct($owner, ['cost_price' => 6, 'selling_price' => 12, 'quantity' => 3, 'category' => 'snacks']);

    $bill1 = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'customer_id' => $customer->id, 'total_price' => 50]);
    $bill1->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->saveQuietly();
    $bill1->products()->attach($product->id, ['quantity' => 1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);
    CustomerLedger::chargeBill($bill1);

    $bill2 = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'customer_id' => $customer->id, 'total_price' => 100]);
    $bill2->forceFill(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)])->saveQuietly();
    $bill2->products()->attach($product->id, ['quantity' => 1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);
    CustomerLedger::chargeBill($bill2);

    PurchaseBill::withoutGlobalScopes()->create(['user_id' => $owner->id, 'supplier_id' => $supplier->id, 'total_amount' => 40, 'purchase_date' => now()->subDays(20)->toDateString(), 'created_by' => $owner->id]);
    PurchaseBill::withoutGlobalScopes()->create(['user_id' => $owner->id, 'supplier_id' => $supplier->id, 'total_amount' => 50, 'purchase_date' => now()->subDays(70)->toDateString(), 'created_by' => $owner->id]);
    CapitalEntry::create(['user_id' => $owner->id, 'amount' => 100, 'entry_date' => now()->toDateString()]);

    $service = app(FinanceInsightsService::class);
    $receivables = $service->receivablesAging($owner->id, now()->toDateString());
    $payables = $service->payablesAging($owner->id, now()->toDateString());
    $inventory = $service->inventoryValuation($owner->id, now()->toDateString(), now()->toDateString());
    $balances = $service->balancesSummary($owner->id, now()->toDateString());

    expect($receivables['totals']['0_30'])->toBe(50.0)
        ->and($receivables['totals']['31_60'])->toBe(100.0)
        ->and($payables['totals']['0_30'])->toBe(40.0)
        ->and($payables['totals']['61_90'])->toBe(50.0)
        ->and($inventory['total_value'])->toBe(38.0)
        ->and($inventory['dead_stock']->count())->toBe(1)
        ->and($balances['receivables'])->toBe(150.0)
        ->and($balances['payables'])->toBe(90.0)
        ->and($balances['inventory_value'])->toBe(38.0)
        ->and($balances['capital'])->toBe(100.0);
});

test('aging keeps opening balances in the unallocated bucket as of the selected date', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $supplier = $this->makeSupplier($owner);

    CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => -25,
        'type' => 'cash',
        'kind' => 'opening_balance',
        'note' => 'Initial balance',
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ]);
    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $owner->id,
        'amount' => 40,
        'type' => 'cash',
        'kind' => 'opening_balance',
        'note' => 'Initial balance',
        'payment_date' => now()->subDays(15)->toDateString(),
        'created_at' => now()->subDays(15),
        'updated_at' => now()->subDays(15),
    ]);

    $service = app(FinanceInsightsService::class);
    $receivables = $service->receivablesAging($owner->id, now()->toDateString());
    $payables = $service->payablesAging($owner->id, now()->toDateString());

    expect($receivables['totals']['unallocated'])->toBe(25.0)
        ->and($payables['totals']['unallocated'])->toBe(40.0);
});

test('customer balance mismatch query avoids duplicate selected column names', function () {
    $owner = $this->makeOwner();

    $sql = app(FinanceInsightsService::class)->customerBalanceMismatchQuery($owner->id)->toSql();
    $columns = extractSelectColumnNames($sql);

    expect($sql)->not->toContain('select *')
        ->and($columns)->toBe(array_values(array_unique($columns)))
        ->and($columns)->toContain('id');
});
