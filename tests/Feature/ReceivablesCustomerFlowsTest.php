<?php

use App\Http\Controllers\BillsController;
use App\Models\Bill;
use App\Models\CustomerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Builds;

uses(Builds::class);

test('bill payments endpoint enforces permissions amount limits and tenant isolation', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['selling_price' => 15]);
    $employee = $this->makeEmployee($owner, ['view_bills']);

    $this->actingAs($owner)->postJson('/bills', [
        'customer_id' => $customer->id,
        'product_ids' => [$product->id],
        'quantities' => [2],
        'discounts' => [0],
        'cost_prices' => [5],
        'selling_prices' => [15],
    ])->assertOk();

    $bill = Bill::withoutGlobalScopes()->firstOrFail();

    $this->actingAs($employee)->postJson("/bills/{$bill->id}/payments", [
        'amount' => 5,
        'type' => 'cash',
    ])->assertForbidden();

    $this->actingAs($owner)->postJson("/bills/{$bill->id}/payments", [
        'amount' => 31,
        'type' => 'cash',
    ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

    $this->actingAs($other)->postJson("/bills/{$bill->id}/payments", [
        'amount' => 5,
        'type' => 'cash',
    ])->assertNotFound();

    $this->actingAs($owner)->postJson("/bills/{$bill->id}/payments", [
        'amount' => 12,
        'type' => 'card',
    ])->assertOk()->assertJsonPath('summary.due', 18);
});

test('customer payment pages filters and statement render with bill references', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner, ['selling_price' => 20]);

    $this->actingAs($owner)->postJson('/bills', [
        'customer_id' => $customer->id,
        'product_ids' => [$product->id],
        'quantities' => [2],
        'discounts' => [0],
        'cost_prices' => [5],
        'selling_prices' => [20],
    ])->assertOk();

    $bill = Bill::withoutGlobalScopes()->firstOrFail();
    CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->where('kind', 'bill_payment')->delete();
    app(\App\Services\CustomerLedger::class);
    \App\Services\CustomerLedger::receiveForBill($bill, 10, 'cash', 'Later payment', now()->subDay());

    $this->actingAs($owner)->get("/customers/{$customer->id}/payments?type=bill_payment")
        ->assertOk()
        ->assertSee((string) $bill->id)
        ->assertSee(__('receivables.bill_payment'));

    $this->actingAs($owner)->get("/customers/{$customer->id}/statement")
        ->assertOk()
        ->assertSee(__('receivables.customer_statement'))
        ->assertSee((string) $bill->id);
});

test('bill payment status filter respects legacy bill payment notes', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner, ['balance' => -120]);
    $old = new Bill(['total_price' => 100, 'customer_id' => $customer->id, 'created_by' => $owner->id]);
    $old->user_id = $owner->id;
    $old->created_at = now()->subDays(2);
    $old->updated_at = now()->subDays(2);
    $old->save();

    $new = new Bill(['total_price' => 20, 'customer_id' => $customer->id, 'created_by' => $owner->id]);
    $new->user_id = $owner->id;
    $new->save();

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

    $response = $this->actingAs($owner)->get(route('bills.index', ['payment_status' => 'paid']));

    $response->assertOk()
        ->assertSee('₪20.00')
        ->assertSee('₪0.00');

    expect($response->viewData('bills')->getCollection()->pluck('id')->all())->toBe([$new->id]);
});

test('payments destroy rules and update payment keep the ledger consistent', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $bill = new Bill(['total_price' => 40, 'customer_id' => $customer->id, 'created_by' => $owner->id]);
    $bill->user_id = $owner->id;
    $bill->save();

    \App\Services\CustomerLedger::chargeBill($bill);
    $payment = \App\Services\CustomerLedger::receiveForBill($bill, 10, 'cash');
    $general = \App\Services\CustomerLedger::receive($customer->fresh(), 5, 'cash', 'General');

    $charge = CustomerPayment::withoutGlobalScopes()->where('bill_id', $bill->id)->where('kind', 'bill_charge')->firstOrFail();

    $this->actingAs($owner)->deleteJson("/payments/{$charge->id}")
        ->assertStatus(422);

    $this->actingAs($owner)->putJson("/payments/{$charge->id}", [
        'amount' => 10,
        'type' => 'cash',
        'note' => 'bad',
    ])->assertStatus(422);

    $this->actingAs($owner)->deleteJson("/payments/{$general->id}")
        ->assertOk();

    $this->actingAs($owner)->deleteJson("/bills/{$bill->id}/payments/{$payment->id}")
        ->assertOk();

    expect((float) $customer->fresh()->balance)->toBe(-40.0);
});

test('quick payments use the ledger and the recalc balances command fixes drift', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner, ['balance' => 25]);

    $this->actingAs($owner)->postJson("/customers/{$customer->id}/quick-payments", [
        'amount' => 15,
        'type' => 'cash',
    ])->assertOk()->assertJsonPath('new_balance', fn ($balance) => (float) $balance === 40.0);

    $customer->update(['balance' => 999]);

    Artisan::call('customers:recalc-balances', ['--owner' => $owner->id]);

    expect((float) $customer->fresh()->balance)->toBe(15.0);
});

test('bills create route redirects to the dashboard', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)->get('/bills/create')
        ->assertRedirect(route('dashboard'));
});

test('customer search returns an empty result for users without an owner shop', function () {
    $admin = $this->makeAdmin();

    $this->actingAs($admin)->getJson('/api/customers/search?search=Visible')
        ->assertOk()
        ->assertExactJson([]);
});

test('bill totals subquery sql has no duplicate column names', function () {
    $controller = new BillsController();
    $method = new ReflectionMethod(BillsController::class, 'billTotalsBaseQuery');
    $method->setAccessible(true);

    $sql = $method->invoke($controller, 123, Request::create('/bills', 'GET'))->toSql();
    preg_match('/select\s+(.*?)\s+from\s+/is', $sql, $matches);
    $selectClause = strtolower(str_replace(['"', '`'], '', $matches[1] ?? ''));
    $columns = array_map('trim', array_filter(array_map('trim', explode(',', $selectClause))));

    expect($sql)->not->toContain('bills.*')
        ->and($columns)->toBe([
            'bills.id',
            'bills.total_price',
            'bills.customer_id',
            'bills.user_id',
        ]);
});
