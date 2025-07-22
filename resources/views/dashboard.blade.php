<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Bill') }}
        </h2>
    </x-slot>

    <div class="py-12 flex flex-row space-x-6">
        {{-- Product Selector --}}
        <x-block class="w-2/5">
            <div class="mb-4">
                <input type="text" id="product-search" placeholder="Search products..." class="w-full px-3 py-2 border rounded" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                @foreach(array_reverse($productsForJS) as $product)
                    @if(empty($product['barcode']))
                        @php
                            $images = is_string($product['pictures']) ? json_decode($product['pictures'], true) : $product['pictures'];
                            $firstImage = $images[0] ?? null;
                        @endphp

                        <div class="bg-white p-2 border rounded shadow text-center cursor-pointer product-card"
                             data-product-id="{{ $product['id'] }}"
                             data-name="{{ strtolower($product['name']) }}">
                            @if ($firstImage)
                                <img src="{{ asset('storage/' . $firstImage) }}" class="w-full h-32 object-contain mb-2">
                            @else
                                <span class="block h-32 flex items-center justify-center text-gray-400">No image</span>
                            @endif
                            <div class="text-sm font-medium">{{ $product['name'] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </x-block>

        {{-- Bill Form --}}
        <x-block class="w-full">
            <div class="p-6 bg-white border-b border-gray-200">
                <form id="create-bill" method="POST" action="{{ route('bills.store') }}">
                    @csrf

                    {{-- Note --}}
                    <div class="mb-4">
                        <label for="note" class="block font-medium text-sm text-gray-700">Note</label>
                        <textarea name="note" id="note" rows="3" class="form-textarea mt-1 block w-full border rounded px-3 py-2"></textarea>
                    </div>

                    {{-- Barcode --}}
                    <div class="mb-6">
                        <label for="barcode_input" class="block font-medium text-sm text-gray-700">Scan Barcode</label>
                        <input type="text" id="barcode_input" placeholder="Enter barcode..." class="w-1/3 px-3 py-2 border rounded">
                    </div>

                    {{-- Products --}}
                    <div id="products-list"></div>

                    <button type="button" id="add-product-row" class="bg-blue-500 text-white px-4 py-2 rounded mb-4">+ Add Product Manually</button>

                    <div class="flex gap-4">
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Create Bill</button>
                        <button type="button" id="clear-all" class="bg-red-500 text-white px-4 py-2 rounded">Clear All</button>
                        <button type="button" id="print-button" class="bg-gray-500 text-white px-4 py-2 rounded">Print</button>
                    </div>
                </form>
            </div>
        </x-block>

        {{-- Totals --}}
        <x-block>
            <div class="grid grid-cols-1 gap-4 mb-4">
                <div>
                    <label for="total_discount" class="block">Total Discount</label>
                    <input type="number" id="total_discount" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
                <div>
                    <label for="total_price" class="block">Total Price</label>
                    <input type="number" name="total_price" id="total_price" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
                <div>
                    <label for="total_sales_today" class="block">Total Sales Today:</label>
                    <input type="number" name="total_sales_today" id="total_sales_today" class="form-input px-3 py-2 border rounded w-full bg-gray-100" readonly>
                </div>
            </div>
        </x-block>
    </div>

    {{-- Printable Invoice --}}
    <div id="print-area" class="print-hidden p-6 text-sm">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">Bee Phone</h1>
            <p>{{ now()->format('Y-m-d H:i') }}</p>
            <hr class="my-2">
        </div>
        <table class="w-full border border-gray-400 text-sm">
            <thead>
                <tr>
                    <th class="border px-2 py-1">Product</th>
                    <th class="border px-2 py-1 text-right">Qty</th>
                    <th class="border px-2 py-1 text-right">Unit Price</th>
                    <th class="border px-2 py-1 text-right">Discount</th>
                    <th class="border px-2 py-1 text-right">Total</th>
                </tr>
            </thead>
            <tbody id="print-products-list"></tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="border px-2 py-1 text-right font-bold">Totals</td>
                    <td id="print-total-discount" class="border px-2 py-1 text-right">0.00₪</td>
                    <td id="print-total-price" class="border px-2 py-1 text-right">0.00₪</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Styles --}}
    <style>
        .print-hidden { display: none; }
        @media print {
            body * { visibility: hidden !important; }
            #print-area, #print-area * { visibility: visible !important; }
            #print-area { position: absolute; top: 0; left: 0; width: 100%; background: white; padding: 20px; }
        }
    </style>

    {{-- Scripts --}}
    <script>
        const products = @json($productsForJS);
        const totalSalesToday = {{ $totalToday ?? 0 }};
        document.getElementById('total_sales_today').value = totalSalesToday.toFixed(2);

        const productsList = document.getElementById('products-list');

        document.getElementById('barcode_input').addEventListener('keypress', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = e.target.value.trim();
                const product = products.find(p => p.barcode === code);
                if (product) {
                    addProductRow(product);
                    e.target.value = '';
                } else {
                    alert('Product not found for barcode: ' + code);
                }
            }
        });

        document.getElementById('add-product-row').addEventListener('click', () => {
            addProductRow();
        });

        document.getElementById('clear-all').addEventListener('click', () => {
            productsList.innerHTML = '';
            calculateTotal();
        });

        document.addEventListener('input', e => {
            if (['quantity', 'discount', 'product-select'].some(cls => e.target.classList.contains(cls))) {
                calculateTotal();
            }
        });

        document.addEventListener('click', e => {
            if (e.target.classList.contains('remove-row')) {
                e.target.closest('.product-row').remove();
                calculateTotal();
            }

            const card = e.target.closest('.product-card');
            if (card) {
                const productId = parseInt(card.dataset.productId);
                const product = products.find(p => p.id === productId);
                if (product) addProductRow(product);
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.matches('.product-select') && !e.target.disabled) {
                const selectedId = e.target.value;
                const currentRow = e.target.closest('.product-row');
                if (!selectedId || !currentRow) return;

                const existingRow = Array.from(document.querySelectorAll('.product-row')).find(row => {
                    const hiddenId = row.querySelector('input[name="product_ids[]"]');
                    return hiddenId?.value == selectedId && row !== currentRow;
                });

                if (existingRow) {
                    const qtyInput = existingRow.querySelector('.quantity');
                    qtyInput.value = parseInt(qtyInput.value || 0) + 1;
                    currentRow.remove();
                } else {
                    const product = products.find(p => p.id == selectedId);
                    if (!product) return;

                    const hiddenInputs = `
                        <input type="hidden" name="product_ids[]" value="${product.id}">
                        <input type="hidden" name="cost_prices[]" value="${product.cost_price}">
                        <input type="hidden" name="selling_prices[]" value="${product.price}">
                    `;
                    currentRow.insertAdjacentHTML('afterbegin', hiddenInputs);
                    e.target.disabled = true;
                }

                calculateTotal();
            }
        });

        function addProductRow(product = null) {
            if (product) {
                const existing = [...document.querySelectorAll('input[name="product_ids[]"]')].find(input => input.value == product.id);
                if (existing) {
                    const row = existing.closest('.product-row');
                    const qty = row.querySelector('.quantity');
                    qty.value = parseInt(qty.value) + 1;

                    const manualRow = [...document.querySelectorAll('.product-select')].find(select => !select.disabled && select.value == product.id)?.closest('.product-row');
                    if (manualRow) manualRow.remove();

                    calculateTotal();
                    return;
                }
            }

            const row = document.createElement('div');
            row.className = 'product-row flex flex-wrap gap-2 mb-4 items-end';

            const id = product?.id ?? '';
            const cost = product?.cost_price ?? '';
            const price = product?.price ?? '';

            row.innerHTML = `
                ${product ? `
                    <input type="hidden" name="product_ids[]" value="${id}">
                    <input type="hidden" name="cost_prices[]" value="${cost}">
                    <input type="hidden" name="selling_prices[]" value="${price}">
                ` : ''}
                <div class="flex-1">
                    <label class="text-sm">Product</label>
                    <select class="form-select w-full px-3 py-2 border rounded product-select" ${product ? 'disabled' : ''}>
                        <option value="">Select Product</option>
                        ${products.map(p => `
                            <option value="${p.id}" ${product && p.id === product.id ? 'selected' : ''}>${p.name} (${p.price})</option>
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
            let total = 0, discount = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const disc = parseFloat(row.querySelector('.discount')?.value || 0);
                const select = row.querySelector('.product-select');
                const priceMatch = select?.selectedOptions[0]?.textContent.match(/\(([^)]+)\)/);
                const price = parseFloat(priceMatch?.[1] || 0);
                total += (price * qty) - disc;
                discount += disc;
            });
            document.getElementById('total_price').value = total.toFixed(2);
            document.getElementById('total_discount').value = discount.toFixed(2);
        }

        document.getElementById('print-button').addEventListener('click', () => {
            const list = document.getElementById('print-products-list');
            list.innerHTML = '';
            let total = 0, discount = 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const disc = parseFloat(row.querySelector('.discount')?.value || 0);
                const select = row.querySelector('.product-select');
                const name = select?.selectedOptions[0]?.textContent.split('(')[0]?.trim() || 'Unknown';
                const price = parseFloat(select?.selectedOptions[0]?.textContent.match(/\(([^)]+)\)/)?.[1] || 0);
                const sub = (price * qty) - disc;
                total += sub;
                discount += disc;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border px-2 py-1">${name}</td>
                    <td class="border px-2 py-1 text-right">${qty}</td>
                    <td class="border px-2 py-1 text-right">${price.toFixed(2)}₪</td>
                    <td class="border px-2 py-1 text-right">${disc.toFixed(2)}₪</td>
                    <td class="border px-2 py-1 text-right">${sub.toFixed(2)}₪</td>
                `;
                list.appendChild(tr);
            });

            document.getElementById('print-total-price').textContent = total.toFixed(2) + '₪';
            document.getElementById('print-total-discount').textContent = discount.toFixed(2) + '₪';

            window.print();
        });

        document.getElementById('product-search').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.name;
                card.style.display = name.includes(term) ? '' : 'none';
            });
        });

        window.addEventListener('DOMContentLoaded', () => {
            document.getElementById('barcode_input')?.focus();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('create-bill').submit();
            }
        });
    </script>
</x-app-layout>
