<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Http\Request;
use App\Models\Batch;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_products')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $query = Product::where('user_id', $ownerId);

        if ($search = $request->query('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$search}%"])
                    ->orWhere('cost_price', 'like', "%{$search}%")
                    ->orWhere('selling_price', 'like', "%{$search}%")
                    ->orWhereHas('barcodes', function ($qb) use ($search) {
                        $qb->whereRaw('LOWER(barcode) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->query('low_stock')) {
            $query->where('quantity', '<=', 5);
        }

        $products = $query->paginate(20)->appends($request->query());

        if ($request->ajax()) {
            return view('products.index', compact('products'))->render();
        }

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        return view('products.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255', // Add category validation
            'barcode' => 'nullable|string|max:255',
            'additional_barcodes' => 'nullable|array',
            'additional_barcodes.*' => 'nullable|string|max:255',
            'pictures' => 'nullable|array',
            'pictures.*' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cost_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'has_tags' => 'boolean',
        ]);

        // Check image limit
        if ($request->hasFile('pictures')) {
            $owner = \App\Models\User::find($ownerId);
            $currentImages = $this->countTotalImages($ownerId);
            $newImagesCount = count($request->file('pictures'));
            if ($currentImages + $newImagesCount > $owner->image_limit) {
                return back()->withErrors(['pictures' => 'Image limit exceeded. You can upload a maximum of ' . $owner->image_limit . ' images total.'])->withInput();
            }
        }

        $product = new Product();
        $product->name = $request->name;
        $product->category = $request->category; // Add category
        $product->barcode = trim($request->barcode);
        $product->quantity = $request->quantity;
        $product->cost_price = round($request->cost_price, 2);
        $product->selling_price = $request->selling_price;
        $product->user_id = $ownerId;
        $product->has_tags = $request->has('has_tags');

        if ($request->hasFile('pictures')) {
            $pictures = [];
            foreach ($request->file('pictures') as $picture) {
                $pictures[] = $picture->store('products', 'public');
            }
            $product->pictures = json_encode($pictures);
        }

        $product->save();

        // Save additional barcodes
        if ($request->has('additional_barcodes')) {
            $barcodes = array_filter($request->additional_barcodes); // Remove empty values
            foreach ($barcodes as $barcode) {
                if (!empty(trim($barcode))) {
                    ProductBarcode::create([
                        'product_id' => $product->id,
                        'barcode' => trim($barcode),
                    ]);
                }
            }
        }

        // create initial batch for this product
        $batch = new Batch();
        $batch->product_id = $product->id;
        $batch->quantity = $request->quantity;
        $batch->cost_price = $request->cost_price;
        $batch->user_id = $ownerId;
        $batch->save();

        return redirect()->route('products.create')->with('success', 'Product created successfully.');
    }


    public function edit(Product $product)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('edit_products')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeProduct($product);
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('edit_products')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $this->authorizeProduct($product);

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255', // Add category validation
            'barcode' => 'nullable|string|max:255',
            'additional_barcodes' => 'nullable|array',
            'additional_barcodes.*' => 'nullable|string|max:255',
            'pictures' => 'nullable|array',
            'pictures.*' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cost_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'has_tags' => 'boolean',
        ]);

        // Check image limit for updates
        if ($request->hasFile('pictures')) {
            $owner = \App\Models\User::find($ownerId);
            $currentImages = $this->countTotalImages($ownerId);
            $oldImagesCount = $product->pictures ? count(json_decode($product->pictures, true) ?? []) : 0;
            $newImagesCount = count($request->file('pictures'));
            if ($currentImages - $oldImagesCount + $newImagesCount > $owner->image_limit) {
                return back()->withErrors(['pictures' => 'Image limit exceeded. You can upload a maximum of ' . $owner->image_limit . ' images total.'])->withInput();
            }
        }

        $product->name = $request->name;
        $product->category = $request->category; // Add category
        $product->barcode = trim($request->barcode);
        $product->cost_price = round($request->cost_price, 2);
        $product->selling_price = $request->selling_price;
        $product->has_tags = $request->has('has_tags');

        // Handle image updates
        if ($request->hasFile('pictures')) {
            // Delete old images if they exist
            $this->deleteProductImages($product);

            // Upload new images
            $pictures = [];
            foreach ($request->file('pictures') as $picture) {
                $pictures[] = $picture->store('products', 'public');
            }
            $product->pictures = json_encode($pictures);
        }

        $product->save();

        // Update additional barcodes
        if ($request->has('additional_barcodes')) {
            // Get existing barcodes for this product
            $existingBarcodes = $product->barcodes()->pluck('barcode')->toArray();

            // Get new barcodes from request
            $newBarcodes = array_filter($request->additional_barcodes); // Remove empty values
            $newBarcodes = array_map('trim', $newBarcodes); // Trim all barcodes

            // Check for duplicates in the new barcodes
            $uniqueBarcodes = array_unique($newBarcodes);
            if (count($newBarcodes) !== count($uniqueBarcodes)) {
                return back()->withErrors(['additional_barcodes' => 'Duplicate barcodes detected. Please use unique barcodes for each product.'])
                    ->withInput();
            }

            // Allow duplicate barcodes across different products

            // Delete existing barcodes
            $product->barcodes()->delete();

            // Add new barcodes
            foreach ($newBarcodes as $barcode) {
                if (!empty($barcode)) {
                    ProductBarcode::create([
                        'product_id' => $product->id,
                        'barcode' => $barcode,
                    ]);
                }
            }
        } else {
            // If no additional barcodes are submitted, delete all existing ones
            $product->barcodes()->delete();
        }

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function addQuantity(Request $request, Product $product)
    {
        $this->authorizeProduct($product);

        $request->validate(['amount' => 'required|integer|min:1']);
        $product->increment('quantity', $request->amount);

        return response()->json(['success' => true, 'new_quantity' => $product->quantity]);
    }

    public function toggleActive(Product $product)
    {
        $this->authorizeProduct($product);

        $wasActive = $product->is_active;
        $product->is_active = !$product->is_active;

        // Delete product images when deactivating to free storage
        if ($wasActive && !$product->is_active) {
            $this->deleteProductImages($product);
            // Clear the pictures field to remove references to deleted images
            $product->pictures = null;
        }

        $product->save();

        $status = $product->is_active ? 'activated' : 'deactivated';

        return redirect()->route('products.index')->with('success', "Product {$status} successfully.");
    }

    public function destroy(Product $product)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_products')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeProduct($product);

        // Delete associated images before deleting the product
        $this->deleteProductImages($product);

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    // Get all categories for the current user
    public function getCategories(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $categories = Product::where('user_id', $ownerId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json($categories);
    }

    // Enhanced search for all products with quantity ordering
    public function searchAllProducts(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $search = $request->query('search', '');
        $category = $request->query('category', '');
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 20);

        $query = Product::select('id', 'name', 'category', 'pictures', 'selling_price', 'cost_price', 'quantity', 'barcode', 'has_tags', 'is_active')
            ->where('user_id', $ownerId)
            ->where('is_active', true);

        if ($search) {
            $searchTerms = explode(' ', $search);
            foreach ($searchTerms as $term) {
                $term = trim($term);
                if ($term) {
                    $query->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('category', 'like', "%{$term}%");
                    });
                }
            }
        }

        // Filter by specific category if provided
        if ($category) {
            if ($category === 'Uncategorized') {
                $query->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '');
                });
            } else {
                $query->where('category', $category);
            }
        }

        // Order by category first (null categories at end), then by quantity status, then by name
        // Products with quantity > 0 first, then by name
        // Get products
        $productsQuery = $query->orderByRaw('CASE WHEN category IS NULL OR category = "" THEN 1 ELSE 0 END')
            ->orderBy('category')
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->orderBy('name');

        if ($perPage > 10000) {
            // Load all products without pagination
            $products = $productsQuery->get();
            $products->load('barcodes');

            // Add barcodes to each product
            $products->transform(function ($product) {
                $additionalBarcodes = $product->barcodes->pluck('barcode')->toArray();
                $mainBarcode = $product->barcode ? [$product->barcode] : [];
                $allBarcodes = array_merge($mainBarcode, $additionalBarcodes);
                $product->unsetRelation('barcodes');
                $product->barcodes = array_map('trim', array_filter($allBarcodes)); // trim and remove nulls/empties
                return $product;
            });

            // Return in paginated format for compatibility
            return response()->json([
                'data' => $products,
                'current_page' => 1,
                'per_page' => $products->count(),
                'total' => $products->count(),
                'last_page' => 1,
                'from' => 1,
                'to' => $products->count()
            ]);
        } else {
            $products = $productsQuery->paginate($perPage);
            $products->getCollection()->load('barcodes');

            // Add barcodes to each product in the collection
            $products->getCollection()->transform(function ($product) {
                $additionalBarcodes = $product->barcodes->pluck('barcode')->toArray();
                $mainBarcode = $product->barcode ? [$product->barcode] : [];
                $allBarcodes = array_merge($mainBarcode, $additionalBarcodes);
                $product->unsetRelation('barcodes');
                $product->barcodes = array_map('trim', array_filter($allBarcodes)); // trim and remove nulls/empties
                return $product;
            });

            return response()->json($products);
        }
    }


    // Keep the old method for backward compatibility but enhance it
    public function searchWithoutBarcode(Request $request)
    {
        return $this->searchAllProducts($request);
    }
    // Enhanced barcode search that returns multiple products if duplicates exist
    public function search(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $barcode = $request->input('barcode');
        $productId = $request->input('productid');

        if ($barcode) {
            // Collect all products that match this barcode (main or additional)
            $mainBarcodeProducts = Product::where('barcode', $barcode)
                ->where('user_id', $ownerId)
                ->where('is_active', true)
                ->get();

            $additionalBarcodeProducts = \DB::table('product_barcodes')
                ->join('products', 'product_barcodes.product_id', '=', 'products.id')
                ->where('products.user_id', $ownerId)
                ->where('products.is_active', true)
                ->where('product_barcodes.barcode', $barcode)
                ->select('products.*')
                ->get();

            // Combine all matching products, avoiding duplicates
            $allProducts = collect();
            $productIds = [];

            // Add main barcode products
            foreach ($mainBarcodeProducts as $product) {
                if (!in_array($product->id, $productIds)) {
                    $allProducts->push($product);
                    $productIds[] = $product->id;
                }
            }

            // Add additional barcode products
            foreach ($additionalBarcodeProducts as $product) {
                if (!in_array($product->id, $productIds)) {
                    $allProducts->push($product);
                    $productIds[] = $product->id;
                }
            }

            if ($allProducts->count() === 1) {
                // Single product found - return it directly
                return response()->json($allProducts->first());
            } elseif ($allProducts->count() > 1) {
                // Multiple products found - return array with special flag
                return response()->json([
                    'multiple_products' => true,
                    'products' => $allProducts,
                    'barcode' => $barcode
                ]);
            } else {
                // No products found at all
                return response()->json(null);
            }
        } elseif ($productId) {
            $product = Product::where('id', $productId)
                ->where('user_id', $ownerId)
                ->where('is_active', true)
                ->first();
            return response()->json($product);
        }

        return response()->json(null);
    }

    // Search barcode in purchase bills to find suppliers
    public function searchBarcode(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $barcode = trim($request->input('barcode'));

        if (!$barcode) {
            return view('products.barcode-search', ['results' => null, 'searched' => false]);
        }

        // Search in purchase_bill_product table for barcodes containing this barcode
        $barcodeResults = \DB::table('purchase_bill_product')
            ->join('purchase_bills', 'purchase_bill_product.purchase_bill_id', '=', 'purchase_bills.id')
            ->join('suppliers', 'purchase_bills.supplier_id', '=', 'suppliers.id')
            ->join('products', 'purchase_bill_product.product_id', '=', 'products.id')
            ->where('purchase_bills.user_id', $ownerId)
            ->whereJsonContains('purchase_bill_product.barcodes', $barcode)
            ->select(
                'products.name as product_name',
                'products.id as product_id',
                'suppliers.name as supplier_name',
                'suppliers.id as supplier_id',
                'purchase_bills.purchase_date',
                'purchase_bills.reference_number',
                'purchase_bill_product.quantity',
                'purchase_bill_product.unit_cost',
                'purchase_bill_product.barcodes'
            )
            ->orderBy('purchase_bills.purchase_date', 'desc')
            ->get()
            ->map(function ($result) {
                // Convert purchase_date string to Carbon object
                $result->purchase_date = \Carbon\Carbon::parse($result->purchase_date);
                return $result;
            });

        $productSuppliers = collect();

        // If no barcode results, find product by barcode and get all suppliers who purchased it
        if ($barcodeResults->isEmpty()) {
            // Find product by barcode
            $product = Product::where('user_id', $ownerId)
                ->where(function ($q) use ($barcode) {
                    $q->where('barcode', $barcode)
                        ->orWhereHas('barcodes', function ($qb) use ($barcode) {
                            $qb->where('barcode', $barcode);
                        });
                })
                ->first();

            if ($product) {
                // Get all suppliers who have purchased this product
                $productSuppliers = \DB::table('purchase_bill_product')
                    ->join('purchase_bills', 'purchase_bill_product.purchase_bill_id', '=', 'purchase_bills.id')
                    ->join('suppliers', 'purchase_bills.supplier_id', '=', 'suppliers.id')
                    ->where('purchase_bills.user_id', $ownerId)
                    ->where('purchase_bill_product.product_id', $product->id)
                    ->select(
                        'products.name as product_name',
                        'products.id as product_id',
                        'suppliers.name as supplier_name',
                        'suppliers.id as supplier_id',
                        'purchase_bills.purchase_date',
                        'purchase_bills.reference_number',
                        'purchase_bill_product.quantity',
                        'purchase_bill_product.unit_cost',
                        'purchase_bill_product.barcodes'
                    )
                    ->orderBy('purchase_bills.purchase_date', 'desc')
                    ->get()
                    ->map(function ($result) {
                        // Convert purchase_date string to Carbon object
                        $result->purchase_date = \Carbon\Carbon::parse($result->purchase_date);
                        return $result;
                    });
            }
        }

        return view('products.barcode-search', [
            'barcodeResults' => $barcodeResults,
            'productSuppliers' => $productSuppliers,
            'searched' => true,
            'barcode' => $barcode
        ]);
    }

    // Get suppliers for a specific product
    public function getProductSuppliers(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $productId = $request->input('product_id');

        if (!$productId) {
            return response()->json(['error' => 'Product ID required'], 400);
        }

        $suppliers = \DB::table('purchase_bill_product')
            ->join('purchase_bills', 'purchase_bill_product.purchase_bill_id', '=', 'purchase_bills.id')
            ->join('suppliers', 'purchase_bills.supplier_id', '=', 'suppliers.id')
            ->where('purchase_bills.user_id', $ownerId)
            ->where('purchase_bill_product.product_id', $productId)
            ->select(
                'suppliers.name as supplier_name',
                'suppliers.id as supplier_id',
                'purchase_bills.purchase_date',
                'purchase_bills.reference_number',
                'purchase_bill_product.quantity',
                'purchase_bill_product.unit_cost'
            )
            ->orderBy('purchase_bills.purchase_date', 'desc')
            ->get()
            ->map(function ($result) {
                $result->purchase_date = \Carbon\Carbon::parse($result->purchase_date);
                return $result;
            });

        return response()->json($suppliers);
    }
    // Export products to CSV
    public function export()
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $products = Product::where('user_id', $ownerId)
            ->orderBy('name')
            ->get();

        $filename = 'products_export_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'ID',
                'Name',
                'Barcode',
                'Quantity',
                'Cost Price',
                'Selling Price',
                'Profit Margin (%)',
                'Profit per Unit',
                'Total Inventory Value',
                'Total Potential Revenue',
                'Created Date',
                'Last Updated'
            ]);

            // Add product data
            foreach ($products as $product) {
                $profitMargin = $product->selling_price > 0 ?
                    (($product->selling_price - $product->cost_price) / $product->selling_price) * 100 : 0;

                $profitPerUnit = $product->selling_price - $product->cost_price;
                $totalInventoryValue = $product->quantity * $product->cost_price;
                $totalPotentialRevenue = $product->quantity * $product->selling_price;

                fputcsv($file, [
                    $product->id,
                    $product->name,
                    $product->barcode ?? '',
                    $product->quantity,
                    number_format($product->cost_price, 2),
                    number_format($product->selling_price, 2),
                    number_format($profitMargin, 2),
                    number_format($profitPerUnit, 2),
                    number_format($totalInventoryValue, 2),
                    number_format($totalPotentialRevenue, 2),
                    $product->created_at->format('Y-m-d H:i:s'),
                    $product->updated_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Helper to check ownership
    private function authorizeProduct(Product $product)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        if ($product->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Display out-of-stock products page with deactivation warnings
     */
    public function outOfStock(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        // Get the owner user for settings (shop owner has the settings)
        $owner = $user->role === 'employee' ? \App\Models\User::find($user->shop_owner_id) : $user;

        // Get configurable periods (use owner's settings or defaults)
        $warningMonths = $request->get('warning_months', $owner->product_warning_period ?? 4);
        $deactivationMonths = $request->get('deactivation_months', $owner->product_deactivation_period ?? 6);

        // Calculate cutoff dates
        $warningCutoff = now()->subMonths($warningMonths);
        $deactivationCutoff = now()->subMonths($deactivationMonths);

        // Get out-of-stock products (quantity = 0) that haven't been sold recently
        $query = Product::where('user_id', $ownerId)
            ->where('quantity', 0)
            ->where('is_active', true)
            ->whereNotNull('last_sale_date')
            ->where('last_sale_date', '<=', $warningCutoff);

        // Filter by deactivation status
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'warning':
                    $query->where('last_sale_date', '<=', $warningCutoff)
                        ->where('last_sale_date', '>', $deactivationCutoff);
                    break;
                case 'deactivation':
                    $query->where('last_sale_date', '<=', $deactivationCutoff);
                    break;
            }
        }

        // Handle bulk actions
        if ($request->has('action') && $request->has('product_ids')) {
            \Log::info('Bulk action request', [
                'action' => $request->action,
                'product_ids' => $request->product_ids,
                'user_id' => $ownerId
            ]);

            switch ($request->action) {
                case 'extend':
                    $extendMonths = (int) $request->get('extend_months', $deactivationMonths);
                    $extendUntil = now()->addMonths($extendMonths);
                    $updated = Product::whereIn('id', $request->product_ids)
                        ->where('user_id', $ownerId)
                        ->update(['extended_until' => $extendUntil]);

                    \Log::info('Extend action completed', ['updated_count' => $updated]);
                    return redirect()->back()->with('success', 'Selected products extended successfully.');
                    break;
                case 'deactivate':
                    $deactivatedCount = $this->deactivateProducts($request->product_ids, $ownerId);
                    return redirect()->back()->with('success', $deactivatedCount . ' products deactivated successfully.');
                    break;
            }
        } else {
            if ($request->has('action')) {
                return redirect()->back()->with('error', 'No products selected for the action.');
            }
        }

        $products = $query->orderBy('last_sale_date', 'asc')->paginate(20);

        // Add status information to each product
        foreach ($products as $product) {
            $product->days_since_sale = now()->diffInDays($product->last_sale_date);
            $product->months_since_sale = now()->diffInMonths($product->last_sale_date);

            if ($product->extended_until && $product->extended_until->isFuture()) {
                $product->status = 'extended';
                $product->status_color = 'blue';
            } elseif ($product->last_sale_date <= $deactivationCutoff) {
                $product->status = 'deactivation';
                $product->status_color = 'red';
            } else {
                $product->status = 'warning';
                $product->status_color = 'yellow';
            }
        }

        return view('products.out-of-stock', compact('products', 'warningMonths', 'deactivationMonths'));
    }

    /**
     * Get the next auto-increment product ID
     */
    public function getNextProductId()
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $maxId = \DB::table('products')->max('id') ?? 0;
        $nextId = $maxId + 1;

        return response()->json(['next_id' => $nextId]);
    }

    /**
     * Delete all images associated with a product
     */
    private function deleteProductImages(Product $product)
    {
        if ($product->pictures) {
            try {
                $pictures = json_decode($product->pictures, true);

                if (is_array($pictures)) {
                    foreach ($pictures as $picture) {
                        // Delete from public disk
                        if (Storage::disk('public')->exists($picture)) {
                            Storage::disk('public')->delete($picture);
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Invalid pictures json for product', [
                    'id' => $product->id,
                    'pictures' => $product->pictures,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Deactivate multiple products (set is_active = false)
     */
    private function deactivateProducts($productIds, $ownerId)
    {
        $count = 0;
        foreach ($productIds as $productId) {
            $product = Product::where('id', $productId)
                ->where('user_id', $ownerId)
                ->where('is_active', true)
                ->first();

            if ($product) {
                $product->is_active = false;
                // Delete product images when deactivating to free storage
                $this->deleteProductImages($product);
                // Clear the pictures field to remove references to deleted images
                $product->pictures = null;
                $product->save();
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count total images for a user
     */
    private function countTotalImages($ownerId)
    {
        $totalImages = 0;
        $products = Product::where('user_id', $ownerId)->whereNotNull('pictures')->get();

        foreach ($products as $product) {
            $pictures = json_decode($product->pictures, true);
            if (is_array($pictures)) {
                $totalImages += count($pictures);
            }
        }

        return $totalImages;
    }
}
