<?php

use App\Models\Bill;
use App\Models\KitchenTicket;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Tests\Support\Builds;

uses(Builds::class);

test('restaurant insights page and export render expected grouped numbers in shop local time', function () {
    $owner = $this->makeRestaurant(['timezone' => 'Asia/Tokyo']);
    $tableA = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'A1', 'is_active' => true]);
    $tableB = RestaurantTable::create(['user_id' => $owner->id, 'name' => 'B1', 'is_active' => true]);
    $product = $this->makeProduct($owner, ['name' => 'Pasta']);

    $bill1 = new Bill(['total_price' => 50, 'created_by' => $owner->id]);
    $bill1->user_id = $owner->id;
    $bill1->created_at = \Carbon\Carbon::parse('2026-10-02 23:30:00', 'UTC');
    $bill1->updated_at = \Carbon\Carbon::parse('2026-10-02 23:30:00', 'UTC');
    $bill1->save();
    $bill1->products()->attach($product->id, ['quantity' => 2, 'discount' => 0, 'cost_price' => 5, 'selling_price' => 25]);

    $bill2 = new Bill(['total_price' => 30, 'created_by' => $owner->id]);
    $bill2->user_id = $owner->id;
    $bill2->created_at = \Carbon\Carbon::parse('2026-10-03 00:30:00', 'UTC');
    $bill2->updated_at = \Carbon\Carbon::parse('2026-10-03 00:30:00', 'UTC');
    $bill2->save();
    $bill2->products()->attach($product->id, ['quantity' => 1, 'discount' => 0, 'cost_price' => 5, 'selling_price' => 30]);

    $order1 = RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $tableA->id,
        'order_type' => 'dine_in',
        'status' => 'paid',
        'label' => 'A1',
        'cart' => ['rows' => []],
        'total' => 50,
        'opened_by' => $owner->id,
        'bill_id' => $bill1->id,
        'closed_at' => \Carbon\Carbon::parse('2026-10-02 23:35:00', 'UTC'),
    ]);

    $order2 = RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $tableB->id,
        'order_type' => 'takeaway',
        'status' => 'paid',
        'label' => 'B1',
        'cart' => ['rows' => []],
        'total' => 30,
        'opened_by' => $owner->id,
        'bill_id' => $bill2->id,
        'closed_at' => \Carbon\Carbon::parse('2026-10-03 00:35:00', 'UTC'),
    ]);

    KitchenTicket::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_id' => $order1->id,
        'number' => 1,
        'local_service_date' => now()->toDateString(),
        'items' => [['name' => 'Pasta', 'quantity' => 2, 'note' => null, 'product_id' => $product->id]],
        'status' => 'served',
        'priority' => 'normal',
        'created_by' => $owner->id,
        'sent_at' => \Carbon\Carbon::parse('2026-10-02 23:30:00', 'UTC'),
        'ready_at' => \Carbon\Carbon::parse('2026-10-02 23:42:00', 'UTC'),
        'served_at' => \Carbon\Carbon::parse('2026-10-02 23:48:00', 'UTC'),
    ]);

    RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'order_type' => 'delivery',
        'status' => 'cancelled',
        'label' => 'Courier',
        'cart' => ['rows' => []],
        'total' => 15,
        'opened_by' => $owner->id,
        'cancel_reason' => 'Wrong address',
        'updated_at' => \Carbon\Carbon::parse('2026-10-03 02:00:00', 'UTC'),
        'closed_at' => \Carbon\Carbon::parse('2026-10-03 02:00:00', 'UTC'),
    ]);

    $this->actingAs($owner)->get(route('restaurant.insights.index', [
        'from' => '2026-10-03',
        'to' => '2026-10-03',
    ]))->assertOk()
        ->assertSee('Pasta')
        ->assertSee('A1')
        ->assertSee('B1')
        ->assertSee('08:00')
        ->assertSee('09:00');

    $this->actingAs($owner)->get(route('restaurant.insights.export', [
        'from' => '2026-10-03',
        'to' => '2026-10-03',
    ]))->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('restaurant insights are available to employees with reports or financial permissions', function () {
    $owner = $this->makeRestaurant();
    $reports = $this->makeEmployee($owner, ['view_reports']);
    $financial = $this->makeEmployee($owner, ['view_financial']);
    $blocked = $this->makeEmployee($owner, ['view_bills']);

    $this->actingAs($reports)->get(route('restaurant.insights.index'))->assertOk();
    $this->actingAs($financial)->get(route('restaurant.insights.index'))->assertOk();
    $this->actingAs($blocked)->get(route('restaurant.insights.index'))->assertForbidden();
});
