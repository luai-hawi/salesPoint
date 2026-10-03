<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'staff/*/login',
            'staff/*/logout',
            'staff/*/punch',
            'staff/*/webauthn/*',
        ]);

        // Language + account checks run inside the web group (after the session is started),
        // so controllers and flash messages already see the user's chosen locale.
        $middleware->web(append: [
            \App\Http\Middleware\LanguageMiddleware::class,
            \App\Http\Middleware\PreventConcurrentSessions::class,
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\IdempotentRequests::class,
        ]);

        // Named alias for the tier-feature gate middleware
        $middleware->alias([
            'tier.feature' => \App\Http\Middleware\TierFeatureMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->withErrors(['session' => __('messages.page_expired')]);
        });

        $exceptions->render(function (\App\Exceptions\EntryLimitReached $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'resource' => $e->resourceKey,
                    'limit' => $e->limit,
                    'used' => $e->used,
                ], 422);
            }

            return redirect()->back()->withErrors(['entry_limit' => $e->getMessage()]);
        });
    })->create();
