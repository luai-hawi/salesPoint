<?php

use Tests\Support\Builds;

uses(Builds::class);

function assertDashboardViewData($response): void
{
    $response->assertOk();

    foreach ([
        'products',
        'totalToday',
        'customers',
        'warningProducts',
        'warningMonths',
        'deactivationMonths',
        'billsCount',
        'categories',
        'tags',
        'activeSales',
        'posLayout',
        'posLayoutLocked',
        'posLayoutTeamDefault',
        'heldBillsCount',
    ] as $key) {
        $response->assertViewHas($key);
    }
}

test('dashboard renders for owner with null pos settings', function () {
    $owner = $this->makeOwner(['pos_settings' => null]);
    $this->makeProduct($owner);
    $this->makeCustomer($owner);

    assertDashboardViewData($this->actingAs($owner)->get(route('dashboard')));
});

test('dashboard renders for employee with null pos settings', function () {
    $owner = $this->makeOwner();
    $this->makeProduct($owner);
    $this->makeCustomer($owner);
    $employee = $this->makeEmployee($owner, ['view_products', 'view_bills', 'create_bills'], ['pos_settings' => null]);

    assertDashboardViewData($this->actingAs($employee)->get(route('dashboard')));
});

test('dashboard renders for restaurant with null pos settings', function () {
    $restaurant = $this->makeRestaurant(['pos_settings' => null]);
    $this->makeProduct($restaurant);
    $this->makeCustomer($restaurant);

    assertDashboardViewData($this->actingAs($restaurant)->get(route('dashboard')));
});

test('dashboard resolves every saved preset', function (string $preset) {
    $owner = $this->makeOwner([
        'pos_settings' => ['preset' => $preset],
    ]);
    $this->makeProduct($owner);
    $this->makeCustomer($owner);

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('posLayout', fn ($settings) => data_get($settings, 'preset') === $preset);
})->with(['classic', 'focus', 'visual', 'cashier', 'custom']);

test('employee without create bills permission cannot open dashboard', function () {
    $owner = $this->makeOwner();
    $this->makeProduct($owner);
    $this->makeCustomer($owner);
    $employee = $this->makeEmployee($owner, ['view_products', 'view_bills']);

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('kitchen only employee is redirected to kitchen and intended dashboard is ignored', function () {
    $restaurant = $this->makeRestaurant();
    $employee = $this->makeEmployee($restaurant, ['view_kitchen']);

    $this->actingAs($employee)
        ->withSession(['url.intended' => route('dashboard')])
        ->get(route('dashboard'))
        ->assertRedirect(route('kitchen.display'));

    expect(session('url.intended'))->toBeNull();
});
