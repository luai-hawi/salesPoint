<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillsController;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\CustomerController;
use App\Models\CustomerPayment;
use App\Http\Controllers\Admin\ShopOwnerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\ShopOwner\EmployeeController;
use App\Http\Controllers\ShopOwner\ExpenseController;
use App\Http\Controllers\ShopOwner\DashboardController;
use App\Http\Controllers\FinancialDashboardController;
use Illuminate\Http\Request;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseBillController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\IslamicSalesController;
use App\Http\Controllers\CapitalController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\PosController;



use App\Models\Product;

Route::redirect('/', '/dashboard', 301);
Route::get('/portfolio', function () {
    return view('portfolio');
})->name('portfolio');

Route::get('/auth/check', function () {
    return response()->json(['authenticated' => auth()->check()]);
})->middleware('auth');

// ------------------- DASHBOARD WITH ROLE-BASED REDIRECT -------------------
Route::get('/dashboard', [PosController::class, 'index'])->middleware(['auth', 'verified', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner,employee,admin,restaurant,merchant'])
    ->name('dashboard');



// Admin routes are registered in routes/modules/admin_accounts.php.

// Additional middleware for admin role
Route::middleware(['auth'])->group(function () {
    Route::get('/admin', function () {
        return redirect()->route('admin.dashboard');
    });
});

//-------------------ROUTES FOR ADMIN, EMPLOYEE, SHOP OWNER-------------------
// These routes are accessible to admin, shop owner, and employee roles
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])
    ->group(function () {
        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

// Full copy of the shop's own data (owner account only, every subscription tier).
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner,restaurant,merchant', 'throttle:6,1'])
    ->get('/dashboard/backup-data', [\App\Http\Controllers\ShopBackupController::class, 'download'])
    ->name('dashboard.backup-data');



use App\Http\Controllers\OfflineSyncController;

// Offline sync endpoint — batch-creates bills saved in IndexedDB while offline
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner,employee,restaurant,merchant'])
    ->post('/offline/sync', [OfflineSyncController::class, 'sync'])
    ->name('offline.sync');

// ------------------- SHOP OWNER AND EMPLOYEE ROUTES -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])
    ->group(function () {

        // Tags Management
        Route::get('/tags', [TagsController::class, 'index'])->name('tags.index');
        Route::post('/tags', [TagsController::class, 'store'])->name('tags.store');
        Route::delete('/tags/{tag}', [TagsController::class, 'destroy'])->name('tags.destroy');

        // Settings
        Route::middleware([\App\Http\Middleware\PermissionMiddleware::class . ':manage_settings'])->group(function () {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::post('/settings/product-settings', [SettingsController::class, 'updateProductSettings'])->name('settings.update-product');
            Route::post('/settings/image-limit', [SettingsController::class, 'updateImageLimit'])->name('settings.update-image-limit');
            Route::post('/settings/visibility', [SettingsController::class, 'updateVisibilitySettings'])->name('settings.update-visibility');
        });

        // Shop owners can manage visibility settings for their employee accounts (no manage_settings permission required)
        Route::post('/settings/employees/{user}/visibility', [SettingsController::class, 'updateEmployeeVisibilitySettings'])->name('settings.employee-visibility');

        // POS view mode toggle (accessible by all authenticated users)
        Route::post('/settings/pos-view-mode', [PosController::class, 'updateLegacyViewMode'])->name('settings.pos-view-mode');

        Route::get('/api/tags', [BillsController::class, 'getTags'])->name('api.tags');
        Route::get('/customers/{customer}/recent-payments', [CustomerController::class, 'getRecentPayments'])->name('customers.recent-payments');

        // Quick Payment for Customers
        Route::post('customers/{customer}/quick-payments', [CustomerController::class, 'quickStorePayment'])->name('customers.quick-payments.store');

        // Enhanced Bills Routes
        Route::resource('bills', BillsController::class);
        Route::get('/bills/quick-stats', [BillsController::class, 'quickStats'])->name('bills.quick-stats');
        Route::get('/bills/{bill}/preview', [BillsController::class, 'preview'])->name('bills.preview');
        Route::post('/bills/{bill}/duplicate', [BillsController::class, 'duplicate'])->name('bills.duplicate');
        Route::post('/bills/quick-store', [BillsController::class, 'quickStore'])->name('bills.quick-store');

        // Customers & Payments
        Route::resource('customers', CustomerController::class);
        Route::get('customers/{customer}/payments', [CustomerController::class, 'showPayments'])->name('customers.payments');
        Route::post('customers/{customer}/payments', [CustomerController::class, 'storePayment'])->name('customers.payments.store');
        Route::put('payments/{customer_payment}', [CustomerController::class, 'updatePayment'])->name('payments.update');

        // Delete payment
        Route::delete('payments/{payment}', [CustomerController::class, 'deletePayment'])->name('payments.destroy');

        // Sales & Promotions (gated by tier feature flag)
        Route::middleware('tier.feature:sales_promotions')->group(function () {
            Route::resource('sales', SaleController::class)->except(['show', 'create', 'edit']);
            Route::post('/sales/{sale}/toggle-active', [SaleController::class, 'toggleActive'])->name('sales.toggle-active');
            Route::post('/sales/{sale}/extend-date', [SaleController::class, 'extendDate'])->name('sales.extend-date');
        });
        // active-sales API is used by the dashboard sell point for real-time discounts — always accessible
        Route::get('/api/active-sales', [SaleController::class, 'activeSales'])->name('sales.active');
    });

// ------------------- SHOP OWNER AND EMPLOYEE SPECIFIC ROUTES -------------------
Route::prefix('shopowner')
    ->as('shopowner.')
    ->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])
    ->group(function () {
        Route::middleware([\App\Http\Middleware\PermissionMiddleware::class . ':manage_employees'])->group(function () {
            Route::resource('employees', EmployeeController::class);
            Route::get('employees/{employee}/payments', [EmployeeController::class, 'payments'])->name('employees.payments');
            Route::post('employees/{employee}/payments', [EmployeeController::class, 'storePayment'])->name('employees.storePayment');
            Route::delete('employees/payment/{payment}', [EmployeeController::class, 'destroyPayment'])->name('employees.destroyPayment');
        });

        // Expenses
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

// ------------------- FINANCIAL DASHBOARD -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', \App\Http\Middleware\PermissionMiddleware::class . ':view_financial', 'tier.feature:financial_dashboard'])->group(function () {
    Route::get('/dashboard/financial', [FinancialDashboardController::class, 'index'])
        ->name('dashboard.financial');

    Route::get('/dashboard/financial/print-report', [FinancialDashboardController::class, 'printComprehensiveReport'])
        ->name('dashboard.financial.print-report');

    Route::get('/sales-data', function () {
        $sales = \App\Models\Bill::selectRaw('MONTH(created_at) as month, SUM(total_price) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        return response()->json($sales);
    });
    Route::get('/dashboard/export-data', [FinancialDashboardController::class, 'exportData'])->name('dashboard.export-data');

    // Capital Entries
    Route::post('/dashboard/capital', [CapitalController::class, 'store'])->name('capital.store');
    Route::delete('/dashboard/capital/{id}', [CapitalController::class, 'destroy'])->name('capital.destroy');
});


// Hisba Analytics Dashboard
Route::get('/hisba', function () {
    return view('hisba');
})->name('hisba');



// Batch Image Compression Route with Progress Tracking
Route::get('/compress-and-cleanup-images', function () {
    return app(\App\Http\Controllers\Admin\StorageController::class)->legacy(request());
})->name('compress.cleanup.images')->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin']);

// Simple route for quick compression (smaller batches)
Route::get('/quick-compress-images', function () {
    return app(\App\Http\Controllers\Admin\StorageController::class)->quickCompress();
})->name('quick.compress.images')->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin']);


// Add this to your web.php routes
Route::get('/api/categories', function () {
    $user = auth()->user();
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

    $search = request('search', '');

    $query = \App\Models\Product::where('user_id', $ownerId)
        ->whereNotNull('category')
        ->where('category', '!=', '')
        ->distinct();

    if ($search) {
        $query->where('category', 'like', "%{$search}%");
    }

    $categories = $query->pluck('category')
        ->sort()
        ->values();

    // Check if there are uncategorized products
    $hasUncategorized = \App\Models\Product::where('user_id', $ownerId)
        ->where(function ($q) {
            $q->whereNull('category')
                ->orWhere('category', '');
        })
        ->exists();

    // Add "Uncategorized" if there are uncategorized products and it matches the search (or no search)
    if ($hasUncategorized && (!$search || stripos('Uncategorized', $search) !== false)) {
        $categories = collect(['Uncategorized'])->merge($categories);
    }

    return response()->json($categories);
})->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant']);

Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant'])
    ->group(function () {

        // Payments and Receipts
        Route::get('/payments-receipts', [PaymentReceiptController::class, 'index'])->name('payments-receipts.index');
        Route::post('/payments-receipts', [PaymentReceiptController::class, 'store'])->name('payments-receipts.store');
        Route::get('/api/customers/search', [PaymentReceiptController::class, 'getCustomers'])->name('api.customers.search');
        Route::get('/api/employees/search', [PaymentReceiptController::class, 'getEmployees'])->name('api.employees.search');
        Route::get('/api/suppliers/search-payment', [PaymentReceiptController::class, 'getSuppliers'])->name('api.suppliers.search-payment');

        // ─── Installments / Deferred Payments ────────────────────────────────
        // Due-count endpoint (all authenticated users with access)
        Route::get('/installments/due-count', [InstallmentController::class, 'dueCount'])->name('installments.due-count');

        // ── All installment routes are gated by the tier feature flag ──────────
        Route::middleware('tier.feature:installments')->group(function () {

            // View installments page
            Route::get('/installments', [InstallmentController::class, 'index'])
                ->name('installments.index')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':view_installments');

            // Create standalone plan
            Route::post('/installments', [InstallmentController::class, 'store'])
                ->name('installments.store')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':create_installments');

            // Create plan from bill (AJAX)
            Route::post('/installments/from-bill', [InstallmentController::class, 'storeFromBill'])
                ->name('installments.from-bill')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':create_installments');

            // Dismiss all today
            Route::post('/installments/dismiss-all-today', [InstallmentController::class, 'dismissAllToday'])
                ->name('installments.dismiss-all');

            // Update plan meta
            Route::put('/installments/{plan}', [InstallmentController::class, 'update'])
                ->name('installments.update')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':create_installments');

            // Delete plan
            Route::delete('/installments/{plan}', [InstallmentController::class, 'destroy'])
                ->name('installments.destroy')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':delete_installments');

            // Mark payment as paid
            Route::post('/installments/payments/{payment}/mark-paid', [InstallmentController::class, 'markPaid'])
                ->name('installments.mark-paid');

            // Dismiss single payment for today
            Route::post('/installments/payments/{payment}/dismiss', [InstallmentController::class, 'dismissToday'])
                ->name('installments.dismiss');

            // Update single payment (date/amount)
            Route::put('/installments/payments/{payment}', [InstallmentController::class, 'updatePayment'])
                ->name('installments.payments.update')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':create_installments');

            // Delete single payment row
            Route::delete('/installments/payments/{payment}', [InstallmentController::class, 'destroyPayment'])
                ->name('installments.payments.destroy')
                ->middleware(\App\Http\Middleware\PermissionMiddleware::class . ':delete_installments');
        }); // end tier.feature:installments
    });

// ------------------- ISLAMIC SALES PWA -------------------
// Offline-first PWA with local SQLite database - no server database operations
Route::get('/islam', [IslamicSalesController::class, 'index'])->name('islam');

// ------------------- REPORTS -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', \App\Http\Middleware\PermissionMiddleware::class . ':view_reports'])->group(function () {
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/generate', [\App\Http\Controllers\ReportsController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/customer-bill-details', [\App\Http\Controllers\ReportsController::class, 'customerBillDetailsPage'])->name('reports.customer-bill-details');
    Route::get('/reports/customer-bill-details/data', [\App\Http\Controllers\ReportsController::class, 'customerBillDetails'])->name('reports.customer-bill-details.data');
});

// ------------------- FEATURE MODULE ROUTES -------------------
// Each file in routes/modules/ registers the routes of one feature module.
foreach (glob(__DIR__ . '/modules/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}

// ------------------- LANGUAGE ROUTES -------------------
require __DIR__ . '/language.php';

// ------------------- AUTH ROUTES -------------------
require __DIR__ . '/auth.php';
