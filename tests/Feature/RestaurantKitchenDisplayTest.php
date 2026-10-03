<?php

use App\Models\KitchenTicket;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\User;
use Tests\Support\Builds;

uses(Builds::class);

function buildRestaurantOrder(User $owner, ?RestaurantTable $table = null): RestaurantOrder
{
    return RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $table?->id,
        'order_type' => $table ? 'dine_in' : 'takeaway',
        'status' => 'open',
        'label' => $table?->name ?? 'Takeaway',
        'cart' => ['rows' => [[
            'name' => 'Dish',
            'quantity' => 1,
            'selling_price' => 20,
            'cost_price' => 8,
            'discount' => 0,
            'discount_type' => 'total',
            'tags' => [],
            'imeis' => [],
            'note' => '',
        ]]],
        'total' => 20,
        'opened_by' => $owner->id,
    ]);
}

test('kitchen page and feed respect authorization and etag caching', function () {
    $owner = $this->makeRestaurant();
    $table = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'K1', 'is_active' => true]);
    $order = buildRestaurantOrder($owner, $table);
    KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order->id,
        'number' => 1,
        'local_service_date' => '2026-10-03',
        'items' => [['name' => 'Dish', 'quantity' => 1, 'note' => null, 'product_id' => null]],
        'status' => 'new',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => now(),
    ]);

    $employee = $this->makeEmployee($owner, ['view_kitchen']);
    $cashier = $this->makeEmployee($owner, ['view_bills', 'create_bills']);
    $nonRestaurant = $this->makeOwner();

    $this->actingAs($employee)->get(route('kitchen.display'))->assertOk();
    $this->actingAs($cashier)->get(route('kitchen.display'))->assertForbidden();
    $this->actingAs($nonRestaurant)->get(route('kitchen.display'))->assertForbidden();

    $feed = $this->actingAs($employee)->getJson(route('kitchen.feed'))
        ->assertOk()
        ->assertJsonPath('tickets.0.number', 1);

    $etag = trim($feed->headers->get('ETag'), '"');
    $this->actingAs($employee)->getJson(route('kitchen.feed'), ['If-None-Match' => '"' . $etag . '"'])
        ->assertStatus(304);

    $cursor = $feed->json('cursor');
    $ticket = KitchenTicket::first();
    $ticket->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    $delta = $this->actingAs($employee)->getJson(route('kitchen.feed', ['since' => $cursor]))
        ->assertOk()
        ->assertJsonPath('delta', true);

    expect($delta->json('removed_ids'))->toContain($ticket->id);
});

test('kitchen ticket state machine and login redirect work', function () {
    $owner = $this->makeRestaurant();
    $table = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'K2', 'is_active' => true]);
    $order = buildRestaurantOrder($owner, $table);
    $ticket = KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order->id,
        'number' => 1,
        'local_service_date' => now()->toDateString(),
        'items' => [['name' => 'Dish', 'quantity' => 1, 'note' => null, 'product_id' => null]],
        'status' => 'new',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => now(),
    ]);

    $kitchenOnly = $this->makeEmployee($owner, ['view_kitchen']);

    $this->post('/login', ['email' => $kitchenOnly->email, 'password' => 'password'])
        ->assertRedirect(route('kitchen.display'));

    $this->actingAs($kitchenOnly)->postJson(route('kitchen.tickets.transition', $ticket), ['action' => 'start'])
        ->assertOk();
    expect($ticket->fresh()->status)->toBe('preparing');

    $this->actingAs($kitchenOnly)->postJson(route('kitchen.tickets.transition', $ticket), ['action' => 'ready'])
        ->assertOk();
    expect($ticket->fresh()->status)->toBe('ready');

    $this->actingAs($kitchenOnly)->postJson(route('kitchen.tickets.transition', $ticket), ['action' => 'recall'])
        ->assertOk();
    expect($ticket->fresh()->status)->toBe('preparing');

    $this->actingAs($kitchenOnly)->postJson(route('kitchen.tickets.transition', $ticket), ['action' => 'served'])
        ->assertStatus(422);
});

