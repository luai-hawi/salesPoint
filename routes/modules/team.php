<?php

use App\Http\Controllers\ShopOwner\TeamController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', RoleMiddleware::class . ':shop_owner,restaurant,merchant'])
    ->prefix('/shopowner/team')
    ->name('shopowner.team.')
    ->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::get('/create', [TeamController::class, 'create'])->name('create');
        Route::post('/', [TeamController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [TeamController::class, 'edit'])->name('edit');
        Route::put('/{user}', [TeamController::class, 'update'])->name('update');
        Route::delete('/{user}', [TeamController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/toggle', [TeamController::class, 'toggle'])->name('toggle');
        Route::post('/{user}/reset-password', [TeamController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{user}/logout', [TeamController::class, 'logout'])->name('logout');
        Route::post('/{user}/copy-permissions', [TeamController::class, 'copyPermissions'])->name('copy-permissions');
    });
