<!DOCTYPE html>
<html>
<head>
    <title>Debug Products</title>
</head>
<body>
    <h1>Debug Information</h1>

    <h2>Current User</h2>
    <p>ID: {{ $user->id }}</p>
    <p>Name: {{ $user->name }}</p>
    <p>Email: {{ $user->email }}</p>
    <p>Role: {{ $user->role }}</p>
    <p>Owner ID: {{ $ownerId }}</p>

    <h2>Out-of-Stock Products (quantity = 0, last_sale_date <= 4 months ago)</h2>
    <p>Count: {{ $products->count() }}</p>

    @if($products->count() > 0)
        <table border="1">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Quantity</th>
                <th>Active</th>
                <th>Last Sale Date</th>
                <th>Months Since Sale</th>
            </tr>
            @foreach($products as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->quantity }}</td>
                    <td>{{ $product->is_active ? 'Yes' : 'No' }}</td>
                    <td>{{ $product->last_sale_date ? \Carbon\Carbon::parse($product->last_sale_date)->format('Y-m-d') : 'Never' }}</td>
                    <td>{{ $product->last_sale_date ? now()->diffInMonths(\Carbon\Carbon::parse($product->last_sale_date)) : 'N/A' }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p>No out-of-stock products found for this user.</p>
    @endif

    <h2>All Products for This User</h2>
    @php
        $allProducts = \App\Models\Product::where('user_id', $ownerId)->get();
    @endphp
    <p>Total: {{ $allProducts->count() }}</p>

    @if($allProducts->count() > 0)
        <table border="1">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Quantity</th>
                <th>Active</th>
                <th>Last Sale Date</th>
            </tr>
            @foreach($allProducts as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->quantity }}</td>
                    <td>{{ $product->is_active ? 'Yes' : 'No' }}</td>
                    <td>{{ $product->last_sale_date ? \Carbon\Carbon::parse($product->last_sale_date)->format('Y-m-d') : 'Never' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>