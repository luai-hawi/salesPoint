<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12 flex flex-row space-x-6">
        {{-- LEFT: Product Picker --}}
        <x-block class="w-2/5">
            <div class="mb-4">
                <input type="text" id="product-search" placeholder="Search products..." class="w-full px-3 py-2 border rounded" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                @foreach($productsForJS as $product)
                    @if(empty($product['barcode']))
                        @php
                            $images = $product['pictures'];
                            if (is_string($images)) {
                                $images = json_decode($images, true);
                            }
                            $firstImage = $images[0] ?? null;
                        @endphp

                        <div class="bg-white p-2 border rounded shadow text-center cursor-pointer product-card"
                             data-product-id="{{ $product['id'] }}"
                             data-name="{{ strtolower($product['name']) }}">
                            @if ($firstImage)
                                <img src="{{ asset('storage/' . $firstImage) }}" alt="{{ $product['name'] }}" class="w-full h-32 object-contain mb-2">
                            @else
                                <span class="block h-32 flex items-center justify-center text-gray-400">No image</span>
                            @endif
                            <div class="text-sm font-medium">{{ $product['name'] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </x-block>

        {{-- RIGHT: Bill Creation --}}
        <x-block class="w-full">
            <x-slot name="header">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Create Bill') }}
                </h2>
            </x-slot>

            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <form id="create-bill" method="POST" action="{{ route('bills.store') }}">
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

                                
                                <div class="flex gap-4">
                                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Create Bill</button>
                                    <button type="button" id="clear-all" class="bg-red-500 text-white px-4 py-2 rounded">Clear All</button>
                                    <button type="button" id="print-button" class="bg-gray-500 text-white px-4 py-2 rounded">Print</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            
        </x-block>
        {{-- Total Sales Today --}}
        <x-block>
            {{-- Totals --}}
            <div class="grid grid-cols-1 gap-4 mb-4">
                <div>
                    <label for="total_discount" class="block">Total Discount</label>
                    <input type="number" id="total_discount" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
                <div>
                    <label for="total_price" class="block">Total Price</label>
                    <input type="number" name="total_price" id="total_price" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
                <div class="mb-6">
                    <label for="total_sales_today" class="block">Total Sales Today:</label>
                    <input type="number" name="total_sales_today" id="total_sales_today" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
            </div>
        </x-block>
    </div>

    {{-- JavaScript --}}
    <script>
        const totalSalesToday = {{ $totalToday ?? 0 }};
        document.getElementById('total_sales_today').value = totalSalesToday.toFixed(2);
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

            if (e.target.closest('.product-card')) {
                const productId = parseInt(e.target.closest('.product-card').dataset.productId);
                const product = products.find(p => p.id === productId);
                if (product) addProductRow(product);
            }
        });

        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('discount') || e.target.classList.contains('product-select')) {
                calculateTotal();
            }
        });

        document.getElementById('clear-all').addEventListener('click', () => {
            document.querySelectorAll('.product-row').forEach(row => row.remove());
            calculateTotal();
        });

        function addProductRow(product = null) {
            const row = document.createElement('div');
            row.className = 'product-row flex flex-wrap gap-2 mb-4 items-end';

            const productId = product?.id ?? '';
            const costPrice = product?.cost_price ?? '';
            const sellingPrice = product?.price ?? '';

            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${productId}">
                <input type="hidden" name="cost_prices[]" value="${costPrice}">
                <input type="hidden" name="selling_prices[]" value="${sellingPrice}">

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
            let totalDiscount = 0;
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
                totalDiscount += discount;
            });
            document.getElementById('total_price').value = total.toFixed(2);
            document.getElementById('total_discount').value = totalDiscount.toFixed(2);
            const totalSalesToday2 = {{ $totalToday ?? 0 }};
            document.getElementById('total_sales_today').value = totalSalesToday2.toFixed(2);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'F2') {
                e.preventDefault();
                const form = document.getElementById('create-bill');
                if (form) form.submit();
            }
        });

        // Product name live search
        document.getElementById('product-search').addEventListener('input', function () {
            const searchTerm = this.value.trim().toLowerCase();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.name || '';
                card.style.display = name.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>

    {{-- Printable Section --}}
    <div id="print-area" class="print-hidden p-6 text-sm">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">Bee Phone</h1>
            <p>{{ now()->format('Y-m-d H:i') }}</p>
            <hr class="my-2">
        </div>

        <table class="min-w-full border-collapse border border-gray-400 text-sm w-full" id="print-products-table">
            <thead>
                <tr>
                    <th class="border border-gray-400 px-2 py-1 text-left">Product</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Quantity</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Unit Price</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Discount</th>
                    <th class="border border-gray-400 px-2 py-1 text-right">Total</th>
                </tr>
            </thead>
            <tbody id="print-products-list">
                {{-- JS will fill this --}}
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="border border-gray-400 px-2 py-1 font-semibold text-right">Totals</td>
                    <td id="print-total-discount" class="border border-gray-400 px-2 py-1 text-right font-semibold">0.00₪</td>
                    <td id="print-total-price" class="border border-gray-400 px-2 py-1 text-right font-semibold">0.00₪</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Print Styles --}}
    <style>
        .print-hidden {
            display: none;
        }

        @media print {
            body * {
                visibility: hidden !important;
            }

            #print-area, #print-area * {
                visibility: visible !important;
            }

            #print-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                background: white;
                font-family: sans-serif;
                padding: 20px;
                color: black;
                display: block !important;
            }
        }
    </style>

    {{-- Print Script --}}
    <script>
        document.getElementById('print-button').addEventListener('click', () => {
            const printList = document.getElementById('print-products-list');
            printList.innerHTML = ''; // clear previous

            let totalPrice = 0;
            let totalDiscount = 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const select = row.querySelector('.product-select');
                const selectedOption = select?.options[select.selectedIndex];
                const productName = selectedOption?.textContent.split('(')[0]?.trim() || 'Unknown';
                const quantity = parseFloat(row.querySelector('.quantity')?.value || 0);
                const discount = parseFloat(row.querySelector('.discount')?.value || 0);
                const priceMatch = selectedOption?.textContent.match(/\(([^)]+)\)/);
                const price = parseFloat(priceMatch?.[1] || 0);
                const subtotal = (price * quantity) - discount;

                totalPrice += subtotal;
                totalDiscount += discount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border border-gray-400 px-2 py-1">${productName}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${quantity}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${price.toFixed(2)}₪</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${discount.toFixed(2)}₪</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${subtotal.toFixed(2)}₪</td>
                `;
                printList.appendChild(tr);
            });

            document.getElementById('print-total-price').textContent = totalPrice.toFixed(2) + '₪';
            document.getElementById('print-total-discount').textContent = totalDiscount.toFixed(2) + '₪';

            window.print();
        });
        window.addEventListener('DOMContentLoaded', () => {
    const barcodeInput = document.getElementById('barcode_input');
    if (barcodeInput) {
        barcodeInput.focus();
    }
});

    </script>
</x-app-layout>
