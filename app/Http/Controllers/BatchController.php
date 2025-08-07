<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Store a new batch for a product
     */
    public function store(Request $request)
{
    $data = $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity' => 'required|integer|min:1',
        'cost_price' => 'required|numeric|min:0',
    ]);

    $product = Product::findOrFail($data['product_id']);
    $oldQty = $product->quantity;
    $oldAvg = $product->cost_price;

    // Check if a batch with the same cost price already exists for this product
    $existingBatch = Batch::where('product_id', $data['product_id'])
        ->where('cost_price', $data['cost_price'])
        ->orderBy('created_at', 'asc') // optional: you may want the oldest
        ->first();

    if ($existingBatch) {
        // Increase existing batch quantity
        $existingBatch->quantity += $data['quantity'];
        $existingBatch->save();
        $batch = $existingBatch;
    } else {
        // Create new batch
        $batch = Batch::create($data);
    }

    // Update total quantity
    $product->quantity = $oldQty + $data['quantity'];

    if ($oldQty <= 0) {
        $product->cost_price = $data['cost_price'];
    } else {
        // Recalculate average cost (your formula)
        $product->cost_price =
            ($oldAvg * $oldQty + $data['cost_price'] * $data['quantity']) /
            max(1, ($oldQty + $data['quantity']));
    }
    $product->cost_price = round($product->cost_price, 2);
    $product->save();

    return response()->json([
        'success' => true,
        'batch' => $batch,
        'updated_quantity' => $product->quantity,
    ]);
}

    /**
     * Update an existing batch (only affects average if quantity increased)
     */
    public function update(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        $product = $batch->product;
        $oldQty = $batch->quantity;
        $oldProductQty = $product->quantity;
        $oldProductAvg = $product->cost_price;

        $batch->update($data);
        $diff = $batch->quantity - $oldQty;

        // Update product quantity
        $product->quantity += $diff;

        if($oldProductQty <= 0 && $diff > 0){
            // If product was empty and now has a batch, set cost price to new batch
            $product->cost_price = $batch->cost_price;
        }
        else{
        $product->cost_price =
            ($oldProductAvg * $oldProductQty + $batch->cost_price * $diff)
            / max(1, ($oldProductQty + $diff));
    }

        $product->cost_price = round($product->cost_price, 2);

        $product->save();

        return response()->json(['success' => true]);
    }

    /**
     * Delete a batch (quantity decreases but average cost does NOT change)
     */
    public function destroy(Batch $batch)
    {
        $product = $batch->product;

        $oldProductQty = $product->quantity;
        $oldProductAvg = $product->cost_price;
        // Update product quantity
        $product->quantity -= $batch->quantity;

        if($oldProductQty > 0){
            $product->cost_price =
                ($oldProductAvg * $oldProductQty - $batch->cost_price * $batch->quantity)
                / max(1, ($oldProductQty - $batch->quantity));
        }
        $product->cost_price = round($product->cost_price, 2);
        $product->save();
        $batch->delete();
        $product->save();

        return response()->json(['success' => true]);
    }
}
