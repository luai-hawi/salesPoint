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
        // Register the language middleware to ensure proper localization
        $middleware->append(\App\Http\Middleware\LanguageMiddleware::class);

        // Apply session prevention globally to web routes
        $middleware->web(append: [
            \App\Http\Middleware\PreventConcurrentSessions::class,
        ]);
    })
    ->withSchedule(function ($schedule) {
        // Run product deactivation daily at 2 AM
        $schedule->command('products:deactivate-old')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('login')->withErrors(['session' => __('messages.page_expired')]);
        });
    })->create();
