<?php

use Illuminate\Support\Facades\Route;
use Tests\Support\Builds;

uses(Builds::class);

/**
 * Guards against pages that forget their permission check: an employee without any permission may only open
 * the small set of utility endpoints below. Add a route here only when it is genuinely harmless for everyone.
 */
it('keeps every page behind a permission for an employee without permissions', function () {
    $allowed = [
        '/auth/check',
        '/profile',
        '/api/tags',
        '/api/active-sales',
        '/api/categories',
        '/installments/due-count',
        '/offline',
        '/offline/csrf',
        '/offline/queue',
        '/confirm-password',
    ];

    $public = ['/', '/login', '/forgot-password', '/up', '/hisba', '/islam', '/portfolio', '/logout'];

    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, []);
    $open = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }

        $uri = '/' . ltrim($route->uri(), '/');
        if (in_array($uri, $public, true)) {
            continue;
        }

        if ($this->actingAs($employee)->get($uri)->getStatusCode() === 200) {
            $open[] = $uri;
        }
    }

    expect(array_values(array_diff($open, $allowed)))->toBe([]);
});