<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Bill') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <form method="POST" action="{{ route('bills.store') }}">
                        @csrf

                        {{-- Note --}}
                        <div class="mb-4">
                            <label for="note" class="block font-medium text-sm text-gray-700">Note</label>
                            <textarea name="note" id="note" rows="3" class="form-textarea mt-1 block w-full border rounded px-3 py-2"></textarea>
                        </div>

                        {{-- Barcode input --}}
                        <div class="mb-6">
                            <label for="barcode_input" class="block font-medium text-sm text-gray-700">Scan Barcode</label>
                            <input type="text" id="barcode_input" placeholder="Enter barcode..." class="w-1/3 px-3 py-2 border rounded">
                        </div>

                        {{-- Product rows --}}
                        <div id="products-list"></div>

                        <button type="button" id="add-product-row" class="bg-blue-500 text-white px-4 py-2 rounded mb-4">+ Add Product Manually</button>

                        {{-- Total --}}
                        <div class="mb-4">
                            <label for="total_price" class="block">Total Price</label>
                            <input type="number" name="total_price" id="total_price" class="form-input px-3 py-2 border rounded w-full" readonly>
                        </div>

                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Create Bill</button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        const products = @json($productsForJS);
        const productsList = document.getElementById('products-list');
        const barcodeInput = document.getElementById('barcode_input');

        barcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = barcodeInput.value.trim();
                const product = products.find(p => p.barcode === code);
                if (product) {
                    addProductRow(product);
                    barcodeInput.value = '';
                } else {
                    alert('Product not found for barcode: ' + code);
                }
            }
        });

        document.getElementById('add-product-row').addEventListener('click', () => {
            addProductRow();
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-row')) {
                e.target.closest('.product-row').remove();
                calculateTotal();
            }
        });

        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('discount') || e.target.classList.contains('product-select')) {
                calculateTotal();
            }
        });

        function addProductRow(product = null) {
            const row = document.createElement('div');
            row.className = 'product-row flex flex-wrap gap-2 mb-4 items-end';

            console.log('Adding product row:', product);
            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${product?.id ?? ''}">
                <input type="hidden" name="cost_prices[]" value="${product?.cost_price ?? ''}">
                
                <input type="hidden" name="selling_prices[]" value="${product?.price ?? ''}">

                <div class="flex-1">
                    <label class="text-sm">Product</label>
                    <select name="product_ids[]" class="form-select w-full px-3 py-2 border rounded product-select" ${product ? 'disabled' : ''} required>
                        <option value="">Select Product</option>
                        ${products.map(p => `
                            <option value="${p.id}" ${product && p.id === product.id ? 'selected' : ''}>
                                ${p.name} (${p.price})
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="w-24">
                    <label class="text-sm">Qty</label>
                    <input type="number" name="quantities[]" class="form-input w-full px-2 py-1 border rounded quantity" min="1" value="1" required>
                </div>
                <div class="w-32">
                    <label class="text-sm">Discount</label>
                    <input type="number" name="discounts[]" class="form-input w-full px-2 py-1 border rounded discount" min="0" value="0" required>
                </div>
                <button type="button" class="remove-row bg-red-500 text-white px-2 py-1 rounded h-9">Remove</button>
            `;

            productsList.appendChild(row);
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const select = row.querySelector('.product-select');
                const quantity = parseFloat(row.querySelector('.quantity')?.value || 0);
                const discount = parseFloat(row.querySelector('.discount')?.value || 0);
                let price = 0;
                const selectedOption = select?.options[select.selectedIndex];
                if (selectedOption) {
                    const match = selectedOption.textContent.match(/\(([^)]+)\)/);
                    price = parseFloat(match?.[1] || 0);
                }
                total += (price * quantity) - discount;
            });
            document.getElementById('total_price').value = total.toFixed(2);
        }
    </script>
</x-app-layout>
