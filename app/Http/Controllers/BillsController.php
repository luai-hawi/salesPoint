<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\Request;

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
    ]);

    $bill = Bill::create([
        'note' => $request->input('note'),
        'total_price' => 0,
    ]);

    $total = 0;

    foreach ($request->product_ids as $index => $productId) {
        $qty = (int) $request->quantities[$index];
        $discount = (float) $request->discounts[$index];
        $costPrice = (float) $request->cost_prices[$index];
        $sellingPrice = (float) $request->selling_prices[$index];

        $product = \App\Models\Product::findOrFail($productId);


        $product->quantity -= $qty;
        $product->save();

        $lineTotal = ($product->selling_price * $qty) - $discount;

        $bill->products()->attach($productId, [
            'quantity' => $qty,
            'discount' => $discount,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
        ]);

        $total += $lineTotal;
    }

    $bill->update(['total_price' => $total]);

    return redirect()->route('dashboard')->with('success', 'Bill created successfully.');
}

public function update(Request $request, Bill $bill)
{
    $bill->note = $request->input('note', '');

    // Get discounts array from request (may be empty)
    $discounts = $request->input('discounts', []);

    // 1. Update existing quantities and discounts
    $quantities = $request->input('quantities', []);
    foreach ($quantities as $productId => $newQty) {
        $newQty = (int)$newQty;
        $newDiscount = isset($discounts[$productId]) ? (float)$discounts[$productId] : 0;

        // Get old pivot to adjust stock
        $pivot = $bill->products()->where('product_id', $productId)->first()->pivot;
        $oldQty = $pivot->quantity;

        // Adjust stock (return old quantity, subtract new quantity)
        $product = \App\Models\Product::findOrFail($productId);
        $product->quantity += ($oldQty - $newQty);
        $product->save();

        // Clamp discount to max allowed (qty * selling_price)
        $maxDiscount = $newQty * $product->selling_price;
        if ($newDiscount > $maxDiscount) {
            $newDiscount = $maxDiscount;
        }

        // Update pivot with quantity and discount
        $bill->products()->updateExistingPivot($productId, [
            'quantity' => $newQty,
            'discount' => $newDiscount,
        ]);
    }

    // 2. Remove products if requested
    $toRemove = $request->input('remove_products', []);
    if (!empty($toRemove)) {
        foreach ($toRemove as $productId) {
            $product = \App\Models\Product::findOrFail($productId);
            $pivot = $bill->products()->where('product_id', $productId)->first()->pivot;

            // Restore stock quantity
            $product->quantity += $pivot->quantity;
            $product->save();
        }

        $bill->products()->detach($toRemove);
    }

    // 3. Add new product (if any)
    $newProductId = $request->input('new_product_id');
    $newQty = (int)$request->input('new_quantity');

    if ($newProductId && $newQty > 0) {
        $product = \App\Models\Product::findOrFail($newProductId);

        // Decrease stock
        $product->quantity -= $newQty;
        $product->save();

        $bill->products()->syncWithoutDetaching([
            $newProductId => [
                'quantity' => $newQty,
                'discount' => 0, // default discount amount
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price
            ]
        ]);
    }

    // 4. Recalculate total price with discounts
    $total = 0;
    $bill->load('products'); // reload to get fresh pivot data

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
    // Restore product quantities before deleting the bill
    foreach ($bill->products as $product) {
        $product->quantity += $product->pivot->quantity;
        $product->save();
    }

    // Detach all product relations (optional, but clean)
    $bill->products()->detach();

    // Delete the bill
    $bill->delete();

    return redirect()->route('bills.index')->with('success', 'Bill deleted successfully and product quantities restored.');
}

}