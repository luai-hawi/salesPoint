<?php

use App\Models\Bill;
use App\Models\KitchenTicket;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Tests\Support\Builds;

uses(Builds::class);

function restaurantRow(string $name, float $qty = 1, float $price = 10, string $note = ''): array
{
    return [
        'name' => $name,
        'quantity' => $qty,
        'selling_price' => $price,
        'cost_price' => 4,
        'discount' => 0,
        'discount_type' => 'total',
        'tags' => [],
        'imeis' => [],
        'note' => $note,
    ];
}

test('restaurant order lifecycle supports save send load pay and cancel', function () {
    $owner = $this->makeRestaurant(['timezone' => 'UTC']);
    $customer = $this->makeCustomer($owner, ['name' => 'Layla']);
    $table = RestaurantTable::create([
        'user_id' => $owner->id,
        'name' => 'A1',
        'zone' => 'Hall',
        'is_active' => true,
    ]);

    $create = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'guests' => 3,
        'customer_id' => $customer->id,
        'customer_name' => 'Layla',
        'rows' => [
            restaurantRow('Soup', 1, 18, 'no onion'),
        ],
        'note' => 'Corner table',
    ])->assertOk();

    $orderId = $create->json('order.id');
    $order = RestaurantOrder::findOrFail($orderId);

    expect((float) $order->total)->toBe(18.0)
        ->and($order->status)->toBe('open')
        ->and($order->table_id)->toBe($table->id);

    $send = $this->actingAs($owner)->postJson(route('restaurant.orders.send', $order), [
        'client_uuid' => 'ticket-1',
    ])->assertOk();

    expect($send->json('ticket.number'))->toBe(1)
        ->and($send->json('ticket.items.0.quantity'))->toBe(1)
        ->and($send->json('ticket.items.0.note'))->toContain('no onion');

    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $order), [
        'client_uuid' => 'ticket-1',
    ])->assertOk();

    expect(KitchenTicket::count())->toBe(1);

    $this->actingAs($owner)->putJson(route('restaurant.orders.update', $order), [
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'guests' => 4,
        'customer_id' => $customer->id,
        'customer_name' => 'Layla',
        'rows' => [
            restaurantRow('Soup', 2, 18, 'no onion'),
            restaurantRow('Tea', 1, 6),
        ],
        'note' => 'Corner table',
    ])->assertOk();

    $secondSend = $this->actingAs($owner)->postJson(route('restaurant.orders.send', $order), [
        'client_uuid' => 'ticket-2',
    ])->assertOk();

    expect($secondSend->json('ticket.number'))->toBe(2)
        ->and($secondSend->json('ticket.items'))->toHaveCount(2)
        ->and(collect($secondSend->json('ticket.items'))->sum('quantity'))->toBe(2);

    $this->actingAs($owner)->postJson(route('restaurant.orders.load', $order))
        ->assertOk()
        ->assertJsonPath('snapshot.rows.0.name', 'Soup')
        ->assertJsonPath('snapshot.restaurant.orderId', $order->id)
        ->assertJsonPath('snapshot.restaurant.tableId', $table->id)
        ->assertJsonPath('snapshot.table_label', 'A1');

    $bill = new Bill([
        'total_price' => 42,
        'customer_id' => $customer->id,
        'created_by' => $owner->id,
    ]);
    $bill->user_id = $owner->id;
    $bill->save();

    $this->actingAs($owner)->postJson(route('restaurant.orders.paid', $order), [
        'bill_id' => $bill->id,
    ])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->bill_id)->toBe($bill->id)
        ->and($order->closed_at)->not->toBeNull();

    $takeaway = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'order_type' => 'takeaway',
        'rows' => [restaurantRow('Pizza', 1, 30)],
        'customer_name' => 'Walk-in',
    ])->assertOk();

    $takeawayId = $takeaway->json('order.id');
    $this->actingAs($owner)->postJson(route('restaurant.orders.cancel', $takeawayId), [
        'reason' => 'Customer changed mind',
    ])->assertOk();

    expect(RestaurantOrder::find($takeawayId)->status)->toBe('cancelled');
});

test('table orders cannot overlap and payload size is validated', function () {
    $owner = $this->makeRestaurant();
    $table = RestaurantTable::create([
        'user_id' => $owner->id,
        'name' => 'B1',
        'is_active' => true,
    ]);

    $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'rows' => [restaurantRow('Soup')],
    ])->assertOk();

    $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'rows' => [restaurantRow('Tea')],
    ])->assertStatus(422);

    $rows = [];
    for ($i = 1; $i <= 101; $i++) {
        $rows[] = restaurantRow('Item ' . $i);
    }

    $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'order_type' => 'takeaway',
        'rows' => $rows,
    ])->assertStatus(422);
});

test('other tenants and non bill creators cannot operate another shops restaurant orders', function () {
    $owner = $this->makeRestaurant();
    $other = $this->makeRestaurant();
    $table = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'C1', 'is_active' => true]);

    $order = RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'status' => 'open',
        'label' => 'C1',
        'cart' => ['rows' => [restaurantRow('Soup')]],
        'total' => 10,
        'opened_by' => $owner->id,
    ]);

    $employee = $this->makeEmployee($owner, ['view_bills']);

    $this->actingAs($other)->postJson(route('restaurant.orders.load', $order))->assertNotFound();
    $this->actingAs($employee)->postJson(route('restaurant.orders.load', $order))->assertForbidden();
});

