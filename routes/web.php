<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\BillsController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $products = \App\Models\Product::select('id', 'name', 'selling_price', 'cost_price', 'barcode')->get();
    // Calculate total of today's bills
    $totalToday = \App\Models\Bill::whereDate('created_at', Carbon::today())
                    ->sum('total_price');

    return view('dashboard', compact('products', 'totalToday'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('products', ProductsController::class)->except(['show']);
    Route::resource('bills', BillsController::class);
    Route::post('/products/{product}/add-quantity', [ProductsController::class, 'addQuantity']);
    Route::get('/products/search', [ProductsController::class, 'search'])->name('products.search');
    Route::get('/products/searchWithoutBarcode', [ProductsController::class, 'searchWithoutBarcode']);



});

require __DIR__.'/auth.php';
