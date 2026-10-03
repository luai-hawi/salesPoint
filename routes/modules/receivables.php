<?php

use App\Http\Controllers\BillsController;
use App\Http\Controllers\CustomerController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])
    ->group(function () {
        Route::post('/bills/{bill}/payments', [BillsController::class, 'storePayment'])->name('bills.payments.store');
        Route::delete('/bills/{bill}/payments/{payment}', [BillsController::class, 'destroyPayment'])->name('bills.payments.destroy');
        Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    });
