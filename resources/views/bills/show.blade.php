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
                            <th class="px-4 py-2 border">Discount</th>
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
                                <td class="px-4 py-2 border">
                                    <input type="number" name="discounts[{{ $product->id }}]" value="{{ old("discounts.$product->id", $product->pivot->discount ?? 0) }}" min="0" class="w-20 px-2 py-1 border rounded discount" step="0.01" required>
                                </td>
                                <td class="px-4 py-2 border total-cell">${{ number_format($product->pivot->quantity * $product->pivot->selling_price - ($product->pivot->discount ?? 0), 2) }}</td>
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

                {{-- Buttons --}}
                <div class="flex space-x-4">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save Changes</button>
                    <a href="{{ route('bills.index') }}" class="bg-gray-300 text-black px-4 py-2 rounded hover:bg-gray-400">Cancel</a>
                    <button type="button" id="print-button" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Print</button>
                </div>
            </form>
        </x-block>
    </div>

    {{-- Printable Section --}}
    <div id="print-area" class="print-hidden p-6 text-sm">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">Bee Phone</h1>
            <p>{{ now()->format('Y-m-d H:i') }}</p>
            <hr class="my-2">
        </div>
        <div><strong>Customer:</strong> {{ $bill->customer->name ?? 'N/A' }}</div>
        <div><strong>Bill ID:</strong> {{ $bill->id }}</div>
        <hr class="my-2">

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
        </table>

        <div class="flex justify-end mt-4 font-semibold space-x-10">
            <div>Total Discount: <span id="print-total-discount">0.00₪</span></div>
            <div>Total Price: <span id="print-total-price">0.00₪</span></div>
        </div>
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

    {{-- Scripts --}}
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
                <td class="px-4 py-2 border">
                    <input type="number" name="discounts[${product.id}]" value="0" min="0" class="w-20 px-2 py-1 border rounded discount" step="0.01" required>
                </td>
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

        document.querySelector('#products-table').addEventListener('input', e => {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('discount')) {
                const tr = e.target.closest('tr');
                const qty = parseInt(tr.querySelector('input.quantity').value) || 0;
                const discount = parseFloat(tr.querySelector('input.discount').value) || 0;
                const price = parseFloat(tr.children[2].textContent.replace('$','')) || 0;
                const totalCell = tr.querySelector('.total-cell');
                let total = (qty * price) - discount;
                totalCell.textContent = '$' + formatPrice(total > 0 ? total : 0);
            }
        });

        document.getElementById('print-button').addEventListener('click', () => {
            const printList = document.getElementById('print-products-list');
            printList.innerHTML = '';
            let totalPrice = 0;
            let totalDiscount = 0;

            document.querySelectorAll('#products-table tbody tr').forEach(row => {
                const name = row.children[0].textContent.trim();
                const quantity = parseFloat(row.querySelector('input.quantity')?.value || 0);
                const unitPrice = parseFloat(row.children[2].textContent.replace('$','')) || 0;
                const discount = parseFloat(row.querySelector('input.discount')?.value || 0);
                const subtotal = (unitPrice * quantity) - discount;
                totalPrice += subtotal > 0 ? subtotal : 0;
                totalDiscount += discount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border border-gray-400 px-2 py-1">${name}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${quantity}</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${unitPrice.toFixed(2)}₪</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${discount.toFixed(2)}₪</td>
                    <td class="border border-gray-400 px-2 py-1 text-right">${(subtotal > 0 ? subtotal : 0).toFixed(2)}₪</td>
                `;
                printList.appendChild(tr);
            });

            document.getElementById('print-total-price').textContent = totalPrice.toFixed(2) + '₪';
            document.getElementById('print-total-discount').textContent = totalDiscount.toFixed(2) + '₪';

            window.print();
        });
    </script>
</x-app-layout>
