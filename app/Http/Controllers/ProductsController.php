<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index()
    {
        $products = Product::all();
        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255|unique:products,barcode',
            'pictures' => 'nullable|array',
            'pictures.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
        ]);

        $product = new Product();
        $product->name = $request->name;
        $product->barcode = $request->barcode;
        $product->quantity = $request->quantity;
        $product->cost_price = $request->cost_price;
        $product->selling_price = $request->selling_price;

        if ($request->hasFile('pictures')) {
            $pictures = [];
            foreach ($request->file('pictures') as $picture) {
                $path = $picture->store('products', 'public');
                $pictures[] = $path;
            }
            $product->pictures = json_encode($pictures);
        }

        $product->save();

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        // dump($request->all());
        $request->validate([
    'name' => 'required|string|max:255',
    'barcode' => 'nullable|string|max:255|unique:products,barcode,' . $product->id,
    'pictures' => 'nullable|array',
    'pictures.*' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
    'quantity' => 'required|integer|min:0',
    'cost_price' => 'required|numeric',
    'selling_price' => 'required|numeric',
]);

        $product->name = $request->name;
        
        $product->barcode = $request->barcode;
        $product->quantity = $request->quantity;
        $product->cost_price = $request->cost_price;
        $product->selling_price = $request->selling_price;
        
        if ($request->hasFile('pictures')) {
            $pictures = [];
            foreach ($request->file('pictures') as $picture) {
                $path = $picture->store('products', 'public');
                $pictures[] = $path;
            }
            $product->pictures = json_encode($pictures);
        }

        $product->save();

        
        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function addQuantity(Request $request, Product $product)
    {
        
        $request->validate(['amount' => 'required|integer|min:1']);
        $product->increment('quantity', $request->amount);
        return response()->json(['success' => true, 'new_quantity' => $product->quantity]);
    }



    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}