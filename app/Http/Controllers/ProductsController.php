<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index(Request $request)
{
    $query = Product::query();

    if ($search = $request->query('search')) {
        $search = strtolower($search);
        $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$search}%"])
              ->orWhere('cost_price', 'like', "%{$search}%")
              ->orWhere('selling_price', 'like', "%{$search}%");
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

    public function searchWithoutBarcode(Request $request)
    {
        $query = Product::whereNull('barcode');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->take(20)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'pictures' => $p->pictures ? json_decode($p->pictures, true) : [],
                'price' => $p->selling_price,
                'cost_price' => $p->cost_price,
                'barcode' => $p->barcode,
            ];
        });

        return response()->json($products);
    }
}