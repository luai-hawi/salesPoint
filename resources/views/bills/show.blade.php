<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Bill') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <x-block>
            <form action="{{ route('bills.update', $bill->id) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Note --}}
                <div class="mb-4">
                    <label for="note" class="block font-medium text-sm text-gray-700">Note</label>
                    <textarea name="note" id="note" rows="3" class="form-textarea mt-1 block w-full border rounded px-3 py-2">{{ old('note', $bill->note) }}</textarea>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold">Customer: {{ $bill->customer->name ?? 'N/A' }}</h3>
                    <p><strong>Created:</strong> {{ $bill->created_at->format('d-m-Y H:i') }}</p>
                    <p><strong>Total Price:</strong> {{ $bill->total_price }}</p>

                </div>

                {{-- Products Table --}}
                <h4 class="font-semibold mb-2">Bill Products:</h4>
                <table id="products-table" class="min-w-full border mb-4">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 border">Product</th>
                            <th class="px-4 py-2 border">Quantity</th>
                            <th class="px-4 py-2 border">Unit Price</th>
                            <th class="px-4 py-2 border">Total</th>
                            <th class="px-4 py-2 border">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bill->products as $product)
                            <tr data-product-id="{{ $product->id }}">
                                <td class="px-4 py-2 border">{{ $product->name }}</td>
                                <td class="px-4 py-2 border">
                                    <input type="number" name="quantities[{{ $product->id }}]" value="{{ old("quantities.$product->id", $product->pivot->quantity) }}" min="1" class="w-20 px-2 py-1 border rounded quantity" required>
                                    <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
                                </td>
                                <td class="px-4 py-2 border">${{ number_format($product->pivot->selling_price, 2) }}</td>
                                <td class="px-4 py-2 border total-cell">${{ number_format($product->pivot->quantity * $product->pivot->selling_price, 2) }}</td>
                                <td class="px-4 py-2 border text-center">
                                    <input type="checkbox" name="remove_products[]" value="{{ $product->id }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Add New Product by Barcode --}}
                <h4 class="font-semibold mb-2">Add Product by Barcode:</h4>
                <div class="flex items-center space-x-4 mb-4">
                    <input type="text" id="barcode_input" placeholder="Scan or enter barcode" class="border rounded px-2 py-1 w-1/3" />
                    <button type="button" id="add_barcode_product" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Add</button>
                </div>

                {{-- Add New Product by Select --}}
                <h4 class="font-semibold mb-2">Add Product:</h4>
                <div class="flex items-center space-x-4 mb-4">
                    <select id="product_select" name="new_product_id" class="border rounded px-2 py-1 w-1/3">
                        <option value="">Select Product</option>
                        @foreach($allProducts as $prod)
                            <option value="{{ $prod->id }}">{{ $prod->name }} (${{ number_format($prod->selling_price, 2) }})</option>
                        @endforeach
                    </select>
                    <input type="number" id="new_quantity" name="new_quantity" class="w-20 border rounded px-2 py-1" min="1" placeholder="Qty">
                </div>

                {{-- Submit --}}
                <div class="flex space-x-4">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save Changes</button>
                    <a href="{{ route('bills.index') }}" class="bg-gray-300 text-black px-4 py-2 rounded hover:bg-gray-400">Cancel</a>
                </div>
            </form>
        </x-block>
    </div>

    <script>
        const products = @json($productsForJS);
        const barcodeInput = document.getElementById('barcode_input');
        const addBarcodeBtn = document.getElementById('add_barcode_product');
        const productSelect = document.getElementById('product_select');
        const newQuantityInput = document.getElementById('new_quantity');
        const productsTableBody = document.querySelector('#products-table tbody');

        function formatPrice(num) {
            return num.toFixed(2);
        }

        function isProductInTable(productId) {
            return !!productsTableBody.querySelector(`tr[data-product-id="${productId}"]`);
        }

        function addProductRow(product) {
            if (isProductInTable(product.id)) {
                alert('Product already added');
                return;
            }

            const tr = document.createElement('tr');
            tr.setAttribute('data-product-id', product.id);

            tr.innerHTML = `
                <td class="px-4 py-2 border">${product.name}</td>
                <td class="px-4 py-2 border">
                    <input type="number" name="quantities[${product.id}]" value="1" min="1" class="w-20 px-2 py-1 border rounded quantity" required>
                    <input type="hidden" name="product_ids[]" value="${product.id}">
                </td>
                <td class="px-4 py-2 border">$${formatPrice(product.price)}</td>
                <td class="px-4 py-2 border total-cell">$${formatPrice(product.price)}</td>
                <td class="px-4 py-2 border text-center">
                    <input type="checkbox" name="remove_products[]" value="${product.id}">
                </td>
            `;

            productsTableBody.appendChild(tr);
        }

        function handleBarcodeAdd() {
            const code = barcodeInput.value.trim();
            if (!code) {
                alert('Please enter a barcode');
                return;
            }

            const product = products.find(p => p.barcode === code);
            if (!product) {
                alert('Product not found for barcode: ' + code);
                return;
            }

            // Set in picker and quantity input
            productSelect.value = product.id;
            newQuantityInput.value = 1;
            barcodeInput.value = '';
        }

        addBarcodeBtn.addEventListener('click', handleBarcodeAdd);

        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleBarcodeAdd();
            }
        });

        // Recalculate totals
        document.querySelector('#products-table').addEventListener('input', e => {
            if (e.target.classList.contains('quantity')) {
                const tr = e.target.closest('tr');
                const qty = parseInt(e.target.value) || 0;
                const price = parseFloat(tr.children[2].textContent.replace('$','')) || 0;
                const totalCell = tr.querySelector('.total-cell');
                totalCell.textContent = '$' + formatPrice(price * qty);
            }
        });
    </script>
</x-app-layout>
