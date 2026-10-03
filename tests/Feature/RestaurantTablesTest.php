<?php

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Tests\Support\Builds;

uses(Builds::class);

test('restaurant owner can create update reorder and bulk create tables', function () {
    $owner = $this->makeRestaurant();

    $this->actingAs($owner)->post(route('restaurant.tables.store'), [
        'name' => 'T1',
        'zone' => 'Main',
        'seats' => 4,
        'sort_order' => 2,
    ])->assertRedirect(route('restaurant.tables.index'));

    $table = RestaurantTable::first();

    expect($table->name)->toBe('T1')
        ->and($table->zone)->toBe('Main')
        ->and($table->seats)->toBe(4);

    $this->actingAs($owner)->put(route('restaurant.tables.update', $table), [
        'name' => 'T1A',
        'zone' => 'Garden',
        'seats' => 6,
        'sort_order' => 4,
        'is_active' => 1,
    ])->assertRedirect(route('restaurant.tables.index'));

    $table->refresh();
    expect($table->name)->toBe('T1A')
        ->and($table->zone)->toBe('Garden')
        ->and($table->sort_order)->toBe(4);

    $this->actingAs($owner)->post(route('restaurant.tables.store'), [
        'bulk_prefix' => 'Table',
        'bulk_from' => 2,
        'bulk_to' => 4,
        'zone' => 'Main',
        'seats' => 4,
    ])->assertRedirect(route('restaurant.tables.index'));

    expect(RestaurantTable::count())->toBe(4);

    $pairs = RestaurantTable::orderBy('id')->get()->map(fn ($item) => ['id' => $item->id, 'sort_order' => $item->id])->all();
    $this->actingAs($owner)->postJson(route('restaurant.tables.reorder'), ['tables' => $pairs])
        ->assertOk();

    expect(RestaurantTable::find($table->id)->sort_order)->toBe($table->id);
});

test('table cannot be deleted while an open order uses it', function () {
    $owner = $this->makeRestaurant();
    $table = RestaurantTable::create([
        'user_id' => $owner->id,
        'name' => 'T9',
        'is_active' => true,
    ]);

    RestaurantOrder::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'status' => 'open',
        'label' => 'T9',
        'cart' => ['rows' => [['name' => 'Burger', 'quantity' => 1, 'selling_price' => 20, 'cost_price' => 8, 'discount' => 0, 'discount_type' => 'total', 'tags' => [], 'imeis' => [], 'note' => '']]],
        'total' => 20,
        'opened_by' => $owner->id,
    ]);

    $this->actingAs($owner)->delete(route('restaurant.tables.destroy', $table))
        ->assertSessionHasErrors('table');

    expect(RestaurantTable::whereKey($table->id)->exists())->toBeTrue();
});

test('restaurant employee needs manage tables permission to manage tables but can still list them for pos use', function () {
    $owner = $this->makeRestaurant();
    $employee = $this->makeEmployee($owner, ['create_bills', 'view_bills']);

    RestaurantTable::create([
        'user_id' => $owner->id,
        'name' => 'T1',
        'is_active' => true,
    ]);

    $this->actingAs($employee)->get(route('restaurant.tables.list'))
        ->assertOk()
        ->assertJsonCount(1, 'tables');

    $this->actingAs($employee)->get(route('restaurant.tables.index'))
        ->assertForbidden();
});

test('non restaurant account cannot access restaurant table pages', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)->get(route('restaurant.tables.index'))->assertForbidden();
});
