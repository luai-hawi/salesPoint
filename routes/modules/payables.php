<?php

use App\Http\Controllers\PurchaseBillController;
use App\Http\Controllers\SupplierController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])->group(function () {
    Route::resource('suppliers', SupplierController::class);
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'storePayment'])->name('suppliers.payments.store');
    Route::get('suppliers/{supplier}/recent-payments', [SupplierController::class, 'getRecentPayments'])->name('suppliers.recent-payments');
    Route::get('suppliers/{supplier}/more-payments', [SupplierController::class, 'getMorePayments'])->name('suppliers.more-payments');
    Route::get('suppliers/{supplier}/print-report', [SupplierController::class, 'printSupplierReport'])->name('suppliers.print-report');
    Route::delete('supplier-payments/{supplier_payment}', [SupplierController::class, 'deletePayment'])->name('supplier-payments.destroy');

    Route::resource('purchase-bills', PurchaseBillController::class);
    Route::get('purchase-bills/{purchaseBill}/print', [PurchaseBillController::class, 'print'])->name('purchase-bills.print');
    Route::post('purchase-bills/{purchaseBill}/duplicate', [PurchaseBillController::class, 'duplicate'])->name('purchase-bills.duplicate');
    Route::post('purchase-bills/{purchaseBill}/payments', [PurchaseBillController::class, 'storePayment'])->name('purchase-bills.payments.store');
    Route::delete('purchase-bills/{purchaseBill}/payments/{supplierPayment}', [PurchaseBillController::class, 'destroyPayment'])->name('purchase-bills.payments.destroy');

    Route::get('api/suppliers/search', [SupplierController::class, 'search'])->name('api.suppliers.search');
    Route::get('api/purchase-bills/search', [PurchaseBillController::class, 'search'])->name('api.purchase-bills.search');
});
