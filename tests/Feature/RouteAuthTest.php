<?php

use Illuminate\Support\Facades\Route;

/**
 * Guards against a module registering routes outside the authenticated groups
 * (routes/modules/*.php are auto-loaded at the top level of routes/web.php).
 */
it('keeps every non-public route behind an authentication middleware', function () {
    $publicUris = [
        '/',
        'login',
        'forgot-password',
        'reset-password',
        'reset-password/{token}',
        'lang/{locale}',
        'up',
        'hisba',
        'islam',
        'portfolio',
        'storage/{path}',
    ];

    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri() === '/' ? '/' : ltrim($route->uri(), '/');

        if (in_array($uri, $publicUris, true)) {
            continue;
        }

        $guarded = collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware))
            ->contains(fn (string $middleware) => $middleware === 'auth'
                || str_starts_with($middleware, 'auth:')
                || str_contains($middleware, 'Authenticate')
                || str_contains($middleware, 'StaffPortalAuth'));

        if (! $guarded) {
            $offenders[] = implode('|', $route->methods()) . ' ' . $uri;
        }
    }

    expect($offenders)->toBe([]);
});
