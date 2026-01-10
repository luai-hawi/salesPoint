<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Batch;
use App\Models\CustomerPayment;
use Illuminate\Http\Request;

class BillsController extends Controller
{
    public function getTags(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $tags = \App\Models\Tag::where('user_id', $ownerId)->get();

        return response()->json($tags);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        $date = $request->input('date');

        $baseQuery = Bill::where('user_id', $ownerId)
            ->with(['products' => function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            }, 'customer', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($date) {
            $baseQuery->whereDate('created_at', $date);
        }

        // Handle search
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $searchTerms = explode(' ', $search);
            foreach ($searchTerms as $term) {
                $term = trim($term);
                if ($term) {
                    $baseQuery->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(CAST(id AS CHAR)) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(note) LIKE ?', ["%{$term}%"])
                            ->orWhereRaw('LOWER(CAST(total_price AS CHAR)) LIKE ?', ["%{$term}%"])
                            ->orWhereHas('customer', function ($customerQuery) use ($term) {
                                $customerQuery->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
                            })
                            ->orWhereHas('creator', function ($creatorQuery) use ($term) {
                                $creatorQuery->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
                            })
                            ->orWhereHas('products', function ($productQuery) use ($term) {
                                $productQuery->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                                    ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$term}%"]);
                            })
                            ->orWhereHas('products.barcodes', function ($barcodeQuery) use ($term) {
                                $barcodeQuery->whereRaw('LOWER(barcode) LIKE ?', ["%{$term}%"]);
                            });
                    });
                }
            }
        }

        // Use selected date or default to today for money calculations
        $calculationDate = $date ? $date : today();

        $calculationBills = Bill::where('user_id', $ownerId)
            ->with(['products' => function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            }])
            ->whereDate('created_at', $calculationDate)
            ->get();

        $totalSales = $calculationBills->sum('total_price');
        $totalProfit = $calculationBills->sum(function ($bill) {
            return $bill->products->sum(function ($product) {
                $quantity = $product->pivot->quantity;
                $costPrice = $product->pivot->cost_price;
                $sellingPrice = $product->pivot->selling_price;
                $discount = $product->pivot->discount;

                return (($sellingPrice - $costPrice) * $quantity) - $discount;
            });
        });

        $bills = $baseQuery->paginate(20);

        // Handle AJAX requests
        if ($request->ajax()) {
            return response()->json([
                'bills' => $bills->items(),
                'pagination' => [
                    'current_page' => $bills->currentPage(),
                    'last_page' => $bills->lastPage(),
                    'total' => $bills->total(),
                ]
            ]);
        }

        return view('bills.index', [
            'bills' => $bills,
            'totalSales' => $totalSales,
            'totalProfit' => $totalProfit,
            'selectedDate' => $date,
        ]);
    }
    // public function create()
    // {
    //     $user = auth()->user();
    //     $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

    //     $products = Product::where('user_id', $ownerId)->get();
    //     $customers = Customer::where('user_id', $ownerId)->get();

    //     // Prepare products data for JavaScript
    //     $productsForJS = $products->map(function ($p) {
    //         return [
    //             'id' => $p->id,
    //             'name' => $p->name,
    //             'price' => $p->selling_price,
    //             'cost_price' => $p->cost_price,
    //             'barcode' => $p->barcode,
    //             'quantity' => $p->quantity,
    //         ];
    //     })->toArray();

    //     return view('bills.create', compact('productsForJS', 'products', 'customers'));
    // }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $request->validate([
            'product_ids' => 'required|array|min:1',
            'quantities' => 'required|array',
            'discounts' => 'required|array',
            'cost_prices' => 'required|array',
            'selling_prices' => 'required|array',
            'note' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|exists:customers,id,user_id,' . $ownerId,
            'selling_prices' => 'required|array',
            'discount_types' => 'array',
        ]);

        // Check if it's a damaged bill
        $isDamaged = $request->has('is_damaged');
        $noteText = $request->input('note', '');

        if ($isDamaged) {
            $noteText .= ($noteText ? ' - ' : '') . 'Damaged Bill';
        }

        $bill = Bill::create([
            'note' => $noteText,
            'total_price' => 0,
            'customer_id' => $request->input('customer_id'),
            'user_id' => $ownerId,
            'created_by' => $user->id,
            'is_damaged' => $isDamaged,
        ]);

        $total = 0;

        foreach ($request->product_ids as $index => $productId) {
            if (empty($productId)) continue;

            $qty = (int) $request->quantities[$index];
            $costPrice = (float) $request->cost_prices[$index];
            $sellingPrice = (float) $request->selling_prices[$index];
            $discountType = $request->discount_types[$index] ?? 'total';
            $discount = $isDamaged ? ($qty * $sellingPrice) : (float) $request->discounts[$index];
            $tags = $request->product_tags[$index] ?? null; // New tags field

            if ($discountType === 'per-unit' && !$isDamaged) {
                $discount = $discount * $qty;
            }

            $product = Product::where('id', $productId)
                ->where('user_id', $ownerId)
                ->firstOrFail();

            // Update product quantity
            $product->quantity -= $qty;
            // Update last sale date when product is sold
            $product->last_sale_date = now();
            $product->save();

            // Handle batch consumption (FIFO)
            $remainingQty = $qty;
            $batches = $product->batches()->where('quantity', '>', 0)->orderBy('created_at')->get();

            foreach ($batches as $batch) {
                if ($remainingQty <= 0) break;

                $consume = min($batch->quantity, $remainingQty);
                $batch->quantity -= $consume;
                $batch->save();
                $remainingQty -= $consume;
            }

            // If still remaining quantity, create negative batch
            if ($remainingQty > 0) {
                $lastBatch = $product->batches()->latest()->first();
                if ($lastBatch) {
                    $lastBatch->quantity -= $remainingQty;
                    $lastBatch->save();
                } else {
                    $product->batches()->create([
                        'quantity' => -1 * $remainingQty,
                        'cost_price' => $product->cost_price,
                        'user_id' => $ownerId,
                    ]);
                }
            }

            $tagsTotal = $this->calculateTagsTotal($tags);
            $lineTotal = ($sellingPrice * $qty) - $discount + ($tagsTotal * $qty);

            $bill->products()->attach($productId, [
                'quantity' => $qty,
                'discount' => $discount,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'tags' => $tags, // Add tags to pivot
            ]);

            $total += max(0, $lineTotal);
        }

        $bill->update(['total_price' => $total]);

        // Handle customer payment if customer is selected
        if ($bill->customer_id && $total > 0) {
            $bill->customer->payments()->create([
                'amount' => -1 * $total,
                'type' => 'cash',
                'note' => "Bill #{$bill->id} created as debt",
                'user_id' => $ownerId,
            ]);
            $bill->customer->update(['balance' => $bill->customer->balance - $total]);
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Bill created successfully!',
                'bill' => $bill
            ]);
        } else {
            return redirect()->route('dashboard')->with('success', 'Bill created successfully!');
        }
    }

    public function show(Bill $bill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->with('barcodes')
            ->get();
        $bill->load(['products', 'customer', 'creator']);

        return view('bills.show', compact('bill', 'products'));
    }

    public function edit(Bill $bill)
    {
        return $this->show($bill);
    }

    private function calculateTagsTotal($tagsString)
    {
        if (!$tagsString) return 0;

        $total = 0;
        $tagPairs = explode('&', $tagsString);

        foreach ($tagPairs as $pair) {
            if (strpos($pair, '@') !== false) {
                $parts = explode('@', $pair);
                if (count($parts) == 2) {
                    $total += floatval($parts[1]);
                }
            }
        }

        return $total;
    }

    public function update(Request $request, Bill $bill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('edit_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'remove_products' => 'array',
            'new_product_id' => 'nullable|exists:products,id,user_id,' . $ownerId,
            'new_quantity' => 'nullable|integer|min:1',
            'dynamic_product_ids' => 'array',
            'dynamic_quantities' => 'array',
            'dynamic_discounts' => 'array',
            'dynamic_product_tags' => 'array',
        ]);

        // Update note
        $noteText = $request->input('note', '');
        if ($bill->is_damaged && !str_contains($noteText, 'Damaged Bill')) {
            $noteText .= ($noteText ? ' - ' : '') . 'Damaged Bill';
        }
        $bill->update(['note' => $noteText]);

        // Get products to remove
        $toRemove = $request->input('remove_products', []);

        // Handle deletions first
        if (!empty($toRemove)) {
            foreach ($toRemove as $uniqueKey) {
                // Split the unique key
                $keyParts = explode('_', $uniqueKey, 2);
                $productId = $keyParts[0];
                $tags = isset($keyParts[1]) ? $keyParts[1] : '';

                // Find matching pivot record
                $pivotQuery = \DB::table('bill_product')
                    ->where('bill_id', $bill->id)
                    ->where('product_id', $productId);

                // Handle tags comparison
                if ($tags === '') {
                    $pivotQuery->where(function ($q) {
                        $q->whereNull('tags')->orWhere('tags', '');
                    });
                } else {
                    $pivotQuery->where('tags', $tags);
                }

                $pivotRecord = $pivotQuery->first();

                if ($pivotRecord) {
                    // Return stock
                    $product = Product::find($productId);
                    if ($product) {
                        $product->quantity += $pivotRecord->quantity;
                        $product->save();

                        // Return to batch
                        $this->returnToBatch($product, $pivotRecord->quantity, $pivotRecord->cost_price, $ownerId);
                    }

                    // Delete the pivot record
                    $pivotQuery->delete();
                }
            }
        }

        // Update existing products (quantities/discounts) - but skip removed ones
        $quantities = $request->input('quantities', []);
        $discounts = $request->input('discounts', []);

        foreach ($quantities as $uniqueKey => $quantity) {
            // Skip if this was marked for removal
            if (in_array($uniqueKey, $toRemove)) {
                continue;
            }

            $keyParts = explode('_', $uniqueKey, 2);
            $productId = $keyParts[0];
            $tags = isset($keyParts[1]) ? $keyParts[1] : '';

            // Find the pivot record
            $pivotQuery = \DB::table('bill_product')
                ->where('bill_id', $bill->id)
                ->where('product_id', $productId);

            if ($tags === '') {
                $pivotQuery->where(function ($q) {
                    $q->whereNull('tags')->orWhere('tags', '');
                });
            } else {
                $pivotQuery->where('tags', $tags);
            }

            $pivotRecord = $pivotQuery->first();

            if ($pivotRecord) {
                $newQuantity = (int)$quantity;
                $newDiscount = isset($discounts[$uniqueKey]) ? (float)$discounts[$uniqueKey] : $pivotRecord->discount;
                $quantityDiff = $newQuantity - $pivotRecord->quantity;

                // Update stock if quantity changed
                if ($quantityDiff != 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        $product->quantity -= $quantityDiff;
                        $product->save();

                        if ($quantityDiff > 0) {
                            $this->consumeProductStockAllowNegative($product, $quantityDiff);
                        } else {
                            $this->returnToBatch($product, abs($quantityDiff), $pivotRecord->cost_price, $ownerId);
                        }
                    }
                }

                // Update the pivot record
                $pivotQuery->update([
                    'quantity' => $newQuantity,
                    'discount' => $newDiscount,
                    'updated_at' => now(),
                ]);
            }
        }

        // Add new products from dynamic data - BUT EXCLUDE DELETED ONES
        $dynamicProductIds = $request->input('dynamic_product_ids', []);
        $dynamicQuantities = $request->input('dynamic_quantities', []);
        $dynamicDiscounts = $request->input('dynamic_discounts', []);
        $dynamicProductTags = $request->input('dynamic_product_tags', []);

        foreach ($dynamicProductIds as $uniqueKey => $productId) {
            // CRITICAL: Skip if this product was marked for deletion
            if (in_array($uniqueKey, $toRemove)) {
                continue;
            }

            // Check if this already exists in database
            $keyParts = explode('_', $uniqueKey, 2);
            $checkProductId = $keyParts[0];
            $checkTags = isset($keyParts[1]) ? $keyParts[1] : '';

            $existsQuery = \DB::table('bill_product')
                ->where('bill_id', $bill->id)
                ->where('product_id', $checkProductId);

            if ($checkTags === '') {
                $existsQuery->where(function ($q) {
                    $q->whereNull('tags')->orWhere('tags', '');
                });
            } else {
                $existsQuery->where('tags', $checkTags);
            }

            // Skip if already exists
            if ($existsQuery->exists()) {
                continue;
            }

            $quantity = (int)($dynamicQuantities[$uniqueKey] ?? 1);
            $discount = (float)($dynamicDiscounts[$uniqueKey] ?? 0);
            $tags = $dynamicProductTags[$uniqueKey] ?? '';

            $product = Product::find($productId);
            if (!$product) continue;

            // Update stock
            $product->quantity -= $quantity;
            $product->save();

            // Handle batch consumption
            $this->consumeProductStockAllowNegative($product, $quantity);

            // Apply damage discount if needed
            if ($bill->is_damaged) {
                $discount = $quantity * $product->selling_price;
            }

            // Insert new record
            \DB::table('bill_product')->insert([
                'bill_id' => $bill->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'discount' => $discount,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'tags' => $tags === '' ? null : $tags,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Handle dropdown product addition
        $newProductId = $request->input('new_product_id');
        $newQty = (int)$request->input('new_quantity');

        if ($newProductId && $newQty > 0) {
            $product = Product::find($newProductId);
            if ($product) {
                $product->quantity -= $newQty;
                $product->save();

                $this->consumeProductStockAllowNegative($product, $newQty);

                $discount = $bill->is_damaged ? ($newQty * $product->selling_price) : 0;

                \DB::table('bill_product')->insert([
                    'bill_id' => $bill->id,
                    'product_id' => $newProductId,
                    'quantity' => $newQty,
                    'discount' => $discount,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'tags' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Recalculate totals
        $this->recalculateBillTotal($bill);
        $this->updateCustomerBalance($bill);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Bill updated successfully!',
                'bill' => $bill
            ]);
        } else {
            return redirect()->route('bills.show', $bill->id)->with('success', 'Bill updated successfully!');
        }
    }
    /**
     * Helper method to consume product stock using FIFO (ALLOWS NEGATIVE STOCK)
     */
    private function consumeProductStockAllowNegative($product, $quantity)
    {
        // Product stock is already updated in the main method
        // Just handle batch consumption, allowing negative batches

        $remaining = $quantity;
        $batches = $product->batches()->where('quantity', '>', 0)->orderBy('id')->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $consume = min($remaining, $batch->quantity);
            $batch->quantity -= $consume;
            $remaining -= $consume;
            $batch->save();
        }

        // If still remaining, consume from the latest batch (can go negative)
        if ($remaining > 0) {
            $lastBatch = $product->batches()->orderByDesc('id')->first();
            if ($lastBatch) {
                $lastBatch->quantity -= $remaining;
                $lastBatch->save();
            } else {
                // If no batches exist, create a negative batch
                $product->batches()->create([
                    'quantity' => -$remaining,
                    'cost_price' => $product->cost_price,
                    'user_id' => auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : auth()->user()->id,
                ]);
            }
        }
    }
    /**
     * Helper method to return stock to appropriate batch
     */
    private function returnToBatch($product, $quantity, $costPrice, $ownerId)
    {
        $previousQuantity = $product->quantity - $quantity; // Quantity before return
        //recalculate average cost price
        if ($product->quantity <= 0) {
            $product->cost_price = $costPrice;
        } else {
            $product->cost_price =
                ($product->cost_price * $previousQuantity + $costPrice * $quantity)
                / max(1, ($previousQuantity + $quantity));
        }
        $product->cost_price = round($product->cost_price, 2);
        $product->save();


        $batch = $product->batches()->where('cost_price', $costPrice)->first();

        if ($batch) {
            $batch->quantity += $quantity;
            $batch->save();
        } else {
            $product->batches()->create([
                'quantity' => $quantity,
                'cost_price' => $costPrice,
                'user_id' => $ownerId,
            ]);
        }
    }

    /**
     * Helper method to recalculate bill total (positive quantities only)
     */
    private function recalculateBillTotal($bill)
    {
        $total = 0;
        $bill->load('products');

        foreach ($bill->products as $product) {
            $qty = $product->pivot->quantity;
            $unitPrice = $product->pivot->selling_price;
            $discount = $product->pivot->discount ?? 0;

            // Calculate tags total if exists
            $tagsTotal = 0;
            if ($product->pivot->tags) {
                $tagsTotal = $this->calculateTagsTotal($product->pivot->tags);
            }

            // Calculate subtotal: (unit_price + tags_per_unit) * quantity - discount
            $subtotal = max(0, (($unitPrice + $tagsTotal) * $qty) - $discount);
            $total += $subtotal;
        }

        $bill->total_price = $total;
        $bill->save();
    }

    /**
     * Helper method to update customer balance
     */
    private function updateCustomerBalance($bill)
    {
        if ($bill->customer_id) {
            $customer = $bill->customer;

            $existingPayment = $customer->payments()
                ->where('note', "Bill #{$bill->id} created as debt")
                ->first();

            if ($existingPayment) {
                $oldAmount = abs($existingPayment->amount);
                $newAmount = $bill->total_price;
                $difference = $newAmount - $oldAmount;

                $existingPayment->update(['amount' => -1 * $newAmount]);
                $customer->update(['balance' => $customer->balance - $difference]);
            }
        }
    }

    public function destroy(Bill $bill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('delete_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized action.');
        }

        // 1. Restore product quantities and batches before deleting the bill
        foreach ($bill->products as $product) {
            $restoredQty = $product->pivot->quantity;
            $costPrice = $product->pivot->cost_price;

            // Store old quantity and average cost before update
            $oldQty = $product->quantity;
            $oldAvg = $product->cost_price;

            // Restore main product quantity
            $product->quantity += $restoredQty;

            // Update or create matching batch
            $batch = $product->batches()->where('cost_price', $costPrice)->first();

            if ($batch) {
                // Batch with same cost price exists → increase its quantity
                $batch->quantity += $restoredQty;
                $batch->save();
            } else {
                // No matching batch → create new one
                $product->batches()->create([
                    'quantity' => $restoredQty,
                    'cost_price' => $costPrice,
                    'user_id' => $ownerId,
                ]);
            }

            if ($oldQty <= 0) {
                $product->cost_price = $costPrice;
            } else {
                // Recalculate average cost price using weighted average
                $product->cost_price =
                    ($oldAvg * $oldQty + $costPrice * $restoredQty)
                    / max(1, ($oldQty + $restoredQty));
            }
            $product->cost_price = round($product->cost_price, 2);
            $product->save();
        }

        // 2. Detach all product relations
        $bill->products()->detach();

        // 3. If bill is linked to a customer, revert their balance
        if ($bill->customer_id) {
            $customer = $bill->customer;

            // Delete associated negative payment
            $deletedPayments = CustomerPayment::where('customer_id', $customer->id)
                ->where('note', "Bill #{$bill->id} created as debt")
                ->delete();

            if ($deletedPayments > 0) {
                // Increase customer balance (reduce debt)
                $customer->balance += $bill->total_price;
                $customer->save();
            }
        }

        // 4. Delete the bill
        $bill->delete();

        return redirect()->route('bills.index')->with('success', 'Bill deleted successfully! Product quantities and customer balance have been restored.');
    }

    /**
     * Quick actions for bill management
     */
    public function quickStats(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');

        $todayBills = Bill::where('user_id', $ownerId)
            ->whereDate('created_at', $today)
            ->with('products');

        $monthlyBills = Bill::where('user_id', $ownerId)
            ->where('created_at', 'like', $thisMonth . '%')
            ->with('products');

        $stats = [
            'today' => [
                'sales' => $todayBills->sum('total_price'),
                'bills_count' => $todayBills->count(),
                'profit' => $todayBills->get()->sum(function ($bill) {
                    return $bill->products->sum(function ($product) {
                        return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                    });
                }),
            ],
            'monthly' => [
                'sales' => $monthlyBills->sum('total_price'),
                'bills_count' => $monthlyBills->count(),
                'profit' => $monthlyBills->get()->sum(function ($bill) {
                    return $bill->products->sum(function ($product) {
                        return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
                    });
                }),
            ]
        ];

        return response()->json($stats);
    }


    /**
     * Duplicate a bill (useful for similar orders)
     */
    public function duplicate(Bill $bill)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->get();
        $customers = Customer::where('user_id', $ownerId)->get();

        // Prepare products data for JavaScript
        $productsForJS = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'barcode' => $p->barcode,
                'quantity' => $p->quantity,
            ];
        })->toArray();

        // Pre-populate with bill data
        $billData = [
            'note' => $bill->note,
            'customer_id' => $bill->customer_id,
            'products' => $bill->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'price' => $product->pivot->selling_price,
                    'cost_price' => $product->pivot->cost_price,
                    'discount' => $product->pivot->discount,
                ];
            }),
        ];

        return view('bills.create', compact('productsForJS', 'products', 'customers', 'billData'));
    }
}
