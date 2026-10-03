<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\ProductImeiController;
use App\Http\Controllers\ProductsController;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/products/search', [ProductsController::class, 'search'])->name('products.search');
    Route::get('/products/searchWithoutBarcode', [ProductsController::class, 'searchWithoutBarcode']);
    Route::get('/products/searchAll', [ProductsController::class, 'searchAllProducts']);
    Route::get('/products/search-barcode', [ProductsController::class, 'searchBarcode'])->name('products.search-barcode');
    Route::get('/products/get-suppliers', [ProductsController::class, 'getProductSuppliers'])
        ->middleware(PermissionMiddleware::class . ':view_purchase_bills')
        ->name('products.get-suppliers');
    Route::get('/products/categories', [ProductsController::class, 'getCategories'])->name('products.categories');
    Route::post('/products/check-barcodes', [ProductsController::class, 'checkBarcodes'])->name('products.check-barcodes');

    Route::get('/products/imei/check', [ProductImeiController::class, 'checkExists'])->name('products.imei.check');
    Route::get('/products/imei/search', [ProductImeiController::class, 'search'])->name('products.imei.search');
    Route::get('/products/{product}/imeis', [ProductImeiController::class, 'index'])->name('products.imeis.index');
    Route::get('/products/{product}/imeis/available', [ProductImeiController::class, 'available'])->name('products.imeis.available');
    Route::post('/products/{product}/imeis', [ProductImeiController::class, 'store'])->name('products.imeis.store');
    Route::delete('/products/{product}/imeis/{imei}', [ProductImeiController::class, 'destroy'])->name('products.imeis.destroy');
});

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])->group(function () {
    Route::get('/barcode-search', function () {
        return view('products.barcode-search', ['results' => null, 'searched' => false]);
    })->middleware(PermissionMiddleware::class . ':view_products')->name('barcode.search');
});

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])->group(function () {
    Route::resource('products', ProductsController::class)->except(['show']);
    Route::post('/products/{product}/add-quantity', [ProductsController::class, 'addQuantity'])->name('products.add-quantity');
    Route::post('/products/{product}/add-variants', [ProductsController::class, 'addVariants'])->name('products.addVariants');
    Route::post('/products/{product}/toggle-active', [ProductsController::class, 'toggleActive'])->name('products.toggle-active');
    Route::post('/products/bulk-status', [ProductsController::class, 'bulkUpdateStatus'])->name('products.bulk-status');
    Route::get('/products/export', [ProductsController::class, 'export'])->name('products.export');
    Route::get('/products/out-of-stock', [ProductsController::class, 'outOfStock'])->name('products.out-of-stock');
    Route::post('/products/out-of-stock', [ProductsController::class, 'outOfStockBulk'])
        ->middleware(PermissionMiddleware::class . ':edit_products')
        ->name('products.out-of-stock.bulk');
    Route::get('/products/next-id', [ProductsController::class, 'getNextProductId'])->name('products.next-id');

    Route::post('/batches', [BatchController::class, 'store'])->middleware(PermissionMiddleware::class . ':edit_products')->name('batches.store');
    Route::put('/batches/{batch}', [BatchController::class, 'update'])->middleware(PermissionMiddleware::class . ':edit_products')->name('batches.update');
    Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->middleware(PermissionMiddleware::class . ':edit_products')->name('batches.destroy');
});
