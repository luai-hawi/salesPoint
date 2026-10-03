<?php

use App\Http\Controllers\OfflineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/offline/csrf', [OfflineController::class, 'csrf'])->name('offline.csrf');
    Route::get('/offline', [OfflineController::class, 'index'])->name('offline.page');
    Route::get('/offline/queue', [OfflineController::class, 'queue'])->name('offline.queue');
});
