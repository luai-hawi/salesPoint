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



use App\Models\Product;

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
})->middleware(['auth', 'verified', \App\Http\Middleware\RoleMiddleware::class.':shop_owner,employee,admin,restaurant,merchant'])
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

//-------------------ROUTES FOR ADMIN, EMPLOYEE, SHOP OWNER-------------------
// These routes are accessible to admin, shop owner, and employee roles
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class.':admin,shop_owner,employee,restaurant,merchant'])
    ->group(function () {
        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });



// ------------------- SHOP OWNER AND EMPLOYEE ROUTES -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class.':shop_owner,employee,restaurant,merchant'])
    ->group(function () {

        Route::get('/customers/{customer}/recent-payments', [CustomerController::class, 'getRecentPayments'])->name('customers.recent-payments');

        // Quick Payment for Customers
        Route::post('customers/{customer}/quick-payments', [CustomerController::class, 'quickStorePayment'])->name('customers.quick-payments.store');

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
    ->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner,restaurant,merchant'])
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

// ------------------- FINANCIAL DASHBOARD -------------------
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':shop_owner,restaurant,merchant'])->group(function () {
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






// Batch Image Compression Route with Progress Tracking
Route::get('/compress-and-cleanup-images', function () {
    set_time_limit(300); // Set 5 minutes timeout
    
    $batchSize = request('batch', 10); // Process 10 images at a time
    $step = request('step', 'start'); // start, compress, cleanup, complete
    $offset = request('offset', 0);
    
    if ($step === 'start') {
        // Return the main page with JavaScript for batch processing
        return view('admin.image-compression');
    }
    
    $results = [
        'compressed' => 0,
        'deleted' => 0,
        'errors' => [],
        'deleted_files' => [],
        'hasMore' => false,
        'nextOffset' => $offset
    ];
    
    // Helper function to compress image using GD
    $compressImage = function($source, $destination, $quality = 75, $maxWidth = 800) {
        $info = getimagesize($source);
        if ($info === false) {
            return false;
        }
        
        $mime = $info['mime'];
        
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        // Get original dimensions
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        
        // Calculate new dimensions
        if ($originalWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($originalHeight * $maxWidth) / $originalWidth;
        } else {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        // Resize image
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Save compressed image
        $result = false;
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($newImage, $destination, $quality);
                break;
            case 'image/png':
                $pngQuality = 9 - round(($quality / 100) * 9);
                $result = imagepng($newImage, $destination, $pngQuality);
                break;
            case 'image/gif':
                $result = imagegif($newImage, $destination);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($newImage);
        
        return $result;
    };
    
    try {
        if ($step === 'compress') {
            // Get products with images in batches
            $products = \App\Models\Product::whereNotNull('pictures')
                ->where('pictures', '!=', '')
                ->skip($offset)
                ->take($batchSize)
                ->get();
            
            $processedCount = 0;
            
            foreach ($products as $product) {
                $pictures = json_decode($product->pictures, true);
                
                if (is_array($pictures)) {
                    foreach ($pictures as $picture) {
                        $fullPath = storage_path('app/public/' . $picture);
                        
                        if (file_exists($fullPath)) {
                            try {
                                $imageInfo = getimagesize($fullPath);
                                if ($imageInfo !== false) {
                                    if ($compressImage($fullPath, $fullPath, 75, 800)) {
                                        $results['compressed']++;
                                    } else {
                                        $results['errors'][] = "Failed to compress: {$picture}";
                                    }
                                }
                            } catch (\Exception $e) {
                                $results['errors'][] = "Error compressing {$picture}: " . $e->getMessage();
                            }
                        }
                    }
                }
                $processedCount++;
            }
            
            // Check if there are more products to process
            $totalProducts = \App\Models\Product::whereNotNull('pictures')
                ->where('pictures', '!=', '')
                ->count();
            
            $results['hasMore'] = ($offset + $batchSize) < $totalProducts;
            $results['nextOffset'] = $offset + $batchSize;
            $results['progress'] = min(100, round((($offset + $processedCount) / $totalProducts) * 100));
            
        } elseif ($step === 'cleanup') {
            // Collect all used images
            $usedImages = [];
            $products = \App\Models\Product::whereNotNull('pictures')
                ->where('pictures', '!=', '')
                ->get();
            
            foreach ($products as $product) {
                $pictures = json_decode($product->pictures, true);
                if (is_array($pictures)) {
                    $usedImages = array_merge($usedImages, $pictures);
                }
            }
            
            // Find and delete unused images
            $productsDirectory = storage_path('app/public/products');
            
            if (is_dir($productsDirectory)) {
                $allFiles = [];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($productsDirectory, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'gif'])) {
                        $relativePath = 'products/' . $iterator->getSubPathName();
                        $allFiles[] = $relativePath;
                    }
                }
                
                $unusedImages = array_diff($allFiles, $usedImages);
                
                foreach ($unusedImages as $unusedImage) {
                    $fullPath = storage_path('app/public/' . $unusedImage);
                    
                    if (file_exists($fullPath)) {
                        try {
                            unlink($fullPath);
                            $results['deleted']++;
                            $results['deleted_files'][] = $unusedImage;
                        } catch (\Exception $e) {
                            $results['errors'][] = "Error deleting {$unusedImage}: " . $e->getMessage();
                        }
                    }
                }
                
                // Clean up empty directories
                $removeEmptyDirs = function($dir) use (&$removeEmptyDirs) {
                    if (!is_dir($dir)) return false;
                    
                    $files = array_diff(scandir($dir), ['.', '..']);
                    foreach ($files as $file) {
                        $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                        if (is_dir($fullPath)) {
                            $removeEmptyDirs($fullPath);
                        }
                    }
                    
                    $files = array_diff(scandir($dir), ['.', '..']);
                    if (empty($files) && $dir !== storage_path('app/public/products')) {
                        rmdir($dir);
                    }
                    
                    return true;
                };
                
                $removeEmptyDirs($productsDirectory);
            }
        }
        
    } catch (\Exception $e) {
        $results['errors'][] = "General error: " . $e->getMessage();
    }
    
    return response()->json($results);
    
})->name('compress.cleanup.images')->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin']);

