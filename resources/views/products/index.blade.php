<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>

    <div class="py-12 mx-6">
        {{-- 🔍 Search and Add New Product --}}
        <div class="flex justify-between items-center mb-4">
            <input type="text" id="product-search" placeholder="Search name, barcode, price..." class="px-4 py-2 border rounded w-64">
            <a href="{{ route('products.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Add New Product
            </a>
        </div>

        <div class="overflow-hidden shadow-sm sm:rounded-lg">
            <table class="min-w-full table-auto" id="products-table">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-xs font-medium">
                        <th class="px-6 py-3 text-left">ID</th>
                        <th class="px-6 py-3 text-left">Name</th>
                        <th class="px-6 py-3 text-left">Pictures</th>
                        <th class="px-6 py-3 text-left">Quantity</th>
                        <th class="px-6 py-3 text-left">Cost Price</th>
                        <th class="px-6 py-3 text-left">Selling Price</th>
                        <th class="px-6 py-3 text-left">+ Quantity</th>
                        <th class="px-6 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        @php
                            $images = is_array($product->pictures) ? $product->pictures : json_decode($product->pictures, true);
                            $firstImage = $images[0] ?? null;
                        @endphp
                        <tr class="bg-gray-200 product-row"
                            data-name="{{ strtolower($product->name) }}"
                            data-barcode="{{ strtolower($product->barcode ?? '') }}"
                            data-cost="{{ $product->cost_price }}"
                            data-sell="{{ $product->selling_price }}"
                            data-id="{{ $product->id }}">
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
                                    <button type="button" class="bg-green-500 text-white px-2 py-1 rounded add-btn">Add</button>
                                </div>
                            </td>
                            <td class="py-3 px-6 border-b text-left">
                                <a href="{{ route('products.edit', $product->id) }}" class="text-yellow-500 hover:text-yellow-700">Edit</a>
                                <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Search filter
        document.getElementById('product-search').addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            document.querySelectorAll('.product-row').forEach(row => {
                const name = row.dataset.name;
                const barcode = row.dataset.barcode;
                const cost = row.dataset.cost;
                const sell = row.dataset.sell;
                const match = name.includes(term) || barcode.includes(term) || cost.includes(term) || sell.includes(term);
                row.style.display = match ? '' : 'none';
            });
        });

        // Quantity Adder
        document.querySelectorAll('.add-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('.product-row');
                const input = row.querySelector('.quantity-input');
                const qtyCell = row.querySelector('.quantity-cell');
                const addQty = parseInt(input.value, 10);

                if (isNaN(addQty) || addQty < 1) return;

                // Update cell visually
                const currentQty = parseInt(qtyCell.textContent, 10);
                const newQty = currentQty + addQty;
                qtyCell.textContent = newQty;

                // Optional: add visual feedback
                qtyCell.classList.add('bg-green-100');
                setTimeout(() => qtyCell.classList.remove('bg-green-100'), 800);

                // Reset input
                input.value = 1;

                
                
                fetch(`/products/${row.dataset.id}/add-quantity`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ amount: addQty })
                });
            });
        });
    </script>
</x-app-layout>
