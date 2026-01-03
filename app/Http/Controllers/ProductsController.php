<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Batch;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;


class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $query = Product::where('user_id', $ownerId);

        if ($search = $request->query('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$search}%"])
                    ->orWhere('cost_price', 'like', "%{$search}%")
                    ->orWhere('selling_price', 'like', "%{$search}%");
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
        return view('products.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255', // Add category validation
            'barcode' => 'nullable|string|max:255',
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
        $product->barcode = $request->barcode;
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

        // create initial batch for this product
        $batch = new Batch();
        $batch->product_id = $product->id;
        $batch->quantity = $request->quantity;
        $batch->cost_price = $request->cost_price;
        $batch->user_id = $ownerId;
        $batch->save();

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }


    public function edit(Product $product)
    {
        $this->authorizeProduct($product);
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $this->authorizeProduct($product);

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255', // Add category validation
            'barcode' => 'nullable|string|max:255',
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
        $product->barcode = $request->barcode;
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
        $this->authorizeProduct($product);

        // Delete associated images before deleting the product
        $this->deleteProductImages($product);

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    // Enhanced search for all products with quantity ordering
    public function searchAllProducts(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $search = $request->query('search', '');
        $category = $request->query('category', '');
        $page = $request->query('page', 1);

        $query = Product::select('id', 'name', 'category', 'pictures', 'selling_price', 'cost_price', 'quantity', 'barcode', 'has_tags', 'is_active')
            ->where('user_id', $ownerId)
            ->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
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
        $products = $query->orderByRaw('CASE WHEN category IS NULL OR category = "" THEN 1 ELSE 0 END')
            ->orderBy('category')
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(20);

        return response()->json($products);
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
            // Find all products with this barcode
            $products = Product::where('barcode', $barcode)
                ->where('user_id', $ownerId)
                ->where('is_active', true)
                ->get();

            if ($products->count() === 1) {
                // Single product found - return it directly
                return response()->json($products->first());
            } elseif ($products->count() > 1) {
                // Multiple products found - return array with special flag
                return response()->json([
                    'multiple_products' => true,
                    'products' => $products,
                    'barcode' => $barcode
                ]);
            } else {
                // No products found
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
