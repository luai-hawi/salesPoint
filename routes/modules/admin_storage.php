<?php

use App\Http\Controllers\Admin\StorageController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', RoleMiddleware::class . ':admin'])
    ->prefix('admin/storage')
    ->name('admin.storage.')
    ->group(function (): void {
        Route::get('/', [StorageController::class, 'index'])->name('index');
        Route::get('/overview', [StorageController::class, 'overview'])->name('overview');
        Route::get('/shops/{shop}', [StorageController::class, 'shop'])->name('shop');
        Route::post('/shops/{shop}/recalculate', [StorageController::class, 'recalculateShop'])->name('shop.recalculate');
        Route::post('/shops/{shop}/delete-images', [StorageController::class, 'deleteImages'])->name('shop.images.delete');
        Route::post('/shops/{shop}/clean-missing-references', [StorageController::class, 'cleanMissingReferences'])->name('shop.references.clean');
        Route::post('/shops/{shop}/download-images', [StorageController::class, 'downloadImages'])->name('shop.images.download');
        Route::post('/compression/preview', [StorageController::class, 'previewCompression'])->name('compression.preview');
        Route::post('/compression/run', [StorageController::class, 'runCompression'])->name('compression.run');
        Route::post('/cleanup/preview', [StorageController::class, 'previewCleanup'])->name('cleanup.preview');
        Route::post('/cleanup/run', [StorageController::class, 'runCleanup'])->name('cleanup.run');
        Route::post('/backups/create', [StorageController::class, 'createBackup'])->name('backups.create');
        Route::get('/backups/{filename}', [StorageController::class, 'downloadBackup'])->where('filename', '.+')->name('backups.download');
        Route::get('/logs/{filename}', [StorageController::class, 'downloadLog'])->where('filename', '.+')->name('logs.download');
    });

Route::middleware(['auth', RoleMiddleware::class . ':admin'])->group(function (): void {
    Route::post('/compress-and-cleanup-images', [StorageController::class, 'legacyMutate'])->name('admin.storage.legacy.mutate');
    Route::post('/quick-compress-images', [StorageController::class, 'quickCompressMutate'])->name('admin.storage.legacy.quick');
});
