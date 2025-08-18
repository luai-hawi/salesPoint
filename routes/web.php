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
use App\Http\Controllers\Admin\ShopOwnerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\ShopOwner\EmployeeController;
use App\Http\Controllers\ShopOwner\ExpenseController;
use App\Http\Controllers\ShopOwner\DashboardController;
use App\Http\Controllers\FinancialDashboardController;
use Illuminate\Http\Request;

Route::redirect('/', '/dashboard', 301);

// ------------------- DASHBOARD WITH ROLE-BASED REDIRECT -------------------
Route::get('/dashboard', function () {
    $user = auth()->user();
    
    // Redirect admins to admin dashboard
    if ($user->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

    $customers = \App\Models\Customer::where('user_id', $ownerId)
        ->orderBy('name')
        ->get();

    $products = \App\Models\Product::where('user_id', $ownerId)
        ->select('id', 'name', 'selling_price', 'cost_price', 'barcode')
        ->get();

    $totalToday = \App\Models\Bill::where('user_id', $ownerId)
        ->whereDate('created_at', Carbon::today())
        ->sum('total_price');

    return view('dashboard', compact('products', 'totalToday', 'customers'));
})->middleware(['auth', 'verified', \App\Http\Middleware\RoleMiddleware::class.':shop_owner,employee,admin'])
    ->name('dashboard');



// Admin Routes (protected by auth and admin middleware)
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class.':admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // Shop Owners Management
    Route::resource('shop-owners', ShopOwnerController::class)->except(['show']);
    Route::get('shop-owners/{shopOwner}', [ShopOwnerController::class, 'show'])->name('shop-owners.show');
    Route::post('shop-owners/{shopOwner}/toggle-status', [ShopOwnerController::class, 'toggleStatus'])->name('shop-owners.toggle-status');
    
    // Employee Management Routes
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [ShopOwnerController::class, 'allEmployees'])->name('index');
        Route::get('/create', [ShopOwnerController::class, 'createEmployee'])->name('create');
        Route::post('/', [ShopOwnerController::class, 'storeEmployee'])->name('store');
        Route::get('/{employee}/edit', [ShopOwnerController::class, 'editEmployee'])->name('edit');
        Route::put('/{employee}', [ShopOwnerController::class, 'updateEmployee'])->name('update');
        Route::delete('/{employee}', [ShopOwnerController::class, 'destroyEmployee'])->name('destroy');
    });
});

// Additional middleware for admin role
Route::middleware(['auth'])->group(function () {
    Route::get('/admin', function () {
        return redirect()->route('admin.dashboard');
    });
});

// ------------------- SHOP OWNER AND EMPLOYEE ROUTES -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class.':shop_owner,employee'])
    ->group(function () {

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Products
        Route::resource('products', ProductsController::class)->except(['show']);
        Route::post('/products/{product}/add-quantity', [ProductsController::class, 'addQuantity']);
        Route::get('/products/search', [ProductsController::class, 'search'])->name('products.search');
        Route::get('/products/searchWithoutBarcode', [ProductsController::class, 'searchWithoutBarcode']);
        Route::get('/products/searchAll', [ProductsController::class, 'searchAllProducts']);
        Route::get('/products/export', [ProductsController::class, 'export'])->name('products.export');

        // Enhanced Bills Routes
        Route::resource('bills', BillsController::class);
        Route::get('/bills/quick-stats', [BillsController::class, 'quickStats'])->name('bills.quick-stats');
        Route::get('/bills/search-api', [BillsController::class, 'search'])->name('bills.search');
        Route::post('/bills/{bill}/duplicate', [BillsController::class, 'duplicate'])->name('bills.duplicate');
        Route::post('/bills/quick-store', [BillsController::class, 'quickStore'])->name('bills.quick-store');

        // Customers & Payments
        Route::resource('customers', CustomerController::class);
        Route::get('customers/{customer}/payments', [CustomerController::class, 'showPayments'])->name('customers.payments');
        Route::post('customers/{customer}/payments', [CustomerController::class, 'storePayment'])->name('customers.payments.store');
        Route::put('payments/{customer_payment}', [CustomerController::class, 'updatePayment'])->name('payments.update');

        // Batches
        Route::post('/batches', [BatchController::class, 'store']);
        Route::put('/batches/{batch}', [BatchController::class, 'update']);
        Route::delete('/batches/{batch}', [BatchController::class, 'destroy']);

        // Delete payment
        Route::delete('payments/{payment}', function (Request $request, CustomerPayment $payment) {
            $customer = $payment->customer;
            $customer->balance -= $payment->amount;
            $customer->save();
            try {
                $payment->delete();
                return response()->json(['success' => true]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        })->name('payments.destroy');
    });

// ------------------- SHOP OWNER SPECIFIC ROUTES -------------------
Route::prefix('shopowner')
    ->as('shopowner.')
    ->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner'])
    ->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::get('employees/{employee}/payments', [EmployeeController::class, 'payments'])->name('employees.payments');
        Route::post('employees/{employee}/payments', [EmployeeController::class, 'storePayment'])->name('employees.storePayment');
        Route::delete('employees/payment/{payment}', [EmployeeController::class, 'destroyPayment'])->name('employees.destroyPayment');

        // Expenses
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

Route::prefix('shopowner')->name('shopowner.')->middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// ------------------- FINANCIAL DASHBOARD -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner'])->group(function () {
    Route::get('/dashboard/financial', [FinancialDashboardController::class, 'index'])
        ->name('dashboard.financial');

    Route::get('/sales-data', function () {
        $sales = \App\Models\Bill::selectRaw('MONTH(created_at) as month, SUM(total_price) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        return response()->json($sales);
    });
});

// ------------------- AUTH ROUTES -------------------
require __DIR__.'/auth.php';