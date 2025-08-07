<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\BillsController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\CustomerController;
use App\Models\CustomerPayment;
use App\Http\Controllers\BatchController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $customers = \App\Models\Customer::orderBy('name')->get();
    $products = \App\Models\Product::select('id', 'name', 'selling_price', 'cost_price', 'barcode')->get();
    // Calculate total of today's bills
    $totalToday = \App\Models\Bill::whereDate('created_at', Carbon::today())
                    ->sum('total_price');

    return view('dashboard', compact('products', 'totalToday', 'customers'));
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

    Route::get('customers/{customer}/payments', [CustomerController::class, 'showPayments'])
    ->name('customers.payments');

    Route::post('customers/{customer}/payments', [CustomerController::class, 'storePayment'])
        ->name('customers.payments.store');

    Route::put('payments/{customer_payment}', [CustomerController::class, 'updatePayment'])
    ->name('payments.update');


});
Route::resource('customers', CustomerController::class)->middleware(['auth', 'verified']);
Route::middleware('auth')->group(function () {
    Route::post('/batches', [BatchController::class, 'store']);
    Route::put('/batches/{batch}', [BatchController::class, 'update']);
    Route::delete('/batches/{batch}', [BatchController::class, 'destroy']);

    Route::delete('payments/{payment}', function (Request $request, CustomerPayment $payment) {
        $customer = $payment->customer;
        $customer->balance -= $payment->amount; // Adjust balance back
        $customer->save();
        try {
            $payment->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('payments.destroy');
});



require __DIR__.'/auth.php';
