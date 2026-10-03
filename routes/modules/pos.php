<?php

use App\Http\Controllers\HeldBillController;
use App\Http\Controllers\PosController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RoleMiddleware::class . ':shop_owner,employee,admin,restaurant,merchant'])
    ->group(function () {
        Route::get('/pos/held', [HeldBillController::class, 'index'])->name('pos.held.index');
        Route::post('/pos/held', [HeldBillController::class, 'store'])->name('pos.held.store');
        Route::put('/pos/held/{heldBill}', [HeldBillController::class, 'update'])->name('pos.held.update');
        Route::post('/pos/held/{heldBill}/resume', [HeldBillController::class, 'resume'])->name('pos.held.resume');
        Route::post('/pos/held/{heldBill}/acknowledge', [HeldBillController::class, 'acknowledgeResume'])->name('pos.held.acknowledge');
        Route::delete('/pos/held/{heldBill}', [HeldBillController::class, 'destroy'])->name('pos.held.destroy');

        Route::post('/pos/layout', [PosController::class, 'saveLayout'])->name('pos.layout.save');
        Route::delete('/pos/layout', [PosController::class, 'resetLayout'])->name('pos.layout.reset');
        Route::post('/pos/layout/apply-team', [PosController::class, 'applyTeamLayout'])->name('pos.layout.apply-team');
    });
