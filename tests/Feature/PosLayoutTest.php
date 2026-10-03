<?php

use App\Models\User;
use Tests\Support\Builds;

uses(Builds::class);

test('layout settings are clamped and whitelisted', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)
        ->postJson(route('pos.layout.save'), [
            'settings' => [
                'preset' => 'custom',
                'products_width' => 120,
                'font_scale' => 10,
                'grid_columns' => 22,
                'payment_panel' => 'hack',
                'show_image' => false,
                'products_tall' => true,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('settings.products_width', 75)
        ->assertJsonPath('settings.font_scale', 90)
        ->assertJsonPath('settings.grid_columns', 8)
        ->assertJsonPath('settings.products_tall', true)
        ->assertJsonMissingPath('settings.payment_panel');

    $owner->refresh();
    expect($owner->pos_settings['show_image'])->toBeFalse();
    expect(array_key_exists('payment_panel', $owner->pos_settings))->toBeFalse();
});

test('owner can apply layout to team and lock employees', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_products', 'view_bills', 'create_bills']);

    $this->actingAs($owner)
        ->postJson(route('pos.layout.apply-team'), [
            'settings' => ['preset' => 'visual', 'products_width' => 68],
            'lock' => true,
        ])
        ->assertOk()
        ->assertJsonPath('settings.preset', 'visual');

    $owner->refresh();
    expect(data_get($owner->pos_settings, 'team_default.preset'))->toBe('visual');
    expect(data_get($owner->pos_settings, 'team_lock'))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertViewHas('posLayoutTeamLock', true);

    $this->actingAs($employee)
        ->postJson(route('pos.layout.save'), [
            'settings' => ['preset' => 'cashier'],
        ])
        ->assertForbidden();
});

test('legacy focus toggle maps to preset and visibility setting', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)
        ->postJson(route('settings.pos-view-mode'), ['slim_mode' => true])
        ->assertOk()
        ->assertJsonPath('settings.preset', 'focus')
        ->assertJsonPath('settings.products_tall', true);

    $owner->refresh();
    expect(data_get($owner->visibility_settings, 'pos_slim_mode'))->toBeTrue();
    expect(data_get($owner->pos_settings, 'preset'))->toBe('focus');

    $this->actingAs($owner)
        ->postJson(route('settings.pos-view-mode'), ['slim_mode' => false])
        ->assertOk()
        ->assertJsonPath('settings.preset', 'classic')
        ->assertJsonPath('settings.products_tall', false);
});

test('layout reset falls back to defaults', function () {
    $owner = $this->makeOwner([
        'pos_settings' => ['preset' => 'visual', 'products_width' => 70],
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('pos.layout.reset'))
        ->assertOk()
        ->assertJsonPath('settings.preset', 'classic');
});
