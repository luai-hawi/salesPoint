<?php

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Services\Finance\FinanceInsightsService;
use Tests\Support\Builds;

uses(Builds::class);

test('team summary aggregates sales returns deletions collections and expense entries per user', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_team_activity']);
    $product = $this->makeProduct($owner, ['cost_price' => 5, 'selling_price' => 10]);
    $customer = $this->makeCustomer($owner);

    $ownerBill = Bill::create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'total_price' => 18,
        'is_damaged' => false,
        'is_returned' => false,
    ]);
    $ownerBill->products()->attach($product->id, ['quantity' => 2, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 2]);

    $employeeReturn = Bill::create([
        'user_id' => $owner->id,
        'created_by' => $employee->id,
        'total_price' => -10,
        'is_damaged' => false,
        'is_returned' => true,
    ]);
    $employeeReturn->products()->attach($product->id, ['quantity' => -1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);

    $payment = CustomerPayment::withoutGlobalScopes()->create([
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'amount' => 25,
        'type' => 'cash',
        'kind' => 'payment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Expense::create(['user_id' => $owner->id, 'title' => 'Marketing', 'amount' => 9, 'expense_date' => now()->toDateString()]);

    ActivityLog::create([
        'owner_id' => $owner->id,
        'actor_id' => $employee->id,
        'actor_name' => $employee->name,
        'actor_role' => 'employee',
        'action' => 'deleted',
        'subject_type' => 'bill',
        'subject_id' => 500,
        'subject_label' => '#500',
        'created_at' => now(),
    ]);
    ActivityLog::create([
        'owner_id' => $owner->id,
        'actor_id' => $employee->id,
        'actor_name' => $employee->name,
        'actor_role' => 'employee',
        'action' => 'created',
        'subject_type' => 'customer_payment',
        'subject_id' => $payment->id,
        'subject_label' => $customer->name,
        'amount' => 25,
        'created_at' => now(),
    ]);
    ActivityLog::create([
        'owner_id' => $owner->id,
        'actor_id' => $employee->id,
        'actor_name' => $employee->name,
        'actor_role' => 'employee',
        'action' => 'created',
        'subject_type' => 'expense',
        'subject_id' => 1,
        'subject_label' => 'Marketing',
        'amount' => 9,
        'created_at' => now(),
    ]);

    $summary = app(FinanceInsightsService::class)->teamSummary($owner->id, now()->toDateString(), now()->toDateString());
    $employeeRow = $summary['rows']->firstWhere('user.id', $employee->id);
    $ownerRow = $summary['rows']->firstWhere('user.id', $owner->id);

    expect($ownerRow['sales_total'])->toBe(18.0)
        ->and($ownerRow['discounts'])->toBe(2.0)
        ->and($employeeRow['returns_total'])->toBe(10.0)
        ->and($employeeRow['deleted_bills'])->toBe(1)
        ->and($employeeRow['collections'])->toBe(25.0)
        ->and($employeeRow['expense_entries'])->toBe(1);
});

test('activity pages require team activity permission', function () {
    $owner = $this->makeOwner();
    $allowedEmployee = $this->makeEmployee($owner, ['view_team_activity']);
    $deniedEmployee = $this->makeEmployee($owner, []);

    $this->actingAs($owner)->get(route('shopowner.activity.index'))->assertOk();
    $this->actingAs($allowedEmployee)->get(route('finance.team-summary.index'))->assertOk();
    $this->actingAs($deniedEmployee)->get(route('shopowner.activity.index'))->assertForbidden();
});

test('team summary rejects drilldown to a foreign shop user', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $foreignEmployee = $this->makeEmployee($otherOwner, ['view_team_activity']);

    $this->actingAs($owner)
        ->get(route('finance.team-summary.index', ['user_id' => $foreignEmployee->id]))
        ->assertNotFound();
});

test('activity csv export neutralises spreadsheet formulas', function () {
    $owner = $this->makeOwner();
    ActivityLog::create([
        'owner_id' => $owner->id,
        'actor_id' => $owner->id,
        'actor_name' => '=Owner',
        'actor_role' => 'shop_owner',
        'action' => 'created',
        'subject_type' => 'expense',
        'subject_id' => 1,
        'subject_label' => '+Unsafe',
        'amount' => 5,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('shopowner.activity.export'));

    expect($response->streamedContent())->toContain("'=Owner");
});
