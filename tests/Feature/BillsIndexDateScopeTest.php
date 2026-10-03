<?php

use App\Models\Bill;
use App\Support\ShopTime;
use Tests\Support\Builds;

uses(Builds::class);

function scopeBillPayload($product, array $overrides = []): array
{
    return array_replace_recursive([
        'product_ids' => [$product->id],
        'quantities' => [1],
        'discounts' => [0],
        'cost_prices' => [(float) $product->cost_price],
        'selling_prices' => [(float) $product->selling_price],
        'discount_types' => ['total'],
        'product_tags' => [null],
    ], $overrides);
}

/** Creates a bill of 10 (cost 4) and moves it to the given UTC instant. */
function scopeBill($test, $owner, $product, $createdAtUtc, array $overrides = []): Bill
{
    $id = $test->actingAs($owner)->postJson('/bills', scopeBillPayload($product, $overrides))->assertOk()->json('bill.id');
    Bill::withoutGlobalScopes()->whereKey($id)->update(['created_at' => $createdAtUtc, 'updated_at' => $createdAtUtc]);

    return Bill::withoutGlobalScopes()->findOrFail($id);
}

beforeEach(function () {
    $this->owner = $this->makeOwner();
    $this->product = $this->makeProduct($this->owner, ['quantity' => 500, 'cost_price' => 4, 'selling_price' => 10]);
    $this->product->batches()->create(['quantity' => 500, 'cost_price' => 4, 'user_id' => $this->owner->id]);

    $todayStartUtc = ShopTime::utcRange(ShopTime::today($this->owner->id), null, $this->owner->id)[0];
    $this->todayBill = scopeBill($this, $this->owner, $this->product, now());
    // Just after local midnight: still "today" for the shop although it is "yesterday" in UTC.
    $this->earlyTodayBill = scopeBill($this, $this->owner, $this->product, $todayStartUtc->copy()->addMinutes(30));
    $this->oldBill = scopeBill($this, $this->owner, $this->product, now()->subDays(3));
    $this->oldDate = ShopTime::localDate($this->oldBill->created_at . ' UTC', $this->owner->id);
});

it('shows only today\'s bills and totals by default', function () {
    $response = $this->actingAs($this->owner)->get(route('bills.index'))->assertOk();

    expect($response->viewData('bills')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->todayBill->id, $this->earlyTodayBill->id])->sort()->values()->all())
        ->and((float) $response->viewData('totalSales'))->toBe(20.0)
        ->and((float) $response->viewData('totalProfit'))->toBe(12.0)
        ->and((float) $response->viewData('filteredPaid'))->toBe(20.0)
        ->and($response->viewData('selectedDate'))->toBe(ShopTime::today($this->owner->id))
        ->and($response->viewData('allDates'))->toBeFalse();

    $response->assertSee(__('bills.list.scope_today', ['date' => ShopTime::today($this->owner->id)]));
});

it('uses the chosen date or range for the totals', function () {
    $day = $this->actingAs($this->owner)->get(route('bills.index', ['date' => $this->oldDate]))->assertOk();
    expect($day->viewData('bills')->pluck('id')->all())->toBe([$this->oldBill->id])
        ->and((float) $day->viewData('totalSales'))->toBe(10.0);

    $range = $this->actingAs($this->owner)->get(route('bills.index', [
        'date' => $this->oldDate,
        'date_to' => ShopTime::today($this->owner->id),
    ]))->assertOk();
    expect((float) $range->viewData('totalSales'))->toBe(30.0);
});

it('shows every date on request and when searching without a date', function () {
    $all = $this->actingAs($this->owner)->get(route('bills.index', ['all_dates' => 1]))->assertOk();
    expect((float) $all->viewData('totalSales'))->toBe(30.0)
        ->and($all->viewData('allDates'))->toBeTrue();

    $search = $this->actingAs($this->owner)->get(route('bills.index', ['search' => (string) $this->oldBill->id]))->assertOk();
    expect($search->viewData('bills')->pluck('id')->all())->toContain($this->oldBill->id);

    // A date picked in "all dates" mode wins over the flag.
    $picked = $this->actingAs($this->owner)->get(route('bills.index', ['all_dates' => 1, 'date' => $this->oldDate]))->assertOk();
    expect((float) $picked->viewData('totalSales'))->toBe(10.0);
});

it('ignores an invalid date and falls back to today', function () {
    $response = $this->actingAs($this->owner)->get(route('bills.index', ['date' => 'not-a-date']))->assertOk();

    expect((float) $response->viewData('totalSales'))->toBe(20.0);
});

it('keeps the payment-status filter scoped to the date', function () {
    $customer = $this->makeCustomer($this->owner);
    scopeBill($this, $this->owner, $this->product, now(), ['customer_id' => $customer->id, 'paid_amount' => 4]);
    scopeBill($this, $this->owner, $this->product, now()->subDays(3), ['customer_id' => $customer->id, 'paid_amount' => 4]);

    $response = $this->actingAs($this->owner)->get(route('bills.index', ['payment_status' => 'partial']))->assertOk();

    expect($response->viewData('bills')->total())->toBe(1)
        ->and((float) $response->viewData('filteredPaid'))->toBe(4.0)
        ->and((float) $response->viewData('filteredDue'))->toBe(6.0);
});

it('returns the quick review of a bill with items and payment details', function () {
    $customer = $this->makeCustomer($this->owner, ['name' => 'Preview Customer']);
    $bill = scopeBill($this, $this->owner, $this->product, now(), [
        'customer_id' => $customer->id,
        'paid_amount' => 5,
        'quantities' => [3],
        'discounts' => [2],
        'note' => 'Deliver tomorrow',
    ]);

    $this->actingAs($this->owner)->getJson(route('bills.preview', $bill))
        ->assertOk()
        ->assertJsonPath('id', $bill->id)
        ->assertJsonPath('customer', 'Preview Customer')
        ->assertJsonPath('note', 'Deliver tomorrow')
        ->assertJsonPath('items.0.name', $this->product->name)
        ->assertJsonPath('items.0.quantity', 3)
        ->assertJsonPath('items.0.line_total', 28)
        ->assertJsonPath('total', 28)
        ->assertJsonPath('paid', 5)
        ->assertJsonPath('due', 23)
        ->assertJsonPath('status', 'partial')
        ->assertJsonPath('created_at', ShopTime::local($bill->created_at . ' UTC', $this->owner->id)->format('Y-m-d H:i'));
});

it('protects the quick review from other shops and employees without permission', function () {
    $other = $this->makeOwner();
    $status = $this->actingAs($other)->getJson(route('bills.preview', $this->todayBill))->status();
    expect($status)->toBeIn([403, 404]);

    $blocked = $this->makeEmployee($this->owner, []);
    expect($this->actingAs($blocked)->getJson(route('bills.preview', $this->todayBill))->status())->toBeIn([403, 404]);

    $allowed = $this->makeEmployee($this->owner, ['view_bills']);
    $this->actingAs($allowed)->getJson(route('bills.preview', $this->todayBill))->assertOk()->assertJsonPath('id', $this->todayBill->id);
});

it('renders the quick review button on the bills page', function () {
    $this->actingAs($this->owner)->get(route('bills.index'))
        ->assertOk()
        ->assertSee(route('bills.preview', $this->todayBill), false)
        ->assertSee(__('bills.list.quick_review'));
});
