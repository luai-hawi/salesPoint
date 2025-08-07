<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Batch;

class BillsController extends Controller
{
    public function index()
    {
        $bills = Bill::orderBy('created_at', 'desc')->paginate(20);

        return view('bills.index', compact('bills'));
    }

    public function create()
    {
        $products = \App\Models\Product::all();
            // Prepare products data for JavaScript
        $productsForJS = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'barcode' => $p->barcode,
            ];
        })->toArray();
        

        return view('bills.create', compact('productsForJS', 'products'));

                
    }

    public function store(Request $request)
{
    $request->validate([
        'product_ids' => 'required|array',
        'quantities' => 'required|array',
        'discounts' => 'required|array',
        'cost_prices' => 'required|array',
        'selling_prices' => 'required|array',
        'note' => 'nullable|string',
        'customer_id' => 'nullable|exists:customers,id',
    ]);

    $bill = Bill::create([
        'note' => $request->input('note'),
        'total_price' => 0,
        'customer_id' => $request->input('customer_id'),
    ]);

    $total = 0;

    foreach ($request->product_ids as $index => $productId) {
        $qty = (int) $request->quantities[$index];
        $discount = (float) $request->discounts[$index];
        $costPrice = (float) $request->cost_prices[$index];
        $sellingPrice = (float) $request->selling_prices[$index];

        $product = \App\Models\Product::findOrFail($productId);

        // 🔻 Directly reduce total product quantity (leave this as-is)
        $product->quantity -= $qty;
        $product->save();

        // 🔁 FIFO batch deduction logic
        $remainingQty = $qty;
        $batches = $product->batches()->where('quantity', '>', 0)->orderBy('created_at')->get();

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) break;

            if ($batch->quantity >= $remainingQty) {
                $batch->quantity -= $remainingQty;
                $batch->save();
                $remainingQty = 0;
            } else {
                $remainingQty -= $batch->quantity;
                $batch->quantity = 0;
                $batch->save();
            }
        }

        // 🟥 If no batches left and some quantity remains, subtract from last batch (allow negative)
        if ($remainingQty > 0) {
            $lastBatch = $product->batches()->latest()->first();

            if ($lastBatch) {
                $lastBatch->quantity -= $remainingQty;
                $lastBatch->save();
            } else {
                // If no batch exists at all, create a negative one
                $product->batches()->create([
                    'quantity' => -1 * $remainingQty,
                    'cost_price' => $product->average_cost, // You may use another fallback if needed
                ]);
            }
        }

        // 💰 Bill line item and total
        $lineTotal = ($sellingPrice * $qty) - $discount;

        $bill->products()->attach($productId, [
            'quantity' => $qty,
            'discount' => $discount,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
        ]);

        $total += $lineTotal;
    }

    $bill->update(['total_price' => $total]);

    if ($bill->customer_id) {
        $bill->customer->payments()->create([
            'amount' => -1 * $total,
            'type' => 'payment',
            'note' => "Bill #{$bill->id} created as debt",
        ]);
        $bill->customer->update(['balance' => $bill->customer->balance + (-1 * $total)]);
    }

    return redirect()->route('dashboard')->with('success', 'Bill created successfully.');
}


