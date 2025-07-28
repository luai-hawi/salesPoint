<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>

    <div class="py-12 mx-6">
        {{-- Search and Add New Product --}}
        <div class="flex justify-between items-center mb-4">
            <input type="text" id="product-search" name="search" placeholder="Search name, barcode, price..." class="px-4 py-2 border rounded w-64" value="{{ request('search') }}">
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
                <tbody id="products-table-body">
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
                <button type="button" class="bg-green-500 text-white px-2 py-1 rounded add-btn">Add</button>
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
                </tbody>
            </table>
        </div>

        <div id="pagination-links" class="mt-4 flex justify-center">
    {{ $products->links('vendor.pagination.custom-light') }}
</div>

    </div>

<script>
    const typingDelay = 500;
    let typingTimer;
    const searchInput = document.getElementById('product-search');
    const productsTableBody = document.getElementById('products-table-body');
    const paginationLinks = document.getElementById('pagination-links');

    function loadProducts(url) {
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newTbody = doc.querySelector('#products-table-body');
            const newPagination = doc.querySelector('#pagination-links');

            if(newTbody) productsTableBody.innerHTML = newTbody.innerHTML;
            if(newPagination) paginationLinks.innerHTML = newPagination.innerHTML;

            attachPaginationLinks();
            attachAddButtons();
        })
        .catch(console.error);
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            const query = searchInput.value.trim();
            const url = new URL('{{ route('products.index') }}', window.location.origin);
            if(query.length > 0) {
                url.searchParams.set('search', query);
            } else {
                url.searchParams.delete('search');
            }
            loadProducts(url.toString());
        }, typingDelay);
    });


     function attachDeleteConfirmation() {
    document.addEventListener('DOMContentLoaded', () => {
  document.body.addEventListener('submit', function(e) {
    const form = e.target;
    if (form.classList.contains('delete-form')) {
      e.preventDefault();
      if (confirm('Are you sure you want to delete this product?')) {
        form.submit();
      }
    }
  });
});

}


    function attachAddButtons() {
        document.querySelectorAll('.add-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('.product-row');
                const input = row.querySelector('.quantity-input');
                const qtyCell = row.querySelector('.quantity-cell');
                const addQty = parseInt(input.value, 10);

                if (isNaN(addQty) || addQty < 1) return;

                const currentQty = parseInt(qtyCell.textContent, 10);
                const newQty = currentQty + addQty;
                qtyCell.textContent = newQty;

                qtyCell.classList.add('bg-green-100');
                setTimeout(() => qtyCell.classList.remove('bg-green-100'), 800);
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
    }

    
    function attachPaginationLinks() {
        document.querySelectorAll('#pagination-links a').forEach(link => {
            link.onclick = function(e) {
                e.preventDefault();
                loadProducts(this.href);
            };
        });
    }
    
    // Call on first load
    attachPaginationLinks();
    attachDeleteConfirmation();
    attachAddButtons();

    
</script>
</x-app-layout>
