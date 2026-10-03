<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmployeeController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\ShopOwnerController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', RoleMiddleware::class . ':admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/download-backup', [AdminDashboardController::class, 'downloadBackup'])->name('dashboard.download-backup');

        Route::get('shop-owners/expiring-licenses', [ShopOwnerController::class, 'expiringLicenses'])->name('shop-owners.expiring-licenses');
        Route::delete('shop-owners/delete-expired', [ShopOwnerController::class, 'deleteExpiredTempAccounts'])->name('shop-owners.delete-expired');
        Route::post('shop-owners/disable-expired', [ShopOwnerController::class, 'disableExpiredTempAccounts'])->name('shop-owners.disable-expired');
        Route::delete('shop-owners/delete-disabled-expired', [ShopOwnerController::class, 'deleteDisabledExpiredAccounts'])->name('shop-owners.delete-disabled-expired');
        Route::post('shop-owners/{shopOwner}/toggle-status', [ShopOwnerController::class, 'toggleStatus'])->name('shop-owners.toggle-status');
        Route::post('shop-owners/{shopOwner}/mark-paid', [ShopOwnerController::class, 'markPaid'])->name('shop-owners.mark-paid');
        Route::delete('shop-owners/{shopOwner}/payments/{payment}', [ShopOwnerController::class, 'deletePayment'])->name('shop-owners.payments.destroy');
        Route::match(['put', 'patch'], 'shop-owners/{shopOwner}/note', [ShopOwnerController::class, 'note'])->name('shop-owners.note');
        Route::post('shop-owners/{shopOwner}/convert-to-full', [ShopOwnerController::class, 'convertToFull'])->name('shop-owners.convert-to-full');
        Route::post('shop-owners/{shopOwner}/impersonate', [ShopOwnerController::class, 'impersonate'])->name('shop-owners.impersonate');
        Route::resource('shop-owners', ShopOwnerController::class)->except(['show']);
        Route::get('shop-owners/{shopOwner}', [ShopOwnerController::class, 'show'])->name('shop-owners.show');

        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/', [AdminEmployeeController::class, 'index'])->name('index');
            Route::get('/create', [AdminEmployeeController::class, 'create'])->name('create');
            Route::post('/', [AdminEmployeeController::class, 'store'])->name('store');
            Route::get('/{employee}/edit', [AdminEmployeeController::class, 'edit'])->name('edit');
            Route::put('/{employee}', [AdminEmployeeController::class, 'update'])->name('update');
            Route::delete('/{employee}', [AdminEmployeeController::class, 'destroy'])->name('destroy');
        });

        Route::get('audit', [AdminAuditController::class, 'index'])->name('audit.index');
        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth'])->post('admin/impersonate/stop', [ShopOwnerController::class, 'stopImpersonating'])->name('admin.impersonate.stop');
