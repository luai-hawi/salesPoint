@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    @endphp
<x-app-layout>
   {{-- Create Bill Header --}}
<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
            <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            {{ __('messages.Create New Bill') }}
        </h2>
        <div class="flex items-center space-x-4">
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                {{ __('bills.Mode') }}: <span class="font-bold text-green-600">{{ __('bills.Create') }}</span>
            </div>
            <a href="{{ route('bills.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('bills.Back to Bills') }}
            </a>
        </div>
    </div>
</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('bills.store') }}" class="space-y-8">
            @csrf

            <!-- Bill Information -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('messages.Bill Information') }}
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Customer Selection -->
                    <div>
                        <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-2">{{ __('bills.Customer (Optional)') }}</label>
                        <select name="customer_id" id="customer_id" class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">{{ __('bills.Walk-in Customer') }}</option>
                            @if(isset($customers))
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} - Balance: ${{ number_format($customer->balance, 2) }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- Damaged Bill Checkbox -->
                    <div class="flex items-center">
                        <div class="flex items-center h-5">
                            <input type="checkbox" name="is_damaged" id="is_damaged" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                        </div>
                        <div class="ml-3">
                            <label for="is_damaged" class="text-sm font-medium text-gray-700">{{ __('bills.Damaged Bill') }}</label>
                            <p class="text-xs text-gray-500">{{ __('bills.Mark this bill as damaged (full discount applied)') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Note -->
                <div class="mt-6">
                    <label for="note" class="block text-sm font-medium text-gray-700 mb-2">{{ __('bills.Note') }}</label>
                    <textarea name="note" id="note" rows="3" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                              placeholder="{{ __('bills.Add a note to this bill...') }}"></textarea>
                </div>
            </div>

            <!-- Product Input Section -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    {{ __('bills.Add Products') }}
                </h3>

                <!-- Barcode Scanner -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-6">
                    <div class="flex items-center mb-4">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                        <h4 class="text-lg font-medium text-gray-800">{{ __('bills.Barcode Scanner') }}</h4>
                    </div>
                    <div class="flex space-x-3">
                        <input type="text" id="barcode_input" 
                               placeholder="{{ __('bills.Scan barcode or type manually...') }}" 
                               class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-mono" 
                               autocomplete="off">
                        <button type="button" id="clear-barcode" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-3 rounded-lg transition-colors">
                            {{ __('bills.Clear') }}
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ __('bills.Scan a barcode or press Enter to add the product automatically') }}
                    </p>
                </div>

                <!-- Manual Product Addition -->
                <div class="flex justify-center mb-6">
                    <button type="button" id="add-product-row" 
                            class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg transition-colors flex items-center font-medium shadow-md">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        {{ __('bills.Add Product Manually') }}
                    </button>
                </div>
            </div>

            <!-- Products List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden" id="products-section" style="display: none;">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        {{ __('bills.Selected Products') }}
                        <span id="products-count" class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">0</span>
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Product') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Quantity') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Unit Price') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Discount') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Total') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="products-list" class="bg-white divide-y divide-gray-200">
                            <!-- Dynamic rows will be added here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total and Submit -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center">
                    <div class="text-right">
                        <p class="text-sm text-gray-600">{{ __('bills.Grand Total') }}</p>
                        <p class="text-3xl font-bold text-gray-900" id="grand-total">$0.00</p>
                        <input type="hidden" name="total_price" id="total_price" value="0">
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="{{ route('bills.index') }}" 
                           class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded-lg transition-colors flex items-center font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            {{ __('bills.Cancel') }}
                        </a>
                        <button type="submit" id="create-bill-btn" disabled
                                class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-8 py-3 rounded-lg transition-colors flex items-center font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('messages.Create Bill') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        const products = @json($productsForJS ?? []);
        const productsList = document.getElementById('products-list');
        const productsSection = document.getElementById('products-section');
        const barcodeInput = document.getElementById('barcode_input');
        const createBillBtn = document.getElementById('create-bill-btn');
        const productsCount = document.getElementById('products-count');
        let productRowIndex = 0;

        function updateProductsCount() {
            const count = productsList.children.length;
            productsCount.textContent = count;
            productsSection.style.display = count > 0 ? 'block' : 'none';
            createBillBtn.disabled = count === 0;
        }

        function formatPrice(num) {
            return parseFloat(num || 0).toFixed(2);
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const select = row.querySelector('.product-select');
                const quantity = parseFloat(row.querySelector('.quantity')?.value || 0);
                const discount = parseFloat(row.querySelector('.discount')?.value || 0);
                let price = 0;
                
                if (select) {
                    const selectedOption = select.options[select.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        const product = products.find(p => p.id == selectedOption.value);
                        price = product ? parseFloat(product.price) : 0;
                    }
                }
                
                const lineTotal = Math.max(0, (price * quantity) - discount);
                total += lineTotal;
                
                // Update row total display
                const totalCell = row.querySelector('.row-total');
                if (totalCell) {
                    totalCell.textContent = '$' + formatPrice(lineTotal);
                }
            });
            
            document.getElementById('grand-total').textContent = '$' + formatPrice(total);
            document.getElementById('total_price').value = formatPrice(total);
        }

        function addProductRow(product = null) {
            const row = document.createElement('tr');
            row.className = 'product-row hover:bg-gray-50';
            
            const rowId = productRowIndex++;
            
            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${product?.id || ''}">
                <input type="hidden" name="cost_prices[]" value="${product?.cost_price || ''}">
                <input type="hidden" name="selling_prices[]" value="${product?.price || ''}">
                
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                            <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                <svg class="h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <select name="product_ids[]" class="product-select w-full px-8 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" ${product ? 'disabled' : ''} required>
                                <option value="">{{ __('bills.Select Product') }}</option>
                                ${products.map(p => `
                                    <option value="${p.id}" ${product && p.id === product.id ? 'selected' : ''}>
                                        ${p.name} (${formatPrice(p.price)})
                                    </option>
                                `).join('')}
                            </select>
                            ${product ? `<div class="text-sm text-gray-500 mt-1">${product.barcode || '{{ __('bills.No barcode') }}'}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="quantities[]" class="quantity w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           min="1" value="1" required>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <span class="unit-price">${product ? formatPrice(product.price) : '0.00'}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="number" name="discounts[]" class="discount w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           min="0" value="0" step="0.01" required>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                    <span class="row-total">${product ? formatPrice(product.price) : '0.00'}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <button type="button" class="remove-row bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </td>
            `;

            productsList.appendChild(row);
            updateProductsCount();
            calculateTotal();
        }

        // Event Listeners
        barcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = barcodeInput.value.trim();
                const product = products.find(p => p.barcode === code);
                if (product) {
                    addProductRow(product);
                    barcodeInput.value = '';
                } else {
                    alert('{{ __('bills.Product not found for barcode: ') }}' + code);
                }
            }
        });

        document.getElementById('clear-barcode').addEventListener('click', () => {
            barcodeInput.value = '';
            barcodeInput.focus();
        });

        document.getElementById('add-product-row').addEventListener('click', () => {
            addProductRow();
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-row') || e.target.closest('.remove-row')) {
                e.target.closest('.product-row').remove();
                updateProductsCount();
                calculateTotal();
            }
        });

        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('quantity') || e.target.classList.contains('discount')) {
                calculateTotal();
            } else if (e.target.classList.contains('product-select')) {
                const row = e.target.closest('.product-row');
                const selectedOption = e.target.options[e.target.selectedIndex];
                
                if (selectedOption.value) {
                    const product = products.find(p => p.id == selectedOption.value);
                    if (product) {
                        // Update hidden inputs
                        row.querySelector('input[name="product_ids[]"]').value = product.id;
                        row.querySelector('input[name="cost_prices[]"]').value = product.cost_price;
                        row.querySelector('input[name="selling_prices[]"]').value = product.price;
                        
                        // Update unit price display
                        row.querySelector('.unit-price').textContent = '$' + formatPrice(product.price);
                    }
                } else {
                    // Clear values
                    row.querySelector('input[name="product_ids[]"]').value = '';
                    row.querySelector('input[name="cost_prices[]"]').value = '';
                    row.querySelector('input[name="selling_prices[]"]').value = '';
                    row.querySelector('.unit-price').textContent = '$0.00';
                }
                
                calculateTotal();
            }
        });

        // Damaged bill checkbox handler
        document.getElementById('is_damaged').addEventListener('change', function() {
            const discountInputs = document.querySelectorAll('.discount');
            if (this.checked) {
                discountInputs.forEach(input => {
                    const row = input.closest('.product-row');
                    const quantity = parseFloat(row.querySelector('.quantity').value || 0);
                    const unitPriceText = row.querySelector('.unit-price').textContent.replace('$', '');
                    const unitPrice = parseFloat(unitPriceText || 0);
                    input.value = quantity * unitPrice;
                    input.disabled = true;
                });
            } else {
                discountInputs.forEach(input => {
                    input.value = 0;
                    input.disabled = false;
                });
            }
            calculateTotal();
        });

        // Focus barcode input on page load
        window.addEventListener('load', () => {
            barcodeInput.focus();
        });

        // Initialize
        updateProductsCount();
    </script>
</x-app-layout>