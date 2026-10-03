<?php

use Tests\Support\Builds;

uses(Builds::class);

test('offline routes require authentication', function () {
    $this->get('/offline')->assertRedirect('/login');
    $this->get('/offline/queue')->assertRedirect('/login');
    $this->get('/offline/csrf')->assertRedirect('/login');
});

test('offline csrf endpoint refreshes and returns a token', function () {
    $owner = $this->makeOwner();

    $response = $this->actingAs($owner)->get('/offline/csrf');

    $response->assertOk()
        ->assertJsonStructure(['token']);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->toContain('max-age=0');
});

test('offline pages render for owner employee and admin with offline assets', function (string $role) {
    $user = match ($role) {
        'owner' => $this->makeOwner(),
        'employee' => $this->makeEmployee($this->makeOwner(), ['view_products']),
        default => $this->makeAdmin(),
    };

    $this->actingAs($user)->get('/offline')
        ->assertOk()
        ->assertSee(__('sync.offline_page_title'))
        ->assertSee('js/sp-offline-core.js', false)
        ->assertSee('js/salespoint-offline.js', false);

    $this->actingAs($user)->get('/offline/queue')
        ->assertOk()
        ->assertSee(__('sync.offline_queue_title'));
})->with(['owner', 'employee', 'admin']);
