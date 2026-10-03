<?php

use App\Models\Bill;
use App\Models\DayClosing;
use App\Models\Product;
use Tests\Support\Builds;

uses(Builds::class);

test('an employee with only close_day can count cash blindly without seeing financial data', function () {
    $owner = $this->makeOwner();
    $cashier = $this->makeEmployee($owner, ['close_day']);
    \App\Models\CashMovement::create([
        'user_id' => $owner->id,
        'type' => 'opening',
        'amount' => 100,
        'reason' => 'Opening',
        'occurred_at' => now()->subDay(),
        'created_by' => $owner->id,
    ]);
    Bill::create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'total_price' => 4321.5,
        'payment_method' => 'cash',
        'is_returned' => false,
    ]);

    $page = $this->actingAs($cashier)->get(route('finance.day-close.index'))->assertOk();
    $page->assertSee(__('finance.day_close.blind_hint'))
        ->assertSee('name="counted_cash"', false)
        ->assertDontSee('4,321.50')
        ->assertDontSee('4,421.50')
        ->assertDontSee(__('finance.day_close.expected_cash'))
        ->assertDontSee(__('finance.day_close.variance'))
        ->assertDontSee(route('finance.day-close.print'), false);

    $this->actingAs($cashier)->post(route('finance.day-close.store'), [
        'closing_date' => now()->toDateString(),
        'counted_cash' => 4400,
    ])->assertRedirect()->assertSessionHas('success', __('finance.day_close.blind_saved'));

    $closing = DayClosing::withoutGlobalScopes()->where('user_id', $owner->id)->firstOrFail();
    expect((int) $closing->closed_by)->toBe($cashier->id)
        ->and((float) $closing->counted_cash)->toBe(4400.0)
        ->and((float) $closing->expected_cash)->toBe(4421.5)
        ->and((float) $closing->variance)->toBe(-21.5);

    $this->actingAs($cashier)->get(route('finance.day-close.print'))->assertForbidden();
    $this->actingAs($cashier)->get(route('dashboard.financial'))->assertForbidden();
    $this->actingAs($cashier)->get(route('finance.cash-drawer.index'))->assertForbidden();

    $this->actingAs($owner)->get(route('finance.day-close.index'))
        ->assertOk()
        ->assertSee($cashier->name)
        ->assertSee('-21.50');
});

test('a blind counter cannot overwrite a day closed by someone else or close a future day', function () {
    $owner = $this->makeOwner();
    $cashier = $this->makeEmployee($owner, ['close_day']);

    $this->actingAs($owner)->post(route('finance.day-close.store'), [
        'closing_date' => now()->toDateString(),
        'counted_cash' => 50,
    ])->assertRedirect();

    $this->actingAs($cashier)->post(route('finance.day-close.store'), [
        'closing_date' => now()->toDateString(),
        'counted_cash' => 1,
    ])->assertSessionHasErrors('closing_date');

    $this->actingAs($cashier)->post(route('finance.day-close.store'), [
        'closing_date' => now()->addDays(2)->toDateString(),
        'counted_cash' => 1,
    ])->assertSessionHasErrors('closing_date');

    expect((float) DayClosing::withoutGlobalScopes()->where('user_id', $owner->id)->sole()->counted_cash)->toBe(50.0);
});

test('employees without close_day or view_financial cannot reach day close, and the sidebar link follows the permission', function () {
    $owner = $this->makeOwner();
    $cashier = $this->makeEmployee($owner, ['view_products', 'create_bills']);
    $closer = $this->makeEmployee($owner, ['create_bills', 'close_day']);

    $this->actingAs($cashier)->get(route('finance.day-close.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('products.index'))->assertDontSee(route('finance.day-close.index'), false);
    $this->actingAs($closer)->get(route('finance.day-close.index'))
        ->assertOk()
        ->assertSee(__('navx.items.day_close'));
});

test('kiosk mode hides only the app chrome and offers an exit control', function () {
    $owner = $this->makeOwner();
    $html = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('id="app-root"')
        ->toContain('body.pos-kiosk-active #app-root > :not(.app-shell)')
        ->not->toContain('body.pos-kiosk-active .min-h-screen > :not(.app-shell)')
        ->not->toContain('body.pos-kiosk-active .app-shell > header {')
        ->toContain('id="pos-kiosk-exit"')
        ->toContain('id="pos-kiosk-fullscreen"');
});

test('barcode label dialog offers a validated custom size and the edit page has a single add stock form', function () {
    $owner = $this->makeOwner();
    $product = Product::withoutGlobalScopes()->findOrFail($this->makeProduct($owner, ['barcode' => '123456'])->id);

    $html = $this->actingAs($owner)->get(route('products.edit', $product))->assertOk()->getContent();

    expect($html)->toContain('<option value="custom">')
        ->toContain('id="bl-custom-width"')
        ->toContain('id="bl-custom-height"')
        ->toContain('x-ref="inlineStockForm"')
        ->not->toContain('batchCreateForm')
        ->not->toContain(route('batches.store'));
});
