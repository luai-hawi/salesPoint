<?php

use App\Models\Bill;
use App\Models\CapitalEntry;
use App\Services\Finance\FinanceInsightsService;
use App\Support\ReportCatalog;
use Illuminate\Support\Facades\DB;
use Tests\Support\Builds;

uses(Builds::class);

test('every legacy and accountant report prints HTML and CSV honors its requested period', function () {
    $owner = $this->makeOwner();
    $this->actingAs($owner);
    $types = array_merge(array_keys(ReportCatalog::rows()), [
        'profit_loss', 'receivables_aging', 'payables_aging', 'inventory_valuation', 'balances_summary',
    ]);
    foreach ($types as $type) {
        $this->get(route('reports.print', ['type' => $type, 'from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee(route('reports.index'), false);
        $this->get(route('reports.export', ['type' => $type, 'from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
    $this->get(route('reports.generate', ['type' => 'sale_bills']))->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $this->getJson(route('reports.generate', ['type' => 'sale_bills']))->assertOk()->assertJsonStructure(['rows']);
    $this->get(route('reports.print', ['type' => 'sale_bills', 'from' => '2026-02-01', 'to' => '2026-01-01']))->assertStatus(422);
});

test('restored financial details retain metrics and isolate tenants with correct discounts and returns', function () {
    $owner = $this->makeOwner();
    $other = $this->makeOwner();
    $product = $this->makeProduct($owner, ['name' => 'Our item', 'cost_price' => 5, 'selling_price' => 10, 'quantity' => 4]);
    $this->makeProduct($other, ['name' => 'Foreign inventory', 'quantity' => 999]);
    $this->actingAs($owner);
    CapitalEntry::create(['user_id' => $owner->id, 'amount' => 100, 'entry_date' => \App\Support\ShopTime::today($owner->id)]);
    $sale = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => 18, 'is_damaged' => false, 'is_returned' => false]);
    $sale->products()->attach($product->id, ['quantity' => 2, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 2]);
    $return = Bill::create(['user_id' => $owner->id, 'created_by' => $owner->id, 'total_price' => -10, 'is_damaged' => false, 'is_returned' => true]);
    $return->products()->attach($product->id, ['quantity' => -1, 'cost_price' => 5, 'selling_price' => 10, 'discount' => 0]);
    $details = app(FinanceInsightsService::class)->dashboardDetails($owner->id, \App\Support\ShopTime::today($owner->id), \App\Support\ShopTime::today($owner->id));
    expect((float) $details['inventory']->cost)->toBe(20.0)
        ->and((float) $details['inventory']->selling)->toBe(40.0)
        ->and((float) $details['capital']->sum('amount'))->toBe(100.0)
        ->and((float) $details['trends']->sum('profit'))->toBe(3.0)
        ->and((float) $details['tables']['top_products']['rows']->sum('profit'))->toBe(3.0)
        ->and((float) $details['tables']['returned_products']['rows']->sum('returned_cost'))->toBe(5.0)
        ->and((float) $details['tables']['returned_products']['rows']->sum('lost_profit'))->toBe(5.0);
    foreach (['dashboard.financial', 'dashboard.financial.print-report'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Our item')->assertDontSee('Foreign inventory')
            ->assertSee(__('finance.restored.top_products'))->assertSee(__('finance.restored.staff_breakdown'));
    }
});

test('legacy report filters, summaries and statement header remain tenant scoped', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner, ['balance' => -35]);
    $otherCustomer = $this->makeCustomer($owner);
    $foreign = $this->makeOwner();
    foreach ([
        [$owner, $customer, '2026-01-15', 'inside-period'],
        [$owner, $customer, '2026-02-15', 'outside-period'],
        [$owner, $otherCustomer, '2026-01-15', 'other-customer'],
        [$foreign, null, '2026-01-15', 'foreign-shop'],
    ] as [$shop, $client, $date, $note]) {
        $bill = Bill::create(['user_id' => $shop->id, 'created_by' => $shop->id, 'customer_id' => $client?->id,
            'total_price' => 35, 'note' => $note]);
        $bill->forceFill(['created_at' => $date.' 12:00:00', 'updated_at' => $date.' 12:00:00'])->saveQuietly();
    }
    $query = ['type' => 'customer_statement', 'customer_id' => $customer->id, 'from' => '2026-01-01', 'to' => '2026-01-31'];
    $this->actingAs($owner)->get(route('reports.print', $query))->assertOk()
        ->assertSee('inside-period')->assertDontSee('outside-period')->assertDontSee('other-customer')->assertDontSee('foreign-shop')
        ->assertSee($customer->name);
    $this->getJson(route('reports.generate', $query))->assertOk()
        ->assertJsonPath('summary.count', 1)->assertJsonPath('summary.total', 35)
        ->assertJsonPath('meta.name', $customer->name);
    $this->getJson(route('reports.print', ['type' => 'sale_bills', 'from' => '2026-02-31']))
        ->assertUnprocessable()->assertJsonValidationErrors('from');
});

test('financial print preserves independently selected cash dates and payment method', function () {
    $owner = $this->makeOwner();
    $query = ['start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'cash_preset' => 'custom', 'cash_from' => '2026-01-12', 'cash_to' => '2026-01-13', 'cash_method' => 'card'];
    $response = $this->actingAs($owner)->get(route('dashboard.financial.print-report', $query))->assertOk();
    expect($response->viewData('cashPeriod')['from_date'])->toBe('2026-01-12')
        ->and($response->viewData('cashPeriod')['to_date'])->toBe('2026-01-13');
    $this->getJson(route('dashboard.financial', ['start_date' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors('start_date');
});

test('product cards provide individual and select all controls using the bulk selection model', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);
    $this->actingAs($owner)->get(route('products.index', ['view' => 'cards']))->assertOk()
        ->assertSee('x-model="selectedIds"', false)->assertSee('toggleAll($event.target.checked)', false)
        ->assertSee('value="'.$product->id.'"', false);
});

test('an old program device cannot reclaim a session when the latest session has no database row', function () {
    $owner = $this->makeOwner(['session_id' => 'new-device-session']);
    DB::table('sessions')->where('user_id', $owner->id)->delete();
    $this->actingAs($owner)->withSession(['program_session_claimed' => $owner->id])
        ->get('/dashboard')->assertRedirect(route('login'))->assertSessionHasErrors('session');
    expect($owner->fresh()->session_id)->toBe('new-device-session');
});

test('a revoked program device stays signed out and cannot register itself again', function () {
    $owner = $this->makeOwner(['session_id' => null]);
    $this->actingAs($owner)->withSession(['program_session_claimed' => $owner->id])
        ->get('/dashboard')->assertRedirect(route('login'));
    expect($owner->fresh()->session_id)->toBeNull();
});

test('new logins rotate remembered credentials and old logout cannot clear the new session', function () {
    $owner = $this->makeOwner();
    $owner->forceFill(['remember_token' => 'old-remember-token'])->save();
    $this->post('/login', ['email' => $owner->email, 'password' => 'password', 'remember' => '1'])->assertRedirect();
    expect($owner->fresh()->remember_token)->not->toBe('old-remember-token');
    $newToken = $owner->fresh()->remember_token;
    $owner->update(['session_id' => 'newest-device']);
    $this->actingAs($owner->fresh())->post('/logout');
    expect($owner->fresh()->session_id)->toBe('newest-device')
        ->and($owner->fresh()->remember_token)->toBe($newToken);
});

test('remembered requests restore the canonical session and preserve its csrf token', function () {
    $owner = $this->makeOwner(['session_id' => str_repeat('s', 40)]);
    $owner->forceFill(['remember_token' => str_repeat('t', 60)])->save();
    $cookieName = auth()->guard('web')->getRecallerName();
    $cookie = $owner->id.'|'.$owner->remember_token.'|'.$owner->password;
    $this->withCookie($cookieName, $cookie)->get(route('dashboard.financial'))->assertOk();
    expect(app('session')->getId())->toBe(str_repeat('s', 40));
    $csrf = app('session')->token();
    auth()->forgetGuards();
    app('session')->flush();
    $this->withCookie(config('session.cookie'), str_repeat('n', 40))
        ->withCookie($cookieName, $cookie)->get(route('dashboard.financial'))->assertOk();
    expect(app('session')->getId())->toBe(str_repeat('s', 40))
        ->and(app('session')->token())->toBe($csrf)
        ->and($owner->fresh()->session_id)->toBe(str_repeat('s', 40));
});
