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
    public function index(Request $request)
    {
        $user = auth()->user();
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

        $allBills = (clone $baseQuery)->get();

        $totalSales = $allBills->sum('total_price');
        $totalProfit = $allBills->sum(function ($bill) {
            return $bill->products->sum(function ($product) {
                return ($product->pivot->selling_price - $product->pivot->cost_price) * $product->pivot->quantity;
            });
        });

        $bills = $baseQuery->paginate(20);

        return view('bills.index', [
            'bills' => $bills,
            'totalSales' => $totalSales,
            'totalProfit' => $totalProfit,
            'selectedDate' => $date,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        $products = Product::where('user_id', $ownerId)->get();
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

        return view('bills.create', compact('productsForJS', 'products', 'customers'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
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

            if ($discountType === 'per-unit' && !$isDamaged) {
                $discount = $discount * $qty;
            }

            $product = Product::where('id', $productId)
                ->where('user_id', $ownerId)
                ->firstOrFail();

           

            // Update product quantity
            $product->quantity -= $qty;
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

            $lineTotal = ($sellingPrice * $qty) - $discount;

            $bill->products()->attach($productId, [
                'quantity' => $qty,
                'discount' => $discount,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
            ]);

            $total += max(0, $lineTotal);
        }

        $bill->update(['total_price' => $total]);

        // Handle customer payment if customer is selected
        if ($bill->customer_id && $total > 0) {
            $bill->customer->payments()->create([
                'amount' => -1 * $total,
                'type' => 'payment',
                'note' => "Bill #{$bill->id} created as debt",
                'user_id' => $ownerId,
            ]);
            $bill->customer->update(['balance' => $bill->customer->balance - $total]);
        }

        return redirect()->route('dashboard')->with('success', 'Bill created successfully!');
    }

    public function show(Bill $bill)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        if ($bill->user_id !== $ownerId) {
            abort(403, 'Unauthorized');
        }

        $products = Product::where('user_id', $ownerId)->get();
        $bill->load(['products', 'customer', 'creator']);

        return view('bills.show', compact('bill', 'products'));
    }

    public function edit(Bill $bill)
    {
        return $this->show($bill);
    }

    public function update(Request $request, Bill $bill)
{
    $user = auth()->user();
    $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

    if ($bill->user_id !== $ownerId) {
        abort(403, 'Unauthorized');
    }

    $request->validate([
        'note' => 'nullable|string|max:1000',
        'quantities' => 'array',
        'quantities.*' => 'integer|min:1', // Bill quantities must be minimum 1
        'discounts' => 'array',
        'discounts.*' => 'numeric|min:0',
        'remove_products' => 'array',
        'new_product_id' => 'nullable|exists:products,id,user_id,' . $ownerId,
        'new_quantity' => 'nullable|integer|min:1', // Bill quantities must be minimum 1
        'dynamic_product_ids' => 'array',
        'dynamic_quantities' => 'array',
        'dynamic_quantities.*' => 'integer|min:1', // Bill quantities must be minimum 1
        'dynamic_discounts' => 'array',
        'dynamic_discounts.*' => 'numeric|min:0',
    ]);

    // Update note
    $noteText = $request->input('note', '');
    if ($bill->is_damaged && !str_contains($noteText, 'Damaged Bill')) {
        $noteText .= ($noteText ? ' - ' : '') . 'Damaged Bill';
    }
    $bill->note = $noteText;
    $bill->save();

    // Handle dynamic products
    $dynamicProductIds = $request->input('dynamic_product_ids', []);
    $dynamicQuantities = $request->input('dynamic_quantities', []);
    $dynamicDiscounts = $request->input('dynamic_discounts', []);

    

    if (!empty($dynamicProductIds)) {
        foreach ($dynamicProductIds as $productId) {
            $quantity = isset($dynamicQuantities[$productId]) ? (int)$dynamicQuantities[$productId] : 1;
            $discount = isset($dynamicDiscounts[$productId]) ? (float)$dynamicDiscounts[$productId] : 0;
            
            $product = Product::where('id', $productId)->where('user_id', $ownerId)->first();
            
            if (!$product) {
                continue;
            }

            $existingPivot = $bill->products()->where('product_id', $productId)->first();
            
            if ($existingPivot) {
                // EXISTING PRODUCT UPDATE
                $oldQty = $existingPivot->pivot->quantity;
                $newQty = $quantity;
                $diff = $newQty - $oldQty;

                if ($diff != 0) {
                    // ALLOW NEGATIVE STOCK - No stock validation
                    // Update product stock (can go negative)
                    $product->quantity -= $diff;
                    $product->save();

                    // Handle batch consumption/restoration
                    if ($diff > 0) {
                        // Consume more batches FIFO (allow negative batches)
                        $this->consumeProductStockAllowNegative($product, $diff);
                    } elseif ($diff < 0) {
                        // RETURNING TO STOCK
                        $returnQty = abs($diff);
                        $costPrice = $existingPivot->pivot->cost_price;
                        
                        // Return to appropriate batch
                        $this->returnToBatch($product, $returnQty, $costPrice, $ownerId);

                        // FIXED: Proper weighted average cost price calculation
                        $oldTotalValue = $product->cost_price * ($product->quantity - $returnQty);
                        $returnValue = $costPrice * $returnQty;
                        $newTotalQuantity = $product->quantity;
                        
                        if ($newTotalQuantity > 0) {
                            $product->cost_price = ($oldTotalValue + $returnValue) / $newTotalQuantity;
                            $product->cost_price = round($product->cost_price, 2);
                            $product->save();
                        }
                    }

                    
                }
                // Validate and set discount
                    $maxDiscount = $newQty * $existingPivot->pivot->selling_price;
                    if ($discount > $maxDiscount) {
                        $discount = $maxDiscount;
                    }
                    
                    if ($bill->is_damaged) {
                        $discount = $newQty * $existingPivot->pivot->selling_price;
                    }

                    // Update the pivot
                    $bill->products()->updateExistingPivot($productId, [
                        'quantity' => $newQty,
                        'discount' => $discount,
                    ]);
            } else {
                // NEW PRODUCT - Allow adding even if no stock (overselling)
                
                // Update product stock (can go negative)
                $product->quantity -= $quantity;
                $product->save();

                // Handle batch consumption (allow negative)
                $this->consumeProductStockAllowNegative($product, $quantity);

                // Set discount
                $maxDiscount = $quantity * $product->selling_price;
                if ($discount > $maxDiscount) {
                    $discount = $maxDiscount;
                }
                
                if ($bill->is_damaged) {
                    $discount = $quantity * $product->selling_price;
                }

                // Attach new product
                $bill->products()->attach($productId, [
                    'quantity' => $quantity,
                    'discount' => $discount,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                ]);
            }
        }
    }

    // Remove products
    $toRemove = $request->input('remove_products', []);
    if (!empty($toRemove)) {
        foreach ($toRemove as $productId) {
            $product = Product::findOrFail($productId);
            $billProduct = $bill->products()->where('product_id', $productId)->first();
            
            if (!$billProduct) {
                continue;
            }
            
            $pivot = $billProduct->pivot;

            // Return quantity to stock
            $product->quantity += $pivot->quantity;
            
            // FIXED: Proper weighted average cost price calculation
            $oldTotalValue = $product->cost_price * ($product->quantity - $pivot->quantity);
            $returnValue = $pivot->cost_price * $pivot->quantity;
            $newTotalQuantity = $product->quantity;
            
            if ($newTotalQuantity > 0) {
                $product->cost_price = ($oldTotalValue + $returnValue) / $newTotalQuantity;
                $product->cost_price = round($product->cost_price, 2);
            }
            
            $product->save();

            // Return to batch
            $this->returnToBatch($product, $pivot->quantity, $pivot->cost_price, $ownerId);
        }

        $bill->products()->detach($toRemove);
    }

    // Add new product (from dropdown)
    $newProductId = $request->input('new_product_id');
    $newQty = (int)$request->input('new_quantity');

    if ($newProductId && $newQty > 0) { // Must be positive for bill
        $product = Product::findOrFail($newProductId);
        
        // ALLOW OVERSELLING - No stock validation
        // Update product stock (can go negative)
        $product->quantity -= $newQty;
        $product->save();

        // Handle batch consumption (allow negative)
        $this->consumeProductStockAllowNegative($product, $newQty);

        $discount = $bill->is_damaged ? ($newQty * $product->selling_price) : 0;

        $bill->products()->syncWithoutDetaching([
            $newProductId => [
                'quantity' => $newQty,
                'discount' => $discount,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
            ]
        ]);
    }

    // Recalculate total price
    $this->recalculateBillTotal($bill);

    // Update customer balance if needed
    $this->updateCustomerBalance($bill);

    return redirect()->route('bills.show', $bill->id)->with('success', 'Bill updated successfully!');
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
        $qty = $product->pivot->quantity; // Always positive in bills
        $unitPrice = $product->pivot->selling_price;
        $discount = $product->pivot->discount ?? 0;
        
        // Calculate subtotal
        $subtotal = max(0, ($qty * $unitPrice) - $discount);
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
     * Search bills by various criteria
     */
    public function search(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        
        $query = Bill::where('user_id', $ownerId)
            ->with(['products', 'customer', 'creator']);
            
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->filled('min_amount')) {
            $query->where('total_price', '>=', $request->min_amount);
        }
        
        if ($request->filled('max_amount')) {
            $query->where('total_price', '<=', $request->max_amount);
        }
        
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        $bills = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return response()->json([
            'bills' => $bills->items(),
            'pagination' => [
                'current_page' => $bills->currentPage(),
                'last_page' => $bills->lastPage(),
                'total' => $bills->total(),
            ]
        ]);
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
        
        $products = Product::where('user_id', $ownerId)->get();
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