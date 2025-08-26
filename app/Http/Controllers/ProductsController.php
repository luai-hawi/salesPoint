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
    $page = $request->query('page', 1);

    $query = Product::select('id', 'name', 'category', 'pictures', 'selling_price', 'cost_price', 'quantity', 'barcode', 'has_tags')
        ->where('user_id', $ownerId);

    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%")
              ->orWhere('category', 'like', "%{$search}%");
        });
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

        $callback = function() use ($products) {
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
     * Delete all images associated with a product
     */
    private function deleteProductImages(Product $product)
    {
        if ($product->pictures) {
            $pictures = json_decode($product->pictures, true);
            
            if (is_array($pictures)) {
                foreach ($pictures as $picture) {
                    // Delete from public disk
                    if (Storage::disk('public')->exists($picture)) {
                        Storage::disk('public')->delete($picture);
                    }
                }
            }
        }
    }

    
}