public function update(Request $request, Bill $bill)
{
    $bill->note = $request->input('note', '');

    $discounts = $request->input('discounts', []);
    $quantities = $request->input('quantities', []);
    

    foreach ($quantities as $productId => $newQty) {
        $newQty = (int)$newQty;
        $newDiscount = isset($discounts[$productId]) ? (float)$discounts[$productId] : 0;

        $product = \App\Models\Product::findOrFail($productId);
        $oldQty = $product->quantity;
        $pivot = $bill->products()->where('product_id', $productId)->first()->pivot;
        $oldQty = $pivot->quantity;

        $diff = $newQty - $oldQty;

        // Update total stock
        $product->quantity -= $diff;
        $product->save();

        if ($diff > 0) {
            // More quantity in bill -> consume batches FIFO
            $remaining = $diff;
            $batches = $product->batches()->where('quantity', '>', 0)->orderBy('id')->get();
            foreach ($batches as $batch) {
                if ($remaining <= 0) break;
                $consume = min($remaining, $batch->quantity);
                $batch->quantity -= $consume;
                $remaining -= $consume;
                $batch->save();
            }
            // If still remaining, subtract from last batch (go negative)
            if ($remaining > 0) {
                $lastBatch = $product->batches()->orderByDesc('id')->first();
                if ($lastBatch) {
                    $lastBatch->quantity -= $remaining;
                    $lastBatch->save();
                }
            }
        } elseif ($diff < 0) {
            // Less quantity in bill -> return to batch with matching cost price
            $returnQty = abs($diff);
            $costPrice = $pivot->cost_price;

            // Update batch (existing or new)
            $batch = $product->batches()->where('cost_price', $costPrice)->first();
            if ($batch) {
                $batch->quantity += $returnQty;
                $batch->save();
            } else {
                $batch = $product->batches()->create([
                    'quantity' => $returnQty,
                    'cost_price' => $costPrice,
                ]);
            }

            $oldAvg = $product->cost_price;
            if($oldQty <= 0) {
                $product->cost_price = $costPrice;
            }
            else {
            // Recalculate average cost price using weighted average
            $product->cost_price = 
                ($oldAvg * $oldQty + $costPrice * $returnQty) 
                / max(1, ($oldQty + $returnQty));
            }
            $product->cost_price = round($product->cost_price, 2);
            $product->save();
        }


        $maxDiscount = $newQty * $product->selling_price;
        if ($newDiscount > $maxDiscount) {
            $newDiscount = $maxDiscount;
        }

        $bill->products()->updateExistingPivot($productId, [
            'quantity' => $newQty,
            'discount' => $newDiscount,
        ]);
    }

    $toRemove = $request->input('remove_products', []);
    if (!empty($toRemove)) {
        foreach ($toRemove as $productId) {
            $product = \App\Models\Product::findOrFail($productId);
            $pivot = $bill->products()->where('product_id', $productId)->first()->pivot;

            $product->quantity += $pivot->quantity;
            $product->save();

            // Add to batch with same cost price or create new
            $batch = $product->batches()->where('cost_price', $pivot->cost_price)->first();
            if ($batch) {
                $batch->quantity += $pivot->quantity;
                $batch->save();
            } else {
                $product->batches()->create([
                    'quantity' => $pivot->quantity,
                    'cost_price' => $pivot->cost_price,
                ]);
            }
        }

        $bill->products()->detach($toRemove);
    }

    $newProductId = $request->input('new_product_id');
    $newQty = (int)$request->input('new_quantity');

    if ($newProductId && $newQty > 0) {
        $product = \App\Models\Product::findOrFail($newProductId);
        $product->quantity -= $newQty;
        $product->save();

        // Decrease batches FIFO
        $remaining = $newQty;
        $batches = $product->batches()->where('quantity', '>', 0)->orderBy('id')->get();
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $consume = min($remaining, $batch->quantity);
            $batch->quantity -= $consume;
            $remaining -= $consume;
            $batch->save();
        }
        if ($remaining > 0) {
            $lastBatch = $product->batches()->orderByDesc('id')->first();
            if ($lastBatch) {
                $lastBatch->quantity -= $remaining;
                $lastBatch->save();
            }
        }

        $bill->products()->syncWithoutDetaching([
            $newProductId => [
                'quantity' => $newQty,
                'discount' => 0,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
            ]
        ]);
    }

    $total = 0;
    $bill->load('products');
    foreach ($bill->products as $product) {
        $qty = $product->pivot->quantity;
        $unitPrice = $product->selling_price;
        $discount = $product->pivot->discount ?? 0;
        $subtotal = max(0, $qty * $unitPrice - $discount);
        $total += $subtotal;
    }

    $bill->total_price = $total;
    $bill->save();

    return redirect()->route('bills.show', $bill->id)->with('success', 'Bill updated successfully.');
}



    public function show(Bill $bill)
    {
    $products = \App\Models\Product::select('id', 'name', 'selling_price', 'cost_price', 'barcode')->get();
    return view('bills.show', compact('bill', 'products'));
    }

    public function edit(Bill $bill)
    {
        return view('bills.edit', compact('bill'));
    }

public function destroy(Bill $bill)
{
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
            ]);
        }
        if($oldQty <= 0) {
            $product->cost_price = $costPrice;
        }
        else {
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
        if (
            \App\Models\CustomerPayment::where('customer_id', $customer->id)
                ->where('note', "Bill #{$bill->id} created as debt")
                ->delete()
        ) {
            // Increase customer balance (reduce debt)
            $customer->balance += $bill->total_price;
            $customer->save();
        }
    }

    // 4. Delete the bill
    $bill->delete();

    return redirect()->route('bills.index')->with('success', 'Bill deleted successfully, product quantities and batches restored, and customer balance updated.');
}



}