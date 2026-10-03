<?php

use App\Http\Controllers\Restaurant\InsightsController;
use App\Http\Controllers\Restaurant\KitchenController;
use App\Http\Controllers\Restaurant\OrderController;
use App\Http\Controllers\Restaurant\TableController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RoleMiddleware::class . ':restaurant,employee'])->group(function () {
    Route::get('/kitchen', [KitchenController::class, 'display'])->name('kitchen.display');
    Route::get('/kitchen/feed', [KitchenController::class, 'feed'])->name('kitchen.feed');
    Route::post('/kitchen/tickets/{ticket}/transition', [KitchenController::class, 'transition'])->name('kitchen.tickets.transition');
    Route::get('/kitchen/tickets/{ticket}/print', [KitchenController::class, 'print'])->name('kitchen.tickets.print');

    Route::get('/restaurant/tables', [TableController::class, 'index'])->name('restaurant.tables.index');
    Route::get('/restaurant/tables/list', [TableController::class, 'list'])->name('restaurant.tables.list');
    Route::post('/restaurant/tables', [TableController::class, 'store'])->name('restaurant.tables.store');
    Route::put('/restaurant/tables/{table}', [TableController::class, 'update'])->name('restaurant.tables.update');
    Route::post('/restaurant/tables/reorder', [TableController::class, 'reorder'])->name('restaurant.tables.reorder');
    Route::delete('/restaurant/tables/{table}', [TableController::class, 'destroy'])->name('restaurant.tables.destroy');

    Route::get('/restaurant/orders', [OrderController::class, 'index'])->name('restaurant.orders.index');
    Route::post('/restaurant/orders', [OrderController::class, 'store'])->name('restaurant.orders.store');
    Route::get('/restaurant/orders/{order}', [OrderController::class, 'show'])->name('restaurant.orders.show');
    Route::put('/restaurant/orders/{order}', [OrderController::class, 'update'])->name('restaurant.orders.update');
    Route::post('/restaurant/orders/{order}/load', [OrderController::class, 'load'])->name('restaurant.orders.load');
    Route::post('/restaurant/orders/{order}/send', [OrderController::class, 'sendToKitchen'])->name('restaurant.orders.send');
    Route::post('/restaurant/orders/{order}/paid', [OrderController::class, 'markPaid'])->name('restaurant.orders.paid');
    Route::post('/restaurant/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('restaurant.orders.cancel');
    Route::post('/restaurant/orders/{order}/move', [OrderController::class, 'move'])->name('restaurant.orders.move');
    Route::post('/restaurant/orders/{order}/merge', [OrderController::class, 'merge'])->name('restaurant.orders.merge');
    Route::get('/restaurant/orders/{order}/print', [OrderController::class, 'print'])->name('restaurant.orders.print');

    Route::get('/restaurant/insights', [InsightsController::class, 'index'])->name('restaurant.insights.index');
    Route::get('/restaurant/insights/export', [InsightsController::class, 'export'])->name('restaurant.insights.export');
});
