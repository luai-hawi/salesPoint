<?php

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Tests\Support\Builds;

uses(Builds::class);

test('restaurant dashboard renders one toolbar config block and script tag', function () {
    $owner = $this->makeRestaurant();
    $this->makeProduct($owner);

    $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'id="restaurant-pos-toolbar"'))->toBe(1)
        ->and(substr_count($html, 'window.RestaurantPosConfig ='))->toBe(1)
        ->and(substr_count($html, 'js/restaurant-pos.js'))->toBe(1);
});

test('non restaurant dashboard does not render restaurant pos wiring', function () {
    $owner = $this->makeOwner();
    $this->makeProduct($owner);

    $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'id="restaurant-pos-toolbar"'))->toBe(0)
        ->and(substr_count($html, 'window.RestaurantPosConfig ='))->toBe(0)
        ->and(substr_count($html, 'js/restaurant-pos.js'))->toBe(0);
});

test('restaurant order load snapshot keeps the coordinated restaurant shape', function () {
    $owner = $this->makeRestaurant();
    $table = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'SN1', 'is_active' => true]);

    $order = RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'status' => 'open',
        'label' => 'SN1',
        'cart' => ['rows' => [[
            'name' => 'Soup',
            'quantity' => 1,
            'selling_price' => 10,
            'cost_price' => 4,
            'discount' => 0,
            'discount_type' => 'total',
            'tags' => [],
            'imeis' => [],
            'note' => 'Less salt',
        ]]],
        'total' => 10,
        'opened_by' => $owner->id,
        'notes' => 'Window seat',
        'customer_name' => 'A',
        'customer_phone' => '1',
        'customer_address' => 'B',
    ]);

    $this->actingAs($owner)->postJson(route('restaurant.orders.load', $order))
        ->assertOk()
        ->assertJsonPath('snapshot.restaurant.orderId', $order->id)
        ->assertJsonPath('snapshot.restaurant.tableId', $table->id)
        ->assertJsonPath('snapshot.restaurant.orderType', 'dine_in')
        ->assertJsonPath('snapshot.restaurant.notes', 'Window seat')
        ->assertJsonPath('snapshot.restaurant.delivery.address', 'B');
});
