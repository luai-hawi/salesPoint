<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImei;
use App\Models\Batch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ImageProcessor;
use App\Services\StockIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;


class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products']);

        $ownerId = $this->ownerId($user);
        $query = Product::where('user_id', $ownerId);

        if ($search = trim((string) $request->query('search', ''))) {
            $search = strtolower($search);
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

        if ($category = trim((string) $request->query('category', ''))) {
            $query->where('category', $category);
        }

        if ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        } elseif ($request->query('status') === 'active') {
            $query->where('is_active', true);
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw('quantity > 0 AND quantity <= low_stock_threshold');
        }

        $products = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->appends($request->query());

        $categories = Product::where('user_id', $ownerId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $viewMode = in_array($request->query('view'), ['table', 'cards'], true) ? $request->query('view') : 'table';

        return view('products.index', compact('products', 'categories', 'viewMode'));
    }

    public function create()
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['create_products']);

        $ownerId = $this->ownerId($user);
        $suppliers = Supplier::where('user_id', $ownerId)->orderBy('name')->get(['id', 'name']);
        $categories = Product::where('user_id', $ownerId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $owner = User::findOrFail($ownerId);
        $usedImageCount = $this->countTotalImages($ownerId);
        $remainingImageSlots = max(0, (int) ($owner->image_limit ?? 0) - $usedImageCount);

        return view('products.create', compact('suppliers', 'categories', 'owner', 'usedImageCount', 'remainingImageSlots'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['create_products']);

        $ownerId = $this->ownerId($user);
        $hasVariants = $request->boolean('has_variants');
        $this->validatePictureUploadEnvelope($request);
        $request->validate(array_merge(
            $this->productRules($ownerId, $hasVariants),
            $this->fundingRules($ownerId),
        ));
        $clientUuid = $this->normalizeIntakeClientUuid($request->input('intake_client_uuid'));
        $intakeTokens = $hasVariants
            ? $this->variantIntakeTokens($clientUuid, count((array) $request->input('variants', [])))
            : ($clientUuid ? [$clientUuid] : []);

        if ($existingProduct = $this->findExistingProductForIntakeTokens($ownerId, $intakeTokens)) {
            return redirect()->route('products.edit', $existingProduct)
                ->with('success', __('products_ui.flash.intake_already_processed'));
        }

        $normalizedBarcodes = $this->normalizeAdditionalBarcodes(
            $request->input('barcode'),
            $request->input('additional_barcodes', []),
        );
        $forceDuplicate = $request->boolean('force_duplicate_barcode');
        $this->assertTenantBarcodesAvailable($ownerId, array_merge([
            $request->input('barcode'),
        ], $normalizedBarcodes), null, $forceDuplicate);
        if ($hasVariants) {
            $variantBarcodes = array_map(
                fn ($variant) => trim((string) data_get($variant, 'barcode')),
                (array) $request->input('variants', []),
            );

            if (count(array_filter($variantBarcodes)) !== count(array_unique(array_filter($variantBarcodes)))) {
                throw ValidationException::withMessages([
                    'variants' => [__('products_ui.validation.duplicate_barcodes')],
                ]);
            }

            $this->assertTenantBarcodesAvailable($ownerId, $variantBarcodes, null, $forceDuplicate);
        }

        $newImageCount = count($request->file('pictures', []));
        $this->ensureImageLimit($ownerId, $newImageCount);

        $createdPaths = [];

        try {
            $pictures = $this->processUploadedPictures($request, app(ImageProcessor::class), $createdPaths);
            $funding = $this->resolveFunding($request);
            $duplicateProductId = null;

            DB::transaction(function () use ($request, $user, $ownerId, $hasVariants, $pictures, $normalizedBarcodes, $funding, $clientUuid, $intakeTokens, &$duplicateProductId) {
                if ($existingProduct = $this->findExistingProductForIntakeTokens($ownerId, $intakeTokens, true)) {
                    $duplicateProductId = $existingProduct->id;

                    return;
                }

                if ($hasVariants) {
                    $variantGroup = \App\Models\ProductVariantGroup::create([
                        'name' => trim((string) $request->input('name')),
                        'user_id' => $ownerId,
                    ]);

                    $lines = [];
                    $createdBatches = [];
                    $variantTokens = $this->variantIntakeTokens($clientUuid, count((array) $request->input('variants', [])));

                    foreach ($request->input('variants', []) as $index => $variantData) {
                        $quantity = round((float) ($variantData['quantity'] ?? 0), 2);

                        $product = new Product();
                        $product->name = trim((string) $request->input('name')) . ' - ' . trim((string) $variantData['name']);
                        $product->category = $this->emptyToNull($request->input('category'));
                        $product->barcode = $this->emptyToNull($variantData['barcode'] ?? null);
                        $product->quantity = $quantity;
                        $product->low_stock_threshold = $this->normalizeLowStockThreshold($request->input('low_stock_threshold'));
                        $product->cost_price = round((float) $request->input('cost_price'), 2);
                        $product->selling_price = round((float) $request->input('selling_price'), 2);
                        $product->user_id = $ownerId;
                        $product->has_tags = $request->boolean('has_tags');
                        $product->variant_group_id = $variantGroup->id;
                        $product->variant_name = trim((string) $variantData['name']);
                        $product->pictures = $pictures ? json_encode($pictures, JSON_UNESCAPED_SLASHES) : null;
                        $product->save();

                        if ($quantity > 0) {
                            $createdBatches[] = Batch::create([
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'cost_price' => round((float) $request->input('cost_price'), 2),
                                'user_id' => $ownerId,
                                'intake_client_uuid' => $variantTokens[$index] ?? null,
                            ]);

                            $lines[] = [
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'unit_cost' => round((float) $request->input('cost_price'), 2),
                            ];
                        }
                    }

                    $this->validateFundingAgainstLines($funding, $lines);
                    if ($lines) {
                        $bill = app(StockIntake::class)->record($user, $lines, $funding);
                        $this->attachPurchaseBillToBatches($createdBatches, $bill);
                    }

                    return;
                }

                $product = new Product();
                $product->name = trim((string) $request->input('name'));
                $product->category = $this->emptyToNull($request->input('category'));
                $product->barcode = $this->emptyToNull($request->input('barcode'));
                $product->quantity = round((float) $request->input('quantity', 0), 2);
                $product->low_stock_threshold = $this->normalizeLowStockThreshold($request->input('low_stock_threshold'));
                $product->cost_price = round((float) $request->input('cost_price'), 2);
                $product->selling_price = round((float) $request->input('selling_price'), 2);
                $product->user_id = $ownerId;
                $product->has_tags = $request->boolean('has_tags');
                $product->has_imeis = $request->boolean('has_imeis');
                $product->pictures = $pictures ? json_encode($pictures, JSON_UNESCAPED_SLASHES) : null;
                $product->save();

                $this->syncAdditionalBarcodes($product, $normalizedBarcodes);

                if ($product->quantity > 0) {
                    $batch = Batch::create([
                        'product_id' => $product->id,
                        'quantity' => $product->quantity,
                        'cost_price' => round((float) $request->input('cost_price'), 2),
                        'user_id' => $ownerId,
                        'intake_client_uuid' => $clientUuid,
                    ]);

                    $lines = [[
                        'product_id' => $product->id,
                        'quantity' => $product->quantity,
                        'unit_cost' => round((float) $request->input('cost_price'), 2),
                    ]];

                    $this->validateFundingAgainstLines($funding, $lines);
                    $bill = app(StockIntake::class)->record($user, $lines, $funding);
                    $this->attachPurchaseBillToBatches([$batch], $bill);
                }

                if ($product->has_imeis) {
                    $this->storeInitialImeis($product, $request, $ownerId);
                }
            });

            if ($duplicateProductId) {
                $this->cleanupStoredPaths($createdPaths);

                return redirect()->route('products.edit', $duplicateProductId)
                    ->with('success', __('products_ui.flash.intake_already_processed'));
            }
        } catch (\Throwable $e) {
            $this->cleanupStoredPaths($createdPaths);

            if ($e instanceof ValidationException) {
                throw $e;
            }

            if ($e instanceof \InvalidArgumentException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'funding_supplier_id' => [__($e->getMessage())],
                ]);
            }

            throw $e;
        }

        $message = $hasVariants
            ? __('products_ui.flash.variants_created', ['count' => count($request->input('variants', []))])
            : __('products_ui.flash.created');

        if ($request->input('save_action') === 'save_add_another') {
            return redirect()->route('products.create')->with('success', $message);
        }

        return redirect()->route('products.index')->with('success', $message);
    }


    public function edit(Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);

        $this->authorizeProduct($product);
        $ownerId = $this->ownerId($user);
        $suppliers = Supplier::where('user_id', $ownerId)->orderBy('name')->get(['id', 'name']);
        $categories = Product::where('user_id', $ownerId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $owner = User::findOrFail($ownerId);
        $usedImageCount = $this->countTotalImages($ownerId);
        $remainingImageSlots = max(0, (int) ($owner->image_limit ?? 0) - $usedImageCount);

        return view('products.edit', compact('product', 'suppliers', 'categories', 'owner', 'usedImageCount', 'remainingImageSlots'));
    }

    public function update(Request $request, Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);

        $ownerId = $this->ownerId($user);
        $this->authorizeProduct($product);

        $this->validatePictureUploadEnvelope($request);
        $request->validate($this->productRules($ownerId, false, true));
        $normalizedBarcodes = $this->normalizeAdditionalBarcodes(
            $request->input('barcode'),
            $request->input('additional_barcodes', []),
        );
        $forceDuplicate = $request->boolean('force_duplicate_barcode');
        $this->assertTenantBarcodesAvailable($ownerId, array_merge([
            $request->input('barcode'),
        ], $normalizedBarcodes), $product->id, $forceDuplicate);

        $currentPictures = $this->picturePathsFromProduct($product);
        $keptPictures = $request->has('existing_pictures')
            ? $this->validatedExistingPictures((array) $request->input('existing_pictures', []), $currentPictures)
            : $currentPictures;

        $removableCount = $request->has('existing_pictures')
            ? $this->countRemovableUniquePaths($ownerId, array_values(array_diff($currentPictures, $keptPictures)), $product->id)
            : $this->countRemovableUniquePaths($ownerId, $currentPictures, $product->id);

        $this->ensureImageLimit(
            $ownerId,
            count($request->file('pictures', [])),
            $removableCount,
        );

        $createdPaths = [];

        try {
            $newPictures = $this->processUploadedPictures($request, app(ImageProcessor::class), $createdPaths);
            $updatedPictures = $request->has('existing_pictures')
                ? $this->mergeOrderedPictures(
                    $keptPictures,
                    $newPictures,
                    (array) $request->input('picture_order', []),
                    (array) $request->input('new_picture_tokens', []),
                )
                : ($request->hasFile('pictures') ? $newPictures : $currentPictures);

            DB::transaction(function () use ($request, $product, $normalizedBarcodes, $updatedPictures) {
                $product->name = trim((string) $request->input('name'));
                $product->category = $this->emptyToNull($request->input('category'));
                $product->barcode = $this->emptyToNull($request->input('barcode'));
                $product->low_stock_threshold = $this->normalizeLowStockThreshold($request->input('low_stock_threshold'));
                $product->cost_price = round((float) $request->input('cost_price'), 2);
                $product->selling_price = round((float) $request->input('selling_price'), 2);
                $product->has_tags = $request->boolean('has_tags');
                $product->has_imeis = $request->boolean('has_imeis');
                $product->pictures = $updatedPictures ? json_encode($updatedPictures, JSON_UNESCAPED_SLASHES) : null;
                $product->save();

                $this->syncAdditionalBarcodes($product, $normalizedBarcodes);
            });

            $removedPaths = array_values(array_diff($currentPictures, $updatedPictures));
            $this->deletePathsIfUnused($removedPaths, $ownerId, $product->id);
        } catch (\Throwable $e) {
            $this->cleanupStoredPaths($createdPaths);

            if ($e instanceof ValidationException) {
                throw $e;
            }

            throw $e;
        }

        return redirect()->route('products.edit', $product)->with('success', __('products_ui.flash.updated'));
    }

    public function addQuantity(Request $request, Product $product)
    {
        $this->authorizeProduct($product);
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);

        $request->validate(array_merge([
            'amount' => 'required|numeric|min:0.01',
            'cost_price' => 'required|numeric|min:0',
        ], $this->fundingRules($this->ownerId($user))));

        $ownerId = $this->ownerId($user);
        $amount = round((float) $request->input('amount'), 2);
        $costPrice = round((float) $request->input('cost_price'), 2);
        $funding = $this->resolveFunding($request);
        $clientUuid = $this->emptyToNull($request->input('intake_client_uuid'));

        $this->validateFundingAgainstLines($funding, [[
            'product_id' => $product->id,
            'quantity' => $amount,
            'unit_cost' => $costPrice,
        ]]);

        DB::transaction(function () use ($product, $ownerId, $amount, $costPrice, $user, $funding, $clientUuid) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($clientUuid) {
                $existingBatch = Batch::where('product_id', $product->id)
                    ->where('user_id', $ownerId)
                    ->where('intake_client_uuid', $clientUuid)
                    ->lockForUpdate()
                    ->first();

                if ($existingBatch) {
                    return;
                }
            }

            $batch = (($funding['mode'] ?? 'none') === 'none' && ! $clientUuid)
                ? Batch::where('product_id', $product->id)
                    ->where('cost_price', $costPrice)
                    ->whereNull('purchase_bill_id')
                    ->lockForUpdate()
                    ->orderBy('created_at')
                    ->first()
                : null;

            if ($batch) {
                $batch->quantity += $amount;
                $batch->save();
            } else {
                $batch = Batch::create([
                    'product_id' => $product->id,
                    'quantity' => $amount,
                    'cost_price' => $costPrice,
                    'user_id' => $ownerId,
                    'intake_client_uuid' => $clientUuid,
                ]);
            }

            $this->applyStockIncrease($product, $amount, $costPrice);

            $bill = app(StockIntake::class)->record($user, [[
                'product_id' => $product->id,
                'quantity' => $amount,
                'unit_cost' => $costPrice,
            ]], array_merge($funding, ['client_uuid' => $clientUuid]));

            if ($bill && ! $batch->purchase_bill_id) {
                $batch->purchase_bill_id = $bill->id;
                $batch->save();
            }
        });

        return response()->json([
            'success' => true,
            'new_quantity' => $product->fresh()->quantity,
            'message' => __('products_ui.flash.stock_added'),
        ]);
    }

    public function toggleActive(Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);
        $this->authorizeProduct($product);

        $wasActive = $product->is_active;
        $product->is_active = !$product->is_active;

        if ($wasActive && !$product->is_active) {
            $this->deleteProductImages($product);
            $product->pictures = null;
        }

        $product->save();

        return redirect()->route('products.index')->with(
            'success',
            $product->is_active ? __('products_ui.flash.activated') : __('products_ui.flash.deactivated'),
        );
    }

    public function destroy(Product $product)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['delete_products']);

        $this->authorizeProduct($product);

        $ownerId = $this->ownerId($user);
        $paths = $this->picturePathsFromProduct($product);

        $product->delete();

        $this->deletePathsIfUnused($paths, $ownerId, $product->id);

        return redirect()->route('products.index')->with('success', __('products_ui.flash.deleted'));
    }

    public function bulkUpdateStatus(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);

        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer',
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
        ]);

        $ownerId = $this->ownerId($user);
        $products = Product::where('user_id', $ownerId)
            ->whereIn('id', $request->input('product_ids', []))
            ->get();

        foreach ($products as $product) {
            $product->is_active = $request->input('action') === 'activate';
            if (! $product->is_active) {
                $this->deleteProductImages($product);
                $product->pictures = null;
            }
            $product->save();
        }

        return redirect()->route('products.index', $request->only(['search', 'category', 'status', 'view']) + [
            'low_stock' => $request->input('low_stock'),
            'page' => $request->input('page'),
        ])
            ->with('success', $request->input('action') === 'activate'
                ? __('products_ui.flash.bulk_activated', ['count' => $products->count()])
                : __('products_ui.flash.bulk_deactivated', ['count' => $products->count()]));
    }

    // Get all categories for the current user
    public function getCategories(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products', 'create_products', 'edit_products', 'create_bills']);
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
        $this->ensureEmployeeHasAnyPermission($user, ['create_products', 'edit_products', 'create_purchase_bills', 'edit_purchase_bills']);
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

        return response()->json([
            'duplicates' => $this->duplicateBarcodesForOwner($ownerId, $barcodes, $ignoreProductId ? (int) $ignoreProductId : null),
        ]);
    }

    // Enhanced search for all products with quantity ordering
    public function searchAllProducts(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products', 'create_bills']);
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
        $this->ensureEmployeeHasAnyPermission($user, ['view_products', 'create_bills']);
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
        $this->ensureEmployeeHasAnyPermission($user, ['view_products']);
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $barcode = trim($request->input('barcode'));
        $visibility = $this->productVisibility($user);

        if (!$barcode) {
            return view('products.barcode-search', ['results' => null, 'searched' => false]);
        }

        // First, check if this is an IMEI code
        $imeiQuery = ProductImei::where('user_id', $ownerId)
            ->where('imei', $barcode)
            ->with('product');

        if ($visibility['suppliers']) {
            $imeiQuery->with('supplier');
        }

        if ($visibility['purchase_bills']) {
            $imeiQuery->with('purchaseBill');
        }

        if ($visibility['bills']) {
            $imeiQuery->with('saleBill');

            if ($visibility['customers']) {
                $imeiQuery->with('saleBill.customer');
            }

            $imeiQuery->with('saleBill.creator');
        }

        $imeiResult = $imeiQuery->first();
        $this->limitImeiVisibility($imeiResult, $visibility);

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
        $barcodeResults = $this->limitPurchaseResultVisibility($barcodeResults, $visibility);

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
                $productSuppliers = $this->limitPurchaseResultVisibility($productSuppliers, $visibility);
            }
        }

        return view('products.barcode-search', [
            'barcodeResults' => $barcodeResults,
            'productSuppliers' => $productSuppliers,
            'imeiResult' => $imeiResult,
            'searched' => true,
            'barcode' => $barcode,
            'productVisibility' => $visibility,
        ]);
    }

    // Get suppliers for a specific product
    public function getProductSuppliers(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_purchase_bills', 'view_products']);
        if ($user->role === 'employee' && ! $user->hasPermission('view_suppliers')) {
            abort(403);
        }
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $productId = $request->input('product_id');

        if (!$productId) {
            return response()->json(['error' => __('products_ui.validation.product_id_required')], 400);
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
        $this->ensureEmployeeHasAnyPermission($user, ['view_products']);
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

    private function authorizeProduct(Product $product)
    {
        $user = auth()->user();
        if ($product->user_id !== $this->ownerId($user)) {
            abort(403);
        }
    }

    /**
     * Display out-of-stock products page with deactivation warnings
     */
    public function outOfStock(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['view_products']);

        $ownerId = $this->ownerId($user);

        $owner = $user->role === 'employee' ? User::find($user->shop_owner_id) : $user;

        $warningMonths = $request->get('warning_months', $owner->product_warning_period ?? 4);
        $deactivationMonths = $request->get('deactivation_months', $owner->product_deactivation_period ?? 6);

        $warningCutoff = now()->subMonths($warningMonths);
        $deactivationCutoff = now()->subMonths($deactivationMonths);

        $query = Product::where('user_id', $ownerId)
            ->where('quantity', 0)
            ->where('is_active', true)
            ->whereNotNull('last_sale_date')
            ->where('last_sale_date', '<=', $warningCutoff);

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

        $products = $query->orderBy('last_sale_date', 'asc')->paginate(25)->withQueryString();

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

    public function outOfStockBulk(Request $request)
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['edit_products']);

        $ownerId = $this->ownerId($user);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['extend', 'deactivate'])],
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer',
            'warning_months' => 'nullable|integer|min:1|max:24',
            'deactivation_months' => 'nullable|integer|min:1|max:36',
            'extend_months' => 'nullable|integer|min:1|max:36',
        ]);

        $productIds = Product::where('user_id', $ownerId)
            ->whereIn('id', $validated['product_ids'])
            ->pluck('id')
            ->all();

        if (count($productIds) !== count(array_unique($validated['product_ids']))) {
            throw ValidationException::withMessages([
                'product_ids' => [__('products_ui.flash.no_products_selected')],
            ]);
        }

        $redirectParams = array_filter([
            'warning_months' => $request->input('warning_months'),
            'deactivation_months' => $request->input('deactivation_months'),
            'filter' => $request->input('filter'),
        ], fn ($value) => $value !== null && $value !== '');

        if ($validated['action'] === 'extend') {
            $extendMonths = (int) ($validated['extend_months'] ?? $validated['deactivation_months'] ?? 1);
            Product::whereIn('id', $productIds)
                ->where('user_id', $ownerId)
                ->update(['extended_until' => now()->addMonths($extendMonths)]);

            return redirect()->route('products.out-of-stock', $redirectParams)
                ->with('success', __('products_ui.flash.out_of_stock_extended'));
        }

        $deactivatedCount = $this->deactivateProducts($productIds, $ownerId);

        return redirect()->route('products.out-of-stock', $redirectParams)
            ->with('success', __('products_ui.flash.bulk_deactivated', ['count' => $deactivatedCount]));
    }

    /**
     * Get the next auto-increment product ID
     */
    public function getNextProductId()
    {
        $user = auth()->user();
        $this->ensureEmployeeHasAnyPermission($user, ['create_products']);

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
        $this->ensureEmployeeHasAnyPermission($user, ['create_products']);

        $this->authorizeProduct($product);

        if (!$product->variant_group_id) {
            throw ValidationException::withMessages([
                'new_variants' => [__('products_ui.validation.variant_group_required')],
            ]);
        }

        $ownerId = $this->ownerId($user);
        $request->validate(array_merge([
            'new_variants' => 'required|array|min:1',
            'new_variants.*.name' => 'required|string|max:255',
            'new_variants.*.quantity' => 'required|numeric|min:0',
            'new_variants.*.barcode' => 'nullable|string|max:255',
            'intake_client_uuid' => 'nullable|string|max:64',
        ], $this->fundingRules($ownerId)));

        $variantGroup = $product->variantGroup;
        $funding = $this->resolveFunding($request);
        $createdCount = 0;
        $clientUuid = $this->normalizeIntakeClientUuid($request->input('intake_client_uuid'));
        $variantTokens = $this->variantIntakeTokens($clientUuid, count((array) $request->input('new_variants', [])));

        if ($existingProduct = $this->findExistingProductForIntakeTokens($ownerId, $variantTokens)) {
            return redirect()->route('products.edit', $existingProduct)
                ->with('success', __('products_ui.flash.intake_already_processed'));
        }

        $duplicateProductId = null;

        DB::transaction(function () use ($request, $product, $variantGroup, $ownerId, $user, $funding, $variantTokens, &$createdCount, &$duplicateProductId) {
            if ($existingProduct = $this->findExistingProductForIntakeTokens($ownerId, $variantTokens, true)) {
                $duplicateProductId = $existingProduct->id;

                return;
            }

            $lines = [];
            $createdBatches = [];

            foreach ($request->input('new_variants', []) as $index => $variantData) {
                $newProduct = new Product();
                $newProduct->name = $variantGroup->name . ' - ' . trim((string) $variantData['name']);
                $newProduct->category = $product->category;
                $newProduct->barcode = $this->emptyToNull($variantData['barcode'] ?? null);
                $newProduct->quantity = round((float) ($variantData['quantity'] ?? 0), 2);
                $newProduct->cost_price = $product->cost_price;
                $newProduct->selling_price = $product->selling_price;
                $newProduct->user_id = $ownerId;
                $newProduct->has_tags = $product->has_tags;
                $newProduct->variant_group_id = $product->variant_group_id;
                $newProduct->variant_name = trim((string) $variantData['name']);
                $newProduct->pictures = $product->pictures;
                $newProduct->save();

                if ($newProduct->quantity > 0) {
                    $createdBatches[] = Batch::create([
                        'product_id' => $newProduct->id,
                        'quantity' => $newProduct->quantity,
                        'cost_price' => (float) $product->cost_price,
                        'user_id' => $ownerId,
                        'intake_client_uuid' => $variantTokens[$index] ?? null,
                    ]);

                    $lines[] = [
                        'product_id' => $newProduct->id,
                        'quantity' => $newProduct->quantity,
                        'unit_cost' => (float) $product->cost_price,
                    ];
                }

                $createdCount++;
            }

            $this->validateFundingAgainstLines($funding, $lines);
            if ($lines) {
                $bill = app(StockIntake::class)->record($user, $lines, $funding);
                $this->attachPurchaseBillToBatches($createdBatches, $bill);
            }
        });

        if ($duplicateProductId) {
            return redirect()->route('products.edit', $duplicateProductId)
                ->with('success', __('products_ui.flash.intake_already_processed'));
        }

        return redirect()->route('products.edit', $product->id)
            ->with('success', __('products_ui.flash.variants_added', ['count' => $createdCount]));
    }

    private function deleteProductImages(Product $product)
    {
        $this->deletePathsIfUnused($this->picturePathsFromProduct($product), $product->user_id, $product->id);
    }

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
                $this->deleteProductImages($product);
                $product->pictures = null;
                $product->save();
                $count++;
            }
        }
        return $count;
    }

    private function countTotalImages($ownerId)
    {
        return count($this->allUniquePicturePaths($ownerId));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function ensureEmployeeHasAnyPermission(User $user, array $permissions): void
    {
        if ($user->role !== 'employee') {
            return;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        abort(403);
    }

    private function ownerId(User $user): int
    {
        return (int) $user->ownerId();
    }

    private function productRules(int $ownerId, bool $hasVariants = false, bool $isUpdate = false): array
    {
        $uploadConstraints = ImageProcessor::uploadConstraints();

        $rules = [
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'additional_barcodes' => 'nullable|array',
            'additional_barcodes.*' => 'nullable|string|max:255',
            'pictures' => 'nullable|array|max:' . $uploadConstraints['max_files'],
            'pictures.*' => 'sometimes|file|mimetypes:image/jpeg,image/png,image/webp,image/gif|max:' . $uploadConstraints['file_kilobytes'],
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'has_tags' => 'nullable|boolean',
            'has_imeis' => 'nullable|boolean',
            'low_stock_threshold' => 'nullable|integer|min:1',
            'intake_client_uuid' => 'nullable|string|max:64',
        ];

        if ($hasVariants) {
            $rules['variants'] = 'required|array|min:1';
            $rules['variants.*.name'] = 'required|string|max:255';
            $rules['variants.*.quantity'] = 'required|numeric|min:0';
            $rules['variants.*.barcode'] = 'nullable|string|max:255';
        } else {
            $rules['quantity'] = 'nullable|numeric|min:0';
            $rules['new_imeis'] = 'nullable|array';
            $rules['new_imeis.*'] = 'nullable|string|max:255';
            $rules['new_imeis_supplier'] = [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ];
            $rules['new_imeis_date'] = 'nullable|date';
        }

        if ($isUpdate) {
            $rules['existing_pictures'] = 'nullable|array';
            $rules['existing_pictures.*'] = 'nullable|string|max:255';
            $rules['picture_order'] = 'nullable|array';
            $rules['picture_order.*'] = 'nullable|string|max:255';
            $rules['new_picture_tokens'] = 'nullable|array';
            $rules['new_picture_tokens.*'] = 'nullable|string|max:100';
        }

        return $rules;
    }

    private function fundingRules(int $ownerId): array
    {
        return [
            'funding_mode' => ['nullable', Rule::in(StockIntake::MODES)],
            'funding_supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ],
            'funding_paid_amount' => 'nullable|numeric|min:0',
            'funding_payment_method' => ['nullable', 'string', Rule::in(StockIntake::PAYMENT_METHODS)],
            'funding_date' => 'nullable|date',
            'funding_note' => 'nullable|string|max:1000',
        ];
    }

    private function normalizeLowStockThreshold(mixed $value): int
    {
        $threshold = $this->emptyToNull($value);

        return $threshold === null ? 10 : max(1, (int) $threshold);
    }

    private function validatePictureUploadEnvelope(Request $request): void
    {
        $constraints = ImageProcessor::uploadConstraints();
        $pictures = $request->file('pictures', []);

        if (count($pictures) > $constraints['max_files']) {
            throw ValidationException::withMessages([
                'pictures' => [__('products_ui.validation.image_count', ['count' => $constraints['max_files']])],
            ]);
        }

        $totalBytes = 0;
        foreach ($pictures as $picture) {
            $size = (int) ($picture?->getSize() ?? 0);
            if ($size > $constraints['file_bytes']) {
                throw ValidationException::withMessages([
                    'pictures' => [__('products_ui.validation.image_file_too_large', ['max' => $constraints['file_label']])],
                ]);
            }

            $totalBytes += $size;
        }

        if ($totalBytes > $constraints['request_bytes']) {
            throw ValidationException::withMessages([
                'pictures' => [__('products_ui.validation.image_request_too_large', ['max' => $constraints['request_label']])],
            ]);
        }
    }

    private function assertTenantBarcodesAvailable(int $ownerId, array $barcodes, ?int $ignoreProductId = null, ?bool $forceDuplicate = false): void
    {
        if ($forceDuplicate) {
            return;
        }

        if ($this->duplicateBarcodesForOwner($ownerId, $barcodes, $ignoreProductId) === []) {
            return;
        }

        throw ValidationException::withMessages([
            'barcode' => [__('products_ui.validation.duplicate_barcodes_existing')],
        ]);
    }

    /**
     * @return list<array{barcode:string,products:array<int,array{id:int,name:string}>}>
     */
    private function duplicateBarcodesForOwner(int $ownerId, array $barcodes, ?int $ignoreProductId = null): array
    {
        $barcodes = array_values(array_unique(array_filter(array_map(
            fn ($barcode) => trim((string) $barcode),
            $barcodes,
        ))));

        if ($barcodes === []) {
            return [];
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

        foreach ($mainMatches as $matchedProduct) {
            $code = trim((string) $matchedProduct->barcode);
            if ($code === '') {
                continue;
            }

            if (! isset($duplicates[$code])) {
                $duplicates[$code] = [
                    'barcode' => $code,
                    'products' => [],
                ];
            }

            $duplicates[$code]['products'][$matchedProduct->id] = [
                'id' => $matchedProduct->id,
                'name' => $matchedProduct->name,
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
            if ($code === '') {
                continue;
            }

            if (! isset($duplicates[$code])) {
                $duplicates[$code] = [
                    'barcode' => $code,
                    'products' => [],
                ];
            }

            $duplicates[$code]['products'][$match->id] = [
                'id' => $match->id,
                'name' => $match->name,
            ];
        }

        return array_values(array_map(function ($entry) {
            $entry['products'] = array_values($entry['products']);

            return $entry;
        }, $duplicates));
    }

    /**
     * @return array{purchase_bills:bool,suppliers:bool,bills:bool,customers:bool,costs:bool}
     */
    private function productVisibility(User $user): array
    {
        $fullAccess = $user->role !== 'employee';

        return [
            'purchase_bills' => $fullAccess || $user->hasPermission('view_purchase_bills'),
            'suppliers' => $fullAccess || $user->hasPermission('view_suppliers'),
            'bills' => $fullAccess || $user->hasPermission('view_bills'),
            'customers' => $fullAccess || $user->hasPermission('view_customers'),
            'costs' => $fullAccess || $user->hasPermission('view_purchase_bills'),
        ];
    }

    private function limitImeiVisibility(?ProductImei $imei, array $visibility): void
    {
        if (! $imei) {
            return;
        }

        if (! $visibility['suppliers']) {
            $imei->setRelation('supplier', null);
        }

        if (! $visibility['purchase_bills']) {
            $imei->setRelation('purchaseBill', null);
        }

        if (! $visibility['costs']) {
            $imei->unit_cost = null;
        }

        if (! $visibility['bills']) {
            $imei->selling_price = null;
            $imei->sold_at = null;
            $imei->setRelation('saleBill', null);
            return;
        }

        if ($imei->relationLoaded('saleBill') && $imei->saleBill && ! $visibility['customers']) {
            $imei->saleBill->setRelation('customer', null);
        }
    }

    private function limitPurchaseResultVisibility($results, array $visibility)
    {
        return $results->map(function ($result) use ($visibility) {
            if (! $visibility['suppliers']) {
                $result->supplier_name = null;
                $result->supplier_id = null;
            }

            if (! $visibility['purchase_bills']) {
                $result->reference_number = null;
            }

            if (! $visibility['costs']) {
                $result->unit_cost = null;
            }

            return $result;
        });
    }

    /**
     * @return list<string>
     */
    private function normalizeAdditionalBarcodes(mixed $mainBarcode, mixed $additionalBarcodes): array
    {
        $barcodes = [];

        foreach ((array) $additionalBarcodes as $barcode) {
            $barcode = trim((string) $barcode);
            if ($barcode !== '') {
                $barcodes[] = $barcode;
            }
        }

        $mainBarcode = trim((string) $mainBarcode);
        if ($mainBarcode !== '' && in_array($mainBarcode, $barcodes, true)) {
            throw ValidationException::withMessages([
                'additional_barcodes' => [__('products_ui.validation.duplicate_barcodes')],
            ]);
        }

        if (count($barcodes) !== count(array_unique($barcodes))) {
            throw ValidationException::withMessages([
                'additional_barcodes' => [__('products_ui.validation.duplicate_barcodes')],
            ]);
        }

        return $barcodes;
    }

    private function syncAdditionalBarcodes(Product $product, array $barcodes): void
    {
        $product->barcodes()->delete();

        foreach ($barcodes as $barcode) {
            ProductBarcode::create([
                'product_id' => $product->id,
                'barcode' => $barcode,
            ]);
        }
    }

    private function storeInitialImeis(Product $product, Request $request, int $ownerId): void
    {
        $supplierId = $this->emptyToNull($request->input('new_imeis_supplier'));
        $purchasedAt = $this->emptyToNull($request->input('new_imeis_date'));

        foreach (array_filter((array) $request->input('new_imeis', [])) as $imei) {
            $imei = trim((string) $imei);
            if ($imei === '') {
                continue;
            }

            ProductImei::create([
                'user_id' => $ownerId,
                'product_id' => $product->id,
                'imei' => $imei,
                'supplier_id' => $supplierId,
                'purchased_at' => $purchasedAt,
            ]);
        }
    }

    private function applyStockIncrease(Product $product, float $quantity, float $costPrice): void
    {
        $oldQty = (float) $product->quantity;
        $oldAvg = (float) $product->cost_price;

        $product->quantity = round($oldQty + $quantity, 2);
        $product->cost_price = $oldQty <= 0
            ? round($costPrice, 2)
            : round((($oldAvg * $oldQty) + ($costPrice * $quantity)) / max(0.01, $oldQty + $quantity), 2);

        $product->save();
    }

    /**
     * @param  list<Batch>  $batches
     */
    private function attachPurchaseBillToBatches(array $batches, ?\App\Models\PurchaseBill $bill): void
    {
        if (! $bill) {
            return;
        }

        foreach ($batches as $batch) {
            if ($batch->purchase_bill_id) {
                continue;
            }

            $batch->purchase_bill_id = $bill->id;
            $batch->save();
        }
    }

    /**
     * @param  array<int, string>  &$createdPaths
     * @return list<string>
     */
    private function processUploadedPictures(Request $request, ImageProcessor $imageProcessor, array &$createdPaths): array
    {
        $stored = [];

        foreach ($request->file('pictures', []) as $picture) {
            try {
                $stored[] = $imageProcessor->storeProductImage($picture);
            } catch (\InvalidArgumentException $e) {
                $this->cleanupStoredPaths($stored);

                throw ValidationException::withMessages([
                    'pictures' => [__($e->getMessage())],
                ]);
            }
        }

        $createdPaths = array_merge($createdPaths, $stored);

        return $stored;
    }

    /**
     * @return array{mode:string,supplier_id:mixed,paid_amount:mixed,payment_method:mixed,date:mixed,note:mixed}
     */
    private function resolveFunding(Request $request): array
    {
        return [
            'mode' => $request->input('funding_mode', 'none') ?: 'none',
            'supplier_id' => $this->emptyToNull($request->input('funding_supplier_id')),
            'paid_amount' => $this->emptyToNull($request->input('funding_paid_amount')),
            'payment_method' => $this->emptyToNull($request->input('funding_payment_method')),
            'date' => $this->emptyToNull($request->input('funding_date')),
            'note' => $this->emptyToNull($request->input('funding_note')),
            'client_uuid' => $this->emptyToNull($request->input('intake_client_uuid')),
        ];
    }

    private function normalizeIntakeClientUuid(mixed $value): ?string
    {
        $uuid = $this->emptyToNull($value);

        if (! is_string($uuid)) {
            return null;
        }

        $uuid = trim($uuid);

        return $uuid === '' ? null : $uuid;
    }

    /**
     * @return list<string>
     */
    private function variantIntakeTokens(?string $clientUuid, int $count): array
    {
        if (! $clientUuid || $count < 1) {
            return [];
        }

        $tokens = [];

        for ($index = 0; $index < $count; $index++) {
            if ($index === 0) {
                $tokens[] = $clientUuid;
                continue;
            }

            $suffix = '#' . ($index + 1);
            $tokens[] = substr($clientUuid, 0, max(0, 64 - strlen($suffix))) . $suffix;
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function findExistingProductForIntakeTokens(int $ownerId, array $tokens, bool $lockForUpdate = false): ?Product
    {
        $tokens = array_values(array_unique(array_filter(array_map(
            fn ($token) => trim((string) $token),
            $tokens,
        ))));

        if ($tokens === []) {
            return null;
        }

        $query = Batch::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('intake_client_uuid', $tokens)
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $batch = $query->first();

        if (! $batch) {
            return null;
        }

        return Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->find($batch->product_id);
    }

    /**
     * @param  list<array{product_id:int,quantity:float|int|string,unit_cost:float|int|string}>  $lines
     */
    private function validateFundingAgainstLines(array $funding, array $lines): void
    {
        $lines = array_values(array_filter($lines, fn ($line) => (float) ($line['quantity'] ?? 0) > 0));
        if ($lines === []) {
            return;
        }

        $mode = $funding['mode'] ?? 'none';
        $total = 0.0;
        foreach ($lines as $line) {
            $total += round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
        }
        $total = round($total, 2);

        if (in_array($mode, ['credit', 'partial'], true) && empty($funding['supplier_id'])) {
            throw ValidationException::withMessages([
                'funding_supplier_id' => [__('products_ui.validation.supplier_required')],
            ]);
        }

        if (in_array($mode, ['paid', 'partial'], true) && empty($funding['payment_method'])) {
            throw ValidationException::withMessages([
                'funding_payment_method' => [__('products_ui.validation.payment_method_required')],
            ]);
        }

        if ($mode === 'partial') {
            $paidAmount = round((float) ($funding['paid_amount'] ?? 0), 2);
            if ($paidAmount <= 0) {
                throw ValidationException::withMessages([
                    'funding_paid_amount' => [__('products_ui.validation.paid_amount_required')],
                ]);
            }

            if ($paidAmount > $total) {
                throw ValidationException::withMessages([
                    'funding_paid_amount' => [__('products_ui.validation.partial_payment_too_large')],
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function picturePathsFromProduct(Product $product): array
    {
        return $this->sanitizePicturePaths($product->pictures);
    }

    /**
     * @return list<string>
     */
    private function sanitizePicturePaths(mixed $pictures): array
    {
        if (is_array($pictures)) {
            $decoded = $pictures;
        } elseif (is_string($pictures) && $pictures !== '') {
            $decoded = json_decode($pictures, true);
        } else {
            $decoded = [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $paths = [];
        foreach ($decoded as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);
            if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'products/')) {
                continue;
            }

            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function validatedExistingPictures(array $requested, array $currentPictures): array
    {
        $allowed = array_flip($currentPictures);
        $valid = [];

        foreach ($requested as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);
            if (isset($allowed[$path])) {
                $valid[] = $path;
            }
        }

        if (count($valid) !== count(array_filter($requested, fn ($path) => is_string($path) && trim($path) !== ''))) {
            throw ValidationException::withMessages([
                'existing_pictures' => [__('products_ui.validation.invalid_existing_picture')],
            ]);
        }

        return array_values(array_unique($valid));
    }

    /**
     * @param  list<string>  $keptPictures
     * @param  list<string>  $newPictures
     * @param  list<string>  $order
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function mergeOrderedPictures(array $keptPictures, array $newPictures, array $order, array $tokens): array
    {
        if ($order === []) {
            return array_values(array_merge($keptPictures, $newPictures));
        }

        $tokenMap = [];
        foreach (array_values($tokens) as $index => $token) {
            if (is_string($token) && isset($newPictures[$index])) {
                $tokenMap[$token] = $newPictures[$index];
            }
        }

        $keptSet = array_flip($keptPictures);
        $ordered = [];

        foreach ($order as $item) {
            if (! is_string($item)) {
                continue;
            }

            if (str_starts_with($item, 'existing:')) {
                $path = substr($item, 9);
                if (isset($keptSet[$path])) {
                    $ordered[] = $path;
                    unset($keptSet[$path]);
                }
            } elseif (str_starts_with($item, 'upload:')) {
                $token = substr($item, 7);
                if (isset($tokenMap[$token])) {
                    $ordered[] = $tokenMap[$token];
                    unset($tokenMap[$token]);
                }
            }
        }

        return array_values(array_merge($ordered, array_keys($keptSet), array_values($tokenMap)));
    }

    private function ensureImageLimit(int $ownerId, int $newImageCount, int $releasableCount = 0): void
    {
        $owner = User::findOrFail($ownerId);
        $finalCount = $this->countTotalImages($ownerId) - max(0, $releasableCount) + $newImageCount;

        if ($finalCount > (int) ($owner->image_limit ?? 0)) {
            throw ValidationException::withMessages([
                'pictures' => [__('products_ui.validation.image_limit', ['limit' => $owner->image_limit])],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function allUniquePicturePaths(int $ownerId): array
    {
        $paths = [];
        foreach (Product::where('user_id', $ownerId)->whereNotNull('pictures')->get(['pictures']) as $product) {
            $paths = array_merge($paths, $this->sanitizePicturePaths($product->pictures));
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $paths
     */
    private function countRemovableUniquePaths(int $ownerId, array $paths, int $ignoreProductId): int
    {
        $count = 0;
        foreach (array_unique($paths) as $path) {
            if (! $this->isPathReferencedElsewhere($ownerId, $path, $ignoreProductId)) {
                $count++;
            }
        }

        return $count;
    }

    private function isPathReferencedElsewhere(int $ownerId, string $path, int $ignoreProductId): bool
    {
        foreach (Product::where('user_id', $ownerId)
            ->where('id', '!=', $ignoreProductId)
            ->whereNotNull('pictures')
            ->get(['pictures']) as $product) {
            if (in_array($path, $this->sanitizePicturePaths($product->pictures), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     */
    private function deletePathsIfUnused(array $paths, int $ownerId, ?int $ignoreProductId = null): void
    {
        foreach (array_unique($paths) as $path) {
            if ($ignoreProductId !== null && $this->isPathReferencedElsewhere($ownerId, $path, $ignoreProductId)) {
                continue;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function cleanupStoredPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
