<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBill;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Supplier;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseBillController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_purchase_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $query = PurchaseBill::where('user_id', $ownerId)
            ->with(['supplier', 'creator', 'products']);

        // Date filter
        if ($request->filled('date')) {
            $query->whereDate('purchase_date', $request->date);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        // Supplier filter
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $bills = $query->orderBy('purchase_date', 'desc')->paginate(20);

        // Get suppliers for filter dropdown
        $suppliers = Supplier::where('user_id', $ownerId)->orderBy('name')->get();

        // Calculate totals for current page results
        $totalAmount = $bills->sum('total_amount');

        return view('purchase-bills.index', compact('bills', 'suppliers', 'totalAmount'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('create_purchase_bills')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $suppliers = Supplier::where('user_id', $ownerId)->orderBy('name')->get();
        $products = Product::where('user_id', $ownerId)->orderBy('name')->get();

        // Handle duplication
        $duplicatedBill = null;
        if ($request->filled('duplicate')) {
            $duplicatedBill = PurchaseBill::where('id', $request->duplicate)
                ->where('user_id', $ownerId)
                ->with(['supplier', 'products'])
                ->first();
        }

        return view('purchase-bills.create', compact('suppliers', 'products', 'duplicatedBill'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('PurchaseBillController store request:', $request->all());

            $user = auth()->user();
            if ($user->role === 'employee' && !$user->hasPermission('create_purchase_bills')) {
                abort(403, 'Unauthorized');
            }

            $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

            $request->validate([
                'supplier_id' => 'required|exists:suppliers,id,user_id,' . $ownerId,
                'purchase_date' => 'required|date',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|exists:products,id,user_id,' . $ownerId,
                'quantities' => 'required|array',
                'quantities.*' => 'required|numeric|min:0.01',
                'unit_costs' => 'required|array',
                'unit_costs.*' => 'required|numeric|min:0',
                'barcodes' => 'nullable|array',
                'barcodes.*' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $purchaseBill = PurchaseBill::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => $ownerId,
                'purchase_date' => $request->purchase_date,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'total_amount' => 0, // Will be calculated
                'created_by' => $user->id,
            ]);

            $totalAmount = 0;

            foreach ($request->product_ids as $index => $productId) {
                $quantity = (float) $request->quantities[$index];
                $unitCost = (float) $request->unit_costs[$index];
                $barcodes = $request->input("barcodes_{$productId}", []);
                $totalCost = $quantity * $unitCost;

                $totalAmount += $totalCost;

                // Attach product to purchase bill with barcodes
                $purchaseBill->products()->attach($productId, [
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'barcodes' => json_encode($barcodes),
                ]);

                // Add barcodes to product's barcode collection
                if (!empty($barcodes)) {
                    foreach ($barcodes as $barcode) {
                        if (!empty(trim($barcode))) {
                            // Check if barcode already exists for this product
                            $exists = ProductBarcode::where('product_id', $productId)
                                ->where('barcode', trim($barcode))
                                ->exists();

                            if (!$exists) {
                                ProductBarcode::create([
                                    'product_id' => $productId,
                                    'barcode' => trim($barcode),
                                ]);
                            }
                        }
                    }
                }

                // Update product stock and average cost
                $product = Product::where('id', $productId)
                    ->where('user_id', $ownerId)
                    ->firstOrFail();

                $this->addToStorage($product, $quantity, $unitCost, $ownerId);

                Log::info('Added product to purchase bill:', [
                    'product_id' => $productId,
                    'added_quantity' => $quantity,
                    'added_cost' => $unitCost,
                    'new_quantity' => $product->fresh()->quantity,
                    'new_avg_cost' => $product->fresh()->cost_price
                ]);
            }

            // Update total amount
            $purchaseBill->total_amount = $totalAmount;
            $purchaseBill->save();

            // Update supplier balance
            $supplier = $purchaseBill->supplier;
            $supplier->balance += $totalAmount;
            $supplier->save();

            DB::commit();

            Log::info('Purchase bill created successfully:', [
                'bill_id' => $purchaseBill->id,
                'total_amount' => $totalAmount
            ]);

            return redirect()->route('purchase-bills.index')
                ->with('success', 'Purchase bill created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation error in purchase bill creation:', $e->errors());
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating purchase bill:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create purchase bill: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show(PurchaseBill $purchaseBill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('view_purchase_bills')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizePurchaseBill($purchaseBill);

        $purchaseBill->load(['supplier', 'products', 'creator']);

        return view('purchase-bills.show', compact('purchaseBill'));
    }

    public function edit(PurchaseBill $purchaseBill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('edit_purchase_bills')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizePurchaseBill($purchaseBill);
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $suppliers = Supplier::where('user_id', $ownerId)->orderBy('name')->get();
        $products = Product::where('user_id', $ownerId)->orderBy('name')->get();

        $purchaseBill->load(['supplier', 'products']);

        return view('purchase-bills.edit', compact('purchaseBill', 'suppliers', 'products'));
    }

    public function update(Request $request, PurchaseBill $purchaseBill)
    {
        $user = auth()->user();
        if ($user->role === 'employee' && !$user->hasPermission('edit_purchase_bills')) {
            abort(403, 'Unauthorized');
        }

        $this->authorizePurchaseBill($purchaseBill);
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id,user_id,' . $ownerId,
            'purchase_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:products,id,user_id,' . $ownerId,
            'quantities' => 'required|array',
            'quantities.*' => 'required|numeric|min:0.01',
            'unit_costs' => 'required|array',
            'unit_costs.*' => 'required|numeric|min:0',
            'barcodes' => 'nullable|array',
            'barcodes.*' => 'nullable|string',
        ]);

        DB::beginTransaction();

        // Store old data for reversal
        $oldTotalAmount = $purchaseBill->total_amount;
        $oldSupplierId = $purchaseBill->supplier_id;
        $oldProducts = $purchaseBill->products()->get();



        // Step 1: Reverse all old stock changes (REMOVING FROM STORAGE - affects average cost)
        foreach ($oldProducts as $product) {
            $quantity = $product->pivot->quantity;
            $unitCost = $product->pivot->unit_cost;

            $this->removeFromStorage($product, $quantity, $unitCost, $ownerId);
        }

        // Step 2: Update supplier balance from old bill
        if ($oldSupplierId) {
            $oldSupplier = Supplier::find($oldSupplierId);
            if ($oldSupplier) {
                $oldSupplier->balance -= $oldTotalAmount;
                $oldSupplier->save();
            }
        }

        // Step 3: Detach old products
        $purchaseBill->products()->detach();

        // Step 4: Update purchase bill basic info
        $purchaseBill->update([
            'supplier_id' => $request->supplier_id,
            'purchase_date' => $request->purchase_date,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
        ]);

        $totalAmount = 0;

        // Step 5: Process new products (ADDING TO STORAGE - affects average cost)
        foreach ($request->product_ids as $index => $productId) {
            $quantity = (float) $request->quantities[$index];
            $unitCost = (float) $request->unit_costs[$index];
            $barcodes = $request->input("barcodes_{$productId}", []);
            $totalCost = $quantity * $unitCost;

            $totalAmount += $totalCost;
            // Attach product to purchase bill
            $purchaseBill->products()->attach($productId, [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'barcodes' => json_encode($barcodes),
            ]);


            // Add new barcodes to product's barcode collection
            if (!empty($barcodes)) {
                foreach ($barcodes as $barcode) {
                    if (!empty(trim($barcode))) {
                        // Check if barcode already exists for this product
                        $exists = ProductBarcode::where('product_id', $productId)
                            ->where('barcode', trim($barcode))
                            ->exists();

                        if (!$exists) {
                            ProductBarcode::create([
                                'product_id' => $productId,
                                'barcode' => trim($barcode),
                            ]);
                        }
                    }
                }
            }


            // Update product stock and average cost (ADDING TO STORAGE)
            $product = Product::where('id', $productId)
                ->where('user_id', $ownerId)
                ->firstOrFail();

            $this->addToStorage($product, $quantity, $unitCost, $ownerId);
        }

        // Step 6: Update total amount
        $purchaseBill->total_amount = $totalAmount;
        $purchaseBill->save();

        // Step 7: Update new supplier balance
        $newSupplier = Supplier::where('id', $request->supplier_id)
            ->where('user_id', $ownerId)
            ->firstOrFail();
        $newSupplier->balance += $totalAmount;
        $newSupplier->save();

        DB::commit();

        return redirect()->route('purchase-bills.show', $purchaseBill)
            ->with('success', 'Purchase bill updated successfully!');
    }

    public function destroy(PurchaseBill $purchaseBill)
    {
        try {
            Log::info('PurchaseBillController destroy request:', ['bill_id' => $purchaseBill->id]);

            $user = auth()->user();
            if ($user->role === 'employee' && !$user->hasPermission('delete_purchase_bills')) {
                abort(403, 'Unauthorized');
            }

            $this->authorizePurchaseBill($purchaseBill);

            $user = auth()->user();
            $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;

            DB::beginTransaction();

            // Reverse stock changes (REMOVING FROM STORAGE - affects average cost)
            foreach ($purchaseBill->products as $product) {
                $quantity = $product->pivot->quantity;
                $unitCost = $product->pivot->unit_cost;

                $this->removeFromStorage($product, $quantity, $unitCost, $ownerId);

                Log::info('Reversed product from deleted purchase bill:', [
                    'product_id' => $product->id,
                    'removed_quantity' => $quantity,
                    'removed_cost' => $unitCost,
                    'new_quantity' => $product->fresh()->quantity,
                    'new_avg_cost' => $product->fresh()->cost_price
                ]);
            }

            // Update supplier balance
            $supplier = $purchaseBill->supplier;
            $supplier->balance -= $purchaseBill->total_amount;
            $supplier->save();

            // Store bill ID for logging
            $billId = $purchaseBill->id;
            $totalAmount = $purchaseBill->total_amount;

            // Delete the purchase bill (cascade will handle products)
            $purchaseBill->delete();

            DB::commit();

            Log::info('Purchase bill deleted successfully:', [
                'bill_id' => $billId,
                'reversed_amount' => $totalAmount
            ]);

            return redirect()->route('purchase-bills.index')
                ->with('success', 'Purchase bill deleted successfully! All stock changes have been reversed.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting purchase bill:', [
                'bill_id' => $purchaseBill->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()
                ->with('error', 'Failed to delete purchase bill: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to add products to storage (affects average cost)
     * Used when: creating purchase bill, updating purchase bill (adding products)
     */
    private function addToStorage($product, $quantity, $unitCost, $ownerId)
    {
        $oldQty = $product->quantity;
        $oldAvgCost = $product->cost_price;

        // Update product quantity
        $product->quantity += $quantity;

        // Update average cost price using weighted average (PURCHASE LOGIC)
        if ($oldQty <= 0) {
            $product->cost_price = $unitCost;
        } else {
            $product->cost_price = ($oldAvgCost * $oldQty + $unitCost * $quantity) / $product->quantity;
        }

        $product->cost_price = round($product->cost_price, 2);
        $product->save();

        // Create or update batch
        $existingBatch = Batch::where('product_id', $product->id)
            ->where('cost_price', $unitCost)
            ->where('user_id', $ownerId)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($existingBatch) {
            $existingBatch->quantity += $quantity;
            $existingBatch->save();
        } else {
            Batch::create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'cost_price' => $unitCost,
                'user_id' => $ownerId,
            ]);
        }
    }

    /**
     * Helper method to remove products from storage (affects average cost)
     * Used when: updating purchase bill (removing products), deleting purchase bill
     */
    private function removeFromStorage($product, $quantity, $unitCost, $ownerId)
    {
        $previousQuantity = $product->quantity;
        $previousAvgCost = $product->cost_price;

        // Update product quantity (removing from storage)
        $product->quantity -= $quantity;

        // Recalculate average cost when removing from storage (PURCHASE LOGIC)
        if ($product->quantity <= 0) {
            $product->cost_price = 0;
        } else {
            // Calculate what the total cost was before this removal
            $totalCostBefore = $previousAvgCost * $previousQuantity;

            // Calculate the cost being removed
            $removedTotalCost = $unitCost * $quantity;

            // Calculate remaining cost and new average
            $remainingTotalCost = $totalCostBefore - $removedTotalCost;

            if ($remainingTotalCost > 0) {
                $product->cost_price = $remainingTotalCost / $product->quantity;
            } else {
                // Fallback to batch-based calculation
                $batches = $product->batches()->where('quantity', '>', 0)->get();
                if ($batches->count() > 0) {
                    $totalCost = 0;
                    $totalQty = 0;
                    foreach ($batches as $batch) {
                        $totalCost += $batch->cost_price * $batch->quantity;
                        $totalQty += $batch->quantity;
                    }
                    $product->cost_price = $totalQty > 0 ? $totalCost / $totalQty : 0;
                } else {
                    $product->cost_price = 0;
                }
            }
        }

        $product->cost_price = round($product->cost_price, 2);
        $product->save();

        // Remove from batches
        $batch = $product->batches()
            ->where('cost_price', $unitCost)
            ->where('user_id', $ownerId)
            ->first();

        if ($batch) {
            if ($batch->quantity <= $quantity) {
                $batch->delete();
            } else {
                $batch->quantity -= $quantity;
                $batch->save();
            }
        }
    }

    /**
     * Helper to ensure purchase bill belongs to the current user
     */
    private function authorizePurchaseBill(PurchaseBill $purchaseBill)
    {
        $user = auth()->user();
        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
        if ($purchaseBill->user_id !== $ownerId) {
            abort(403, 'Unauthorized access to purchase bill.');
        }
    }
}
