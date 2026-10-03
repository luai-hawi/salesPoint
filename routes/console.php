<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('offline:prune-idempotency', function () {
    $count = IdempotencyKey::pruneExpired();

    $this->info("Pruned {$count} idempotency key record(s).");
})->purpose('Prune expired offline idempotency records');

Schedule::command('offline:prune-idempotency')
    ->dailyAt('03:17')
    ->withoutOverlapping();

Schedule::command('attendance:close-stale')->hourly();
Schedule::command('restaurant:purge-tickets')->daily();
