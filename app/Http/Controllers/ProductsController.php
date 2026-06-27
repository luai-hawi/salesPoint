<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImei;
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
            // Split search into words and use AND logic - all words must match
            $searchTerms = array_filter(explode(' ', $search));
            foreach ($searchTerms as $term) {
                $term = trim($term);
                if ($term) {
                    $query->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$term}%"])
                            ->orWhere('cost_price', 'like', "%{$term}%")
                            ->orWhere('selling_price', 'like', "%{$term}%")
                            ->orWhereRaw('LOWER(category) LIKE ?', ["%{$term}%"])
                            ->orWhereHas('barcodes', function ($qb) use ($term) {
                                $qb->whereRaw('LOWER(barcode) LIKE ?', ["%{$term}%"]);
                            })
                            ->orWhereHas('imeis', function ($qb) use ($term) {
                                $qb->whereRaw('LOWER(imei) LIKE ?', ["%{$term}%"]);
                            });
                    });
                }
            }
        }

        if ($request->query('low_stock')) {
            $query->whereRaw('quantity > 0 AND quantity <= low_stock_threshold');
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

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $suppliers = \App\Models\Supplier::where('user_id', $ownerId)->orderBy('name')->get();

        return view('products.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        // Check if creating variants
        $hasVariants = $request->has('has_variants') && $request->has_variants == '1';

        if ($hasVariants) {
            // Validate variant data
            $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
                'pictures' => 'nullable|array',
                'pictures.*' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'cost_price' => 'required|numeric',
                'selling_price' => 'required|numeric',
                'has_tags' => 'boolean',
                'low_stock_threshold' => 'nullable|numeric|min:1',
                'variants' => 'required|array|min:1',
                'variants.*.name' => 'required|string|max:255',
                'variants.*.quantity' => 'required|numeric|min:0',
                'variants.*.barcode' => 'nullable|string|max:255',
            ]);

            // Create variant group
            $variantGroup = \App\Models\ProductVariantGroup::create([
                'name' => $request->name,
                'user_id' => $ownerId,
            ]);

            // Handle pictures once for all variants
            $pictures = null;
            if ($request->hasFile('pictures')) {
                $owner = \App\Models\User::find($ownerId);
                $currentImages = $this->countTotalImages($ownerId);
                $newImagesCount = count($request->file('pictures'));
                if ($currentImages + $newImagesCount > $owner->image_limit) {
                    return back()->withErrors(['pictures' => 'Image limit exceeded. You can upload a maximum of ' . $owner->image_limit . ' images total.'])->withInput();
                }

                $picturesArray = [];
                foreach ($request->file('pictures') as $picture) {
                    $picturesArray[] = $picture->store('products', 'public');
                }
                $pictures = json_encode($picturesArray);
            }

            // Create each variant as a separate product
            foreach ($request->variants as $variantData) {
                $product = new Product();
                $product->name = $request->name . ' - ' . $variantData['name'];
                $product->category = $request->category;
                $product->barcode = isset($variantData['barcode']) ? trim($variantData['barcode']) : null;
                $product->quantity = (float) $variantData['quantity'];
                $product->low_stock_threshold = $request->low_stock_threshold ?? 10;
                $product->cost_price = round($request->cost_price, 2);
                $product->selling_price = $request->selling_price;
                $product->user_id = $ownerId;
                $product->has_tags = $request->has('has_tags');
                $product->variant_group_id = $variantGroup->id;
                $product->variant_name = $variantData['name'];
                $product->pictures = $pictures;

                $product->save();

                // Create batch if quantity > 0
                if ($product->quantity > 0) {
                    Batch::create([
                        'product_id' => $product->id,
                        'quantity' => $product->quantity,
                        'cost_price' => (float) $request->cost_price,
                        'user_id' => $ownerId,
                    ]);
                }
            }

            return redirect()->route('products.create')->with('success', count($request->variants) . ' variant products created successfully.');
        } else {
            // Original single product creation logic
            $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
                'barcode' => 'nullable|string|max:255',
                'additional_barcodes' => 'nullable|array',
                'additional_barcodes.*' => 'nullable|string|max:255',
                'pictures' => 'nullable|array',
                'pictures.*' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'quantity' => 'nullable|numeric|min:0',
                'low_stock_threshold' => 'nullable|numeric|min:1',
                'cost_price' => 'required|numeric',
                'selling_price' => 'required|numeric',
                'has_tags' => 'boolean',
                'has_imeis' => 'boolean',
                'new_imeis' => 'nullable|array',
                'new_imeis.*' => 'nullable|string|max:255',
                'new_imeis_supplier' => 'nullable|integer|exists:suppliers,id',
                'new_imeis_date' => 'nullable|date',
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
            $product->category = $request->category;
            $product->barcode = trim($request->barcode);
            $product->quantity = (float) $request->quantity;
            $product->low_stock_threshold = $request->low_stock_threshold ?? 10;
            $product->cost_price = round($request->cost_price, 2);
            $product->selling_price = $request->selling_price;
            $product->user_id = $ownerId;
            $product->has_tags = $request->has('has_tags');
            $product->has_imeis = $request->boolean('has_imeis', false);

            if ($request->hasFile('pictures')) {
                $pictures = [];
                foreach ($request->file('pictures') as $picture) {
                    $pictures[] = $picture->store('products', 'public');
                }
                $product->pictures = json_encode($pictures);
            }

            $product->save();

            if ($product->quantity > 0) {
                Batch::create([
                    'product_id' => $product->id,
                    'quantity' => $product->quantity,
                    'cost_price' => (float) $request->cost_price,
                    'user_id' => $ownerId,
                ]);
            }

            // Save additional barcodes
            if ($request->has('additional_barcodes')) {
                $barcodes = array_filter($request->additional_barcodes);
                foreach ($barcodes as $barcode) {
                    if (!empty(trim($barcode))) {
                        ProductBarcode::create([
                            'product_id' => $product->id,
                            'barcode' => trim($barcode),
                        ]);
                    }
                }
            }

            // Save initial IMEIs if provided
            if ($product->has_imeis && $request->has('new_imeis')) {
                $supplierId  = $request->input('new_imeis_supplier') ?: null;
                $purchasedAt = $request->input('new_imeis_date') ?: null;
                foreach (array_filter((array) $request->new_imeis) as $imei) {
                    $imei = trim($imei);
                    if ($imei === '') continue;
                    \App\Models\ProductImei::create([
                        'user_id'       => $ownerId,
                        'product_id'    => $product->id,
                        'imei'          => $imei,
                        'supplier_id'   => $supplierId,
                        'purchased_at'  => $purchasedAt,
                    ]);
                }
            }

            return redirect()->route('products.create')->with('success', 'Product created successfully.');
        }
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
            'has_imeis' => 'boolean',
            'low_stock_threshold' => 'nullable|numeric|min:1',
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
        $product->low_stock_threshold = $request->low_stock_threshold ?? 10;
        $product->cost_price = round($request->cost_price, 2);
        $product->selling_price = $request->selling_price;
        $product->has_tags = $request->has('has_tags');
        $product->has_imeis = $request->boolean('has_imeis', false);

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

        $request->validate(['amount' => 'required|numeric|min:0.01']);
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

    public function checkBarcodes(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $ignoreProductId = $request->input('ignore_product_id');

        $mainBarcode = trim((string) $request->input('barcode', ''));
        $additionalBarcodes = $request->input('additional_barcodes', []);
        if (!is_array($additionalBarcodes)) {
            $additionalBarcodes = [];
        }

        $barcodes = array_merge([$mainBarcode], $additionalBarcodes);
        $barcodes = array_values(array_unique(array_filter(array_map('trim', $barcodes))));

        if (empty($barcodes)) {
            return response()->json(['duplicates' => []]);
        }

        $duplicates = [];

        $mainMatches = Product::query()
            ->where('user_id', $ownerId)
            ->when($ignoreProductId, function ($query) use ($ignoreProductId) {
                $query->where('id', '!=', $ignoreProductId);
            })
            ->whereNotNull('barcode')
            ->whereIn('barcode', $barcodes)
            ->get(['id', 'name', 'barcode']);

        foreach ($mainMatches as $product) {
            $code = trim((string) $product->barcode);
            if (!$code) {
                continue;
            }
            if (!isset($duplicates[$code])) {
                $duplicates[$code] = [
                    'barcode' => $code,
                    'products' => [],
                ];
            }
            $duplicates[$code]['products'][$product->id] = [
                'id' => $product->id,
                'name' => $product->name,
                'source' => 'main',
            ];
        }

        $additionalMatches = \DB::table('product_barcodes')
            ->join('products', 'product_barcodes.product_id', '=', 'products.id')
            ->where('products.user_id', $ownerId)
            ->when($ignoreProductId, function ($query) use ($ignoreProductId) {
                $query->where('products.id', '!=', $ignoreProductId);
            })
            ->whereIn('product_barcodes.barcode', $barcodes)
            ->select('product_barcodes.barcode', 'products.id', 'products.name')
            ->get();

        foreach ($additionalMatches as $match) {
            $code = trim((string) $match->barcode);
            if (!$code) {
                continue;
            }
            if (!isset($duplicates[$code])) {
                $duplicates[$code] = [
                    'barcode' => $code,
                    'products' => [],
                ];
            }
            $duplicates[$code]['products'][$match->id] = [
                'id' => $match->id,
                'name' => $match->name,
                'source' => 'additional',
            ];
        }

        $duplicates = array_values(array_map(function ($entry) {
            $entry['products'] = array_values($entry['products']);
            return $entry;
        }, $duplicates));

        return response()->json(['duplicates' => $duplicates]);
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

        $query = Product::select('id', 'name', 'category', 'pictures', 'selling_price', 'cost_price', 'quantity', 'barcode', 'has_tags', 'has_imeis', 'is_active')
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

            // Also search by IMEI code
            $imeiProduct = ProductImei::where('user_id', $ownerId)
                ->where('imei', $barcode)
                ->with('product')
                ->first();

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

            // Add IMEI product if found and active
            if ($imeiProduct && $imeiProduct->product && $imeiProduct->product->is_active) {
                if (!in_array($imeiProduct->product_id, $productIds)) {
                    // Reload as Product model with proper fields
                    $imeiProductFull = Product::where('id', $imeiProduct->product_id)
                        ->where('user_id', $ownerId)
                        ->where('is_active', true)
                        ->first();
                    if ($imeiProductFull) {
                        $allProducts->push($imeiProductFull);
                        $productIds[] = $imeiProductFull->id;
                    }
                }
                // Attach the matched IMEI info for POS use
                $allProducts->each(function ($p) use ($imeiProduct) {
                    if ($p->id === $imeiProduct->product_id) {
                        $p->matched_imei = $imeiProduct->imei;
                    }
                });
            }

            if ($allProducts->count() === 1) {
                return response()->json($allProducts->first());
            } elseif ($allProducts->count() > 1) {
                return response()->json([
                    'multiple_products' => true,
                    'products' => $allProducts,
                    'barcode' => $barcode
                ]);
            } else {
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

        // First, check if this is an IMEI code
        $imeiResult = ProductImei::where('user_id', $ownerId)
            ->where('imei', $barcode)
            ->with(['product', 'supplier', 'purchaseBill', 'saleBill.customer', 'saleBill.creator'])
            ->first();

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
                $result->purchase_date = \Carbon\Carbon::parse($result->purchase_date);
                return $result;
            });

        $productSuppliers = collect();

        // If no barcode results, find product by barcode and get all suppliers who purchased it
        if ($barcodeResults->isEmpty() && !$imeiResult) {
            $product = Product::where('user_id', $ownerId)
                ->where(function ($q) use ($barcode) {
                    $q->where('barcode', $barcode)
                        ->orWhereHas('barcodes', function ($qb) use ($barcode) {
                            $qb->where('barcode', $barcode);
                        });
                })
                ->first();

            if ($product) {
                $productSuppliers = \DB::table('purchase_bill_product')
                    ->join('purchase_bills', 'purchase_bill_product.purchase_bill_id', '=', 'purchase_bills.id')
                    ->join('suppliers', 'purchase_bills.supplier_id', '=', 'suppliers.id')
                    ->join('products', 'purchase_bill_product.product_id', '=', 'products.id')
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
                        $result->purchase_date = \Carbon\Carbon::parse($result->purchase_date);
                        return $result;
                    });
            }
        }

        return view('products.barcode-search', [
            'barcodeResults' => $barcodeResults,
            'productSuppliers' => $productSuppliers,
            'imeiResult' => $imeiResult,
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
            ->join('products', 'purchase_bill_product.product_id', '=', 'products.id')
            ->where('purchase_bills.user_id', $ownerId)
            ->where('purchase_bill_product.product_id', $productId)
            ->select(
                'products.name as product_name',
                'products.id as product_id',
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
     * Add more variants to an existing variant group
     */
    public function addVariants(Request $request, Product $product)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_products')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizeProduct($product);

        // Check if product is part of a variant group
        if (!$product->variant_group_id) {
            return back()->withErrors(['error' => 'This product is not part of a variant group.']);
        }

        // Validate new variants
        $request->validate([
            'new_variants' => 'required|array|min:1',
            'new_variants.*.name' => 'required|string|max:255',
            'new_variants.*.quantity' => 'required|numeric|min:0',
            'new_variants.*.barcode' => 'nullable|string|max:255',
        ]);

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $variantGroup = $product->variantGroup;
        $createdCount = 0;

        // Create each new variant
        foreach ($request->new_variants as $variantData) {
            $newProduct = new Product();
            $newProduct->name = $variantGroup->name . ' - ' . $variantData['name'];
            $newProduct->category = $product->category;
            $newProduct->barcode = isset($variantData['barcode']) ? trim($variantData['barcode']) : null;
            $newProduct->quantity = (float) $variantData['quantity'];
            $newProduct->cost_price = $product->cost_price;
            $newProduct->selling_price = $product->selling_price;
            $newProduct->user_id = $ownerId;
            $newProduct->has_tags = $product->has_tags;
            $newProduct->variant_group_id = $product->variant_group_id;
            $newProduct->variant_name = $variantData['name'];
            $newProduct->pictures = $product->pictures; // Share images

            $newProduct->save();

            // Create batch if quantity > 0
            if ($newProduct->quantity > 0) {
                Batch::create([
                    'product_id' => $newProduct->id,
                    'quantity' => $newProduct->quantity,
                    'cost_price' => (float) $product->cost_price,
                    'user_id' => $ownerId,
                ]);
            }

            $createdCount++;
        }

        return redirect()->route('products.edit', $product->id)
            ->with('success', $createdCount . ' new variant(s) added successfully.');
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