// Simple route for quick compression (smaller batches)
Route::get('/quick-compress-images', function () {
    set_time_limit(60); // 1 minute timeout
    
    $results = ['compressed' => 0, 'errors' => []];
    
    // Process only first 5 products to avoid timeout
    $products = \App\Models\Product::whereNotNull('pictures')
        ->where('pictures', '!=', '')
        ->take(5)
        ->get();
    
    foreach ($products as $product) {
        $pictures = json_decode($product->pictures, true);
        
        if (is_array($pictures)) {
            foreach ($pictures as $picture) {
                $fullPath = storage_path('app/public/' . $picture);
                
                if (file_exists($fullPath)) {
                    try {
                        $imageInfo = getimagesize($fullPath);
                        if ($imageInfo !== false) {
                            // Simple compression using imagejpeg quality
                            $image = null;
                            switch ($imageInfo['mime']) {
                                case 'image/jpeg':
                                    $image = imagecreatefromjpeg($fullPath);
                                    if ($image) {
                                        imagejpeg($image, $fullPath, 75);
                                        $results['compressed']++;
                                    }
                                    break;
                                case 'image/png':
                                    $image = imagecreatefrompng($fullPath);
                                    if ($image) {
                                        imagepng($image, $fullPath, 6);
                                        $results['compressed']++;
                                    }
                                    break;
                            }
                            if ($image) imagedestroy($image);
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = "Error: {$picture} - " . $e->getMessage();
                    }
                }
            }
        }
    }
    
    return response()->json($results);
})->name('quick.compress.images')->middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin']);



// ------------------- LANGUAGE ROUTES -------------------
require __DIR__.'/language.php';

// ------------------- AUTH ROUTES -------------------
require __DIR__.'/auth.php';