test('kitchen cancel transition persists the supplied reason on the locked ticket', function () {
    $owner = $this->makeRestaurant();
    $order = buildRestaurantOrder($owner);
    $ticket = KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order->id,
        'number' => 7,
        'local_service_date' => now()->toDateString(),
        'items' => [['name' => 'Dish', 'quantity' => 1, 'note' => null, 'product_id' => null]],
        'status' => 'new',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => now(),
    ]);
    $kitchenOnly = $this->makeEmployee($owner, ['view_kitchen']);

    $this->actingAs($kitchenOnly)->postJson(route('kitchen.tickets.transition', $ticket), [
        'action' => 'cancel',
        'reason' => 'Out of stock',
    ])->assertOk()->assertJsonPath('ticket.cancel_reason', 'Out of stock');

    expect($ticket->fresh()->status)->toBe('cancelled')
        ->and($ticket->fresh()->cancel_reason)->toBe('Out of stock');
});

test('kitchen print renders the sent time in the shop timezone', function () {
    $owner = $this->makeRestaurant(['timezone' => 'Asia/Gaza']);
    $order = buildRestaurantOrder($owner);
    $ticket = KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order->id,
        'number' => 9,
        'local_service_date' => '2026-10-03',
        'items' => [['name' => 'Dish', 'quantity' => 1, 'note' => null, 'product_id' => null]],
        'status' => 'new',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => \Carbon\Carbon::parse('2026-10-03 07:30:00', 'UTC'),
    ]);
    $kitchenOnly = $this->makeEmployee($owner, ['view_kitchen']);

    $this->actingAs($kitchenOnly)->get(route('kitchen.tickets.print', $ticket))
        ->assertOk()
        ->assertSee('2026-10-03 10:30')
        ->assertDontSee('2026-10-03 07:30');
});

test('kitchen numbering restarts per local day and purge command removes stale served tickets', function () {
    $owner = $this->makeRestaurant(['timezone' => 'UTC']);
    $order = buildRestaurantOrder($owner);

    $this->travelTo(\Carbon\Carbon::parse('2026-10-03 23:59:00'));
    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $order), ['client_uuid' => 'near-midnight'])
        ->assertOk()
        ->assertJsonPath('ticket.number', 1);

    $this->actingAs($owner)->putJson(route('restaurant.orders.update', $order), [
        'order_type' => 'takeaway',
        'rows' => [
            [
                'name' => 'Dish',
                'quantity' => 2,
                'selling_price' => 20,
                'cost_price' => 8,
                'discount' => 0,
                'discount_type' => 'total',
                'tags' => [],
                'imeis' => [],
                'note' => '',
            ],
        ],
    ])->assertOk();

    $this->travelTo(\Carbon\Carbon::parse('2026-10-04 00:01:00'));
    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $order), ['client_uuid' => 'after-midnight'])
        ->assertOk()
        ->assertJsonPath('ticket.number', 1);

    $stale = KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order->id,
        'number' => 99,
        'local_service_date' => '2026-08-01',
        'items' => [['name' => 'Old', 'quantity' => 1, 'note' => null, 'product_id' => null]],
        'status' => 'served',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => now()->subDays(40),
        'served_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
        'created_at' => now()->subDays(40),
    ]);

    $this->artisan('restaurant:purge-tickets')
        ->expectsOutput('Purged 1 ticket(s).')
        ->assertSuccessful();

    expect(KitchenTicket::withoutGlobalScopes()->whereKey($stale->id)->exists())->toBeFalse();
});

test('restaurant pos partials stay hidden for non restaurant accounts', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner);
    $toolbar = $this->app['view']->make('pos.restaurant.toolbar')->render();
    $scripts = $this->app['view']->make('pos.restaurant.scripts')->render();

    expect(trim($toolbar))->toBe('')
        ->and(trim($scripts))->toBe('');
});

test('restaurant kds and pos scripts avoid raw html interpolation for user data', function () {
    $kdsScript = file_get_contents(public_path('js/restaurant-kds.js'));
    $posScript = file_get_contents(public_path('js/restaurant-pos.js'));

    expect($kdsScript)->not->toContain('innerHTML')
        ->and($posScript)->not->toContain('innerHTML')
        ->and($kdsScript)->toContain('textContent')
        ->and($posScript)->toContain('textContent');
});
