<?php

use App\Models\Expense;
use App\Models\SupplierPayment;
use Tests\Support\Builds;

uses(Builds::class);

test('expense page contains advisory guardrail hints and shortcut data', function () {
    $owner = $this->makeOwner();
    $supplier = $this->makeSupplier($owner, ['name' => 'Acme Dealer', 'balance' => 44]);
    SupplierPayment::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $owner->id,
        'amount' => 44,
        'type' => 'cash',
        'kind' => 'payment',
        'payment_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($owner)->get(route('shopowner.expenses.index'));
    $response->assertOk()
        ->assertSee(__('finance.expenses.guardrail_supplier'))
        ->assertSee(__('finance.expenses.warning_supplier_match'))
        ->assertSee(__('finance.expenses.warning_amount_match'))
        ->assertSee('Acme Dealer', false);
});

test('expense categories can be created filtered updated and deleted with permissions', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_expenses', 'create_expenses', 'edit_expenses', 'delete_expenses']);

    $this->actingAs($employee)->post(route('shopowner.expenses.store'), [
        'title' => 'Rent payment',
        'category' => 'rent',
        'amount' => 100,
        'expense_date' => now()->toDateString(),
        'notes' => 'October',
    ])->assertRedirect(route('shopowner.expenses.index'));

    $expense = Expense::withoutGlobalScopes()->where('user_id', $owner->id)->first();
    expect($expense->category)->toBe('rent');

    $this->actingAs($employee)->put(route('shopowner.expenses.update', $expense), [
        'title' => 'Utility payment',
        'category' => 'utilities',
        'amount' => 120,
        'expense_date' => now()->toDateString(),
        'notes' => 'Updated',
    ])->assertRedirect(route('shopowner.expenses.index'));

    $this->actingAs($employee)->get(route('shopowner.expenses.index', ['category' => 'utilities']))
        ->assertOk()
        ->assertSee('Utility payment');

    $this->actingAs($employee)->delete(route('shopowner.expenses.destroy', $expense))
        ->assertRedirect(route('shopowner.expenses.index'));

    expect(Expense::withoutGlobalScopes()->where('user_id', $owner->id)->count())->toBe(0);
});

test('expense csv export neutralises spreadsheet formulas', function () {
    $owner = $this->makeOwner();
    Expense::create([
        'user_id' => $owner->id,
        'title' => '=SUM(1,1)',
        'amount' => 5,
        'expense_date' => now()->toDateString(),
        'notes' => '+cmd',
    ]);

    $response = $this->actingAs($owner)->get(route('shopowner.expenses.export'));

    expect($response->streamedContent())->toContain("'=SUM(1,1)")
        ->toContain("'+cmd");
});