test('restaurant orders index renders opened times in the shop timezone', function () {
    $owner = $this->makeRestaurant(['timezone' => 'Asia/Gaza']);
    $table = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'TZ1', 'is_active' => true]);

    $order = RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'status' => 'open',
        'label' => 'TZ1',
        'cart' => ['rows' => [restaurantRow('Soup')]],
        'total' => 10,
        'opened_by' => $owner->id,
    ]);
    $order->forceFill([
        'created_at' => \Carbon\Carbon::parse('2026-10-03 07:30:00', 'UTC'),
        'updated_at' => \Carbon\Carbon::parse('2026-10-03 07:30:00', 'UTC'),
    ])->saveQuietly();

    KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order->id,
        'number' => 1,
        'local_service_date' => '2026-10-03',
        'items' => [['name' => 'Soup', 'quantity' => 1, 'note' => null, 'product_id' => null]],
        'status' => 'new',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => now(),
    ]);

    $this->actingAs($owner)->get(route('restaurant.orders.index'))
        ->assertOk()
        ->assertSee('2026-10-03 10:30');
});

test('kitchen send tokens are idempotent per order and rejected across orders', function () {
    $owner = $this->makeRestaurant();
    $tableA = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'D1', 'is_active' => true]);
    $tableB = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'D2', 'is_active' => true]);

    $first = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $tableA->id,
        'order_type' => 'dine_in',
        'rows' => [restaurantRow('Soup')],
    ])->json('order.id');

    $second = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $tableB->id,
        'order_type' => 'dine_in',
        'rows' => [restaurantRow('Tea')],
    ])->json('order.id');

    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $first), ['client_uuid' => 'kitchen-uuid-1'])
        ->assertOk();
    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $first), ['client_uuid' => 'kitchen-uuid-1'])
        ->assertOk();
    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $second), ['client_uuid' => 'kitchen-uuid-1'])
        ->assertStatus(422);
});

test('paid and cancelled order transitions are guarded and pending bill links reconcile by client uuid', function () {
    $owner = $this->makeRestaurant();
    $table = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'E1', 'is_active' => true]);
    $customer = $this->makeCustomer($owner);

    $orderId = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'customer_id' => $customer->id,
        'rows' => [[
            'name' => 'Meal',
            'quantity' => 1,
            'selling_price' => 12,
            'cost_price' => 4,
            'discount' => 0,
            'discount_type' => 'total',
            'tags' => ['extra@2'],
            'imeis' => [],
            'note' => '',
        ]],
    ])->json('order.id');

    $order = RestaurantOrder::findOrFail($orderId);
    expect((float) $order->total)->toBe(14.0);

    $this->actingAs($owner)->postJson(route('restaurant.orders.paid', $order), [
        'bill_client_uuid' => 'offline-bill-1',
    ])->assertOk()
        ->assertJsonPath('order.pending_bill_client_uuid', 'offline-bill-1');

    $mismatched = new Bill(['total_price' => 20, 'created_by' => $owner->id, 'client_uuid' => 'offline-bill-1']);
    $mismatched->user_id = $owner->id;
    $mismatched->save();

    $this->actingAs($owner)->getJson(route('restaurant.orders.index'))
        ->assertOk()
        ->assertJsonPath('orders.0.pending_bill_client_uuid', 'offline-bill-1');

    $mismatched->delete();

    $matched = new Bill(['total_price' => 14, 'created_by' => $owner->id, 'client_uuid' => 'offline-bill-1']);
    $matched->user_id = $owner->id;
    $matched->save();

    $this->actingAs($owner)->getJson(route('restaurant.orders.index'))
        ->assertOk();

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->bill_id)->toBe($matched->id)
        ->and($order->pending_bill_client_uuid)->toBeNull();

    $otherOrderId = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'order_type' => 'takeaway',
        'rows' => [restaurantRow('Dessert', 1, 14)],
    ])->json('order.id');

    $this->actingAs($owner)->postJson(route('restaurant.orders.paid', $otherOrderId), [
        'bill_id' => $matched->id,
    ])->assertStatus(422);

    $this->actingAs($owner)->postJson(route('restaurant.orders.cancel', $order), [
        'reason' => 'Too late',
    ])->assertStatus(422);
});

test('merged orders do not re-send already ticketed items', function () {
    $owner = $this->makeRestaurant();
    $tableA = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'M1', 'is_active' => true]);
    $tableB = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'M2', 'is_active' => true]);

    $targetId = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $tableA->id,
        'order_type' => 'dine_in',
        'rows' => [restaurantRow('Soup', 1, 10)],
    ])->json('order.id');

    $sourceId = $this->actingAs($owner)->postJson(route('restaurant.orders.store'), [
        'table_id' => $tableB->id,
        'order_type' => 'dine_in',
        'rows' => [restaurantRow('Tea', 1, 5)],
    ])->json('order.id');

    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $targetId), ['client_uuid' => 'merge-send-1'])->assertOk();
    $this->actingAs($owner)->postJson(route('restaurant.orders.send', $sourceId), ['client_uuid' => 'merge-send-2'])->assertOk();

    $this->actingAs($owner)->postJson(route('restaurant.orders.merge', $targetId), [
        'source_order_id' => $sourceId,
    ])->assertOk();

    $target = RestaurantOrder::findOrFail($targetId);

    $this->actingAs($owner)->putJson(route('restaurant.orders.update', $target), [
        'table_id' => $tableA->id,
        'order_type' => 'dine_in',
        'rows' => [
            restaurantRow('Soup', 1, 10),
            restaurantRow('Tea', 1, 5),
            restaurantRow('Cake', 1, 7),
        ],
    ])->assertOk();

    $send = $this->actingAs($owner)->postJson(route('restaurant.orders.send', $target), [
        'client_uuid' => 'merge-send-3',
    ])->assertOk();

    expect($send->json('ticket.items'))->toHaveCount(1)
        ->and($send->json('ticket.items.0.name'))->toBe('Cake');
});
