@foreach($products as $product)
    @php
        $images = is_array($product->pictures) ? $product->pictures : json_decode($product->pictures, true);
        $firstImage = $images[0] ?? null;
    @endphp
    <tr class="bg-gray-200 product-row" data-id="{{ $product->id }}">
        <td class="py-3 px-6 border-b text-left">{{ $product->id }}</td>
        <td class="py-3 px-6 border-b text-left">{{ $product->name }}</td>
        <td class="py-3 px-6 border-b text-left">
            @if ($firstImage)
                <img src="{{ asset('storage/' . $firstImage) }}" alt="{{ $product->name }}" class="h-16 w-16 object-cover">
            @else
                <span>No image</span>
            @endif
        </td>
        <td class="py-3 px-6 border-b text-left quantity-cell">{{ $product->quantity }}</td>
        <td class="py-3 px-6 border-b text-left">{{ $product->cost_price }}</td>
        <td class="py-3 px-6 border-b text-left">{{ $product->selling_price }}</td>
        <td class="py-3 px-6 border-b text-left">
            <div class="flex gap-2 items-center">
                <input type="number" min="1" value="1" class="w-16 px-2 py-1 border rounded quantity-input">
                <button
                    type="button"
                    class="bg-green-500 text-white px-2 py-1 rounded add-quantity-btn"
                    data-product-id="{{ $product->id }}">
                    Add
                </button>
            </div>
        </td>
        <td class="py-3 px-6 border-b text-left">
            <a href="{{ route('products.edit', $product->id) }}" class="text-yellow-500 hover:text-yellow-700">Edit</a>
            <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="inline delete-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-500 hover:text-red-700">Delete</button>
            </form>
        </td>
    </tr>
@endforeach
