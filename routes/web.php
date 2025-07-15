<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\BillsController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $products = \App\Models\Product::all();
            // Prepare products data for JavaScript
        $productsForJS = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'pictures' => $p->pictures ? json_decode($p->pictures, true) : [],
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'barcode' => $p->barcode,
            ];
        })->toArray();
        

        return view('dashboard', compact('productsForJS', 'products'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('products', ProductsController::class);
    Route::resource('bills', BillsController::class);
    Route::post('/products/{product}/add-quantity', [ProductsController::class, 'addQuantity']);

});

require __DIR__.'/auth.php';
