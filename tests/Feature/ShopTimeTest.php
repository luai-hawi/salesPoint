<?php

use App\Support\ShopTime;
use Tests\Support\Builds;

uses(Builds::class);

it('resolves an employee to the timezone of the shop owner', function () {
    $owner = $this->makeOwner(['timezone' => 'Asia/Tokyo']);
    $employee = $this->makeEmployee($owner, []);

    expect(ShopTime::timezone($owner))->toBe('Asia/Tokyo');
    expect(ShopTime::timezone($employee))->toBe('Asia/Tokyo');
    expect(ShopTime::timezone($owner->id))->toBe('Asia/Tokyo');
});

it('falls back to the configured shop timezone for invalid or missing values', function () {
    config(['app.shop_timezone' => 'Asia/Jerusalem']);

    $owner = $this->makeOwner(['timezone' => null]);

    expect(ShopTime::timezone($owner))->toBe('Asia/Jerusalem');
    expect(ShopTime::timezone(null))->toBe('Asia/Jerusalem');
});

it('converts a UTC instant to the local calendar date of the shop', function () {
    $owner = $this->makeOwner(['timezone' => 'Asia/Tokyo']);

    expect(ShopTime::localDate('2026-10-03 20:30:00', $owner))->toBe('2026-10-04');
});
