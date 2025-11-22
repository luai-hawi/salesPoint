<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.Edit Purchase Bill #') }}{{ $purchaseBill->id }}
            </h2>
            <a href="{{ route('purchase-bills.show', $purchaseBill) }}" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                {{ __('messages.Back to Bill') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('purchase-bills.update', $purchaseBill) }}" id="purchase-bill-form">
                        @csrf
                        @method('PUT')
                        
                        <!-- Bill Header Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Supplier') }} *</label>
                                <select name="supplier_id" id="supplier_id" required
                                        class="w-full border border-gray-300 rounded-lg px-8 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">{{ __('messages.Select Supplier') }}</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id', $purchaseBill->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Purchase Date') }} *</label>
                                <input type="date" name="purchase_date" id="purchase_date" required
                                       value="{{ old('purchase_date', $purchaseBill->purchase_date->format('Y-m-d')) }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('purchase_date')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Reference Number') }}</label>
                                <input type="text" name="reference_number" id="reference_number"
                                       value="{{ old('reference_number', $purchaseBill->reference_number) }}"
                                       placeholder="{{ __('messages.Supplier\'s invoice number') }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('reference_number')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Notes') }}</label>
                                <textarea name="notes" id="notes" rows="2"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $purchaseBill->notes) }}</textarea>
                                @error('notes')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Product Search -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Add Products') }}</h3>
                            <div class="flex gap-4">
                                <input type="text" id="product-search" placeholder="{{ __('messages.Search products...') }}"
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <select id="product-select" class="border border-gray-300 rounded-lg px-8 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">{{ __('messages.Select Product') }}</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-current-cost="{{ $product->cost_price }}">
                                            {{ $product->name }} ({{ __('messages.Current:') }} ₪{{ $product->cost_price }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="add-product-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    {{ __('messages.Add Product') }}
                                </button>
                            </div>
                        </div>

                        <!-- Products Table -->
                        <div class="mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Product') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Quantity') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Unit Cost') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Total') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('messages.Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="products-table-body" class="bg-white divide-y divide-gray-200">
                                        <!-- Existing products will be populated here -->
                                    </tbody>
                                </table>
                                
                                <div id="no-products-message" class="text-center py-8 text-gray-500" style="display: none;">
                                    {{ __('messages.No products added yet. Use the search above to add products.') }}
                                </div>
                            </div>
                        </div>

                        <!-- Total Summary -->
                        <div class="bg-gray-50 rounded-lg p-6 mb-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">{{ __('messages.Total Amount') }}</h3>
                                <div class="text-2xl font-bold text-green-600" id="total-amount">₪0.00</div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('purchase-bills.show', $purchaseBill) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                                {{ __('messages.Cancel') }}
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                {{ __('messages.Update Purchase Bill') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Dynamic Product Management -->
    <script>
        let productIndex = 0;
        const productsData = @json($products->keyBy('id'));
        const existingProducts = @json($purchaseBill->products);
        
        // Initialize with existing products
        document.addEventListener('DOMContentLoaded', function() {
            const tableBody = document.getElementById('products-table-body');
            const noProductsMessage = document.getElementById('no-products-message');
            
            if (existingProducts.length > 0) {
                noProductsMessage.style.display = 'none';
                existingProducts.forEach(function(product) {
                    addProductRow(product.id, product.name, product.pivot.unit_cost, product.pivot.quantity);
                });
            } else {
                noProductsMessage.style.display = 'block';
            }
            
            updateTotal();
        });
        
        // Product search functionality
        document.getElementById('product-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const productSelect = document.getElementById('product-select');
            const options = productSelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') return;
                const productName = option.dataset.name.toLowerCase();
                option.style.display = productName.includes(searchTerm) ? '' : 'none';
            });
        });

        // Add product functionality
        document.getElementById('add-product-btn').addEventListener('click', function() {
            const productSelect = document.getElementById('product-select');
            const productId = productSelect.value;
            
            if (!productId) {
                alert('{{ __('messages.Please select a product') }}');
                return;
            }

            // Check if product already exists
            const existingRow = document.querySelector(`input[value="${productId}"][name="product_ids[]"]`);
            if (existingRow) {
                alert('{{ __('messages.Product already added') }}');
                return;
            }

            const selectedOption = productSelect.selectedOptions[0];
            const productName = selectedOption.dataset.name;
            const currentCost = selectedOption.dataset.currentCost;

            addProductRow(productId, productName, currentCost);
            productSelect.value = '';
            updateTotal();
        });

        function addProductRow(productId, productName, currentCost, quantity = 1) {
            const tableBody = document.getElementById('products-table-body');
            const noProductsMessage = document.getElementById('no-products-message');
            
            noProductsMessage.style.display = 'none';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">${productName}</div>
                    <input type="hidden" name="product_ids[]" value="${productId}">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="quantities[]" value="${quantity}" min="1" 
                           class="w-20 border border-gray-300 rounded px-2 py-1 quantity-input">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="unit_costs[]" value="${currentCost}" min="0" step="0.01"
                           class="w-24 border border-gray-300 rounded px-2 py-1 cost-input">
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium total-cell">₪${(quantity * currentCost).toFixed(2)}</div>
                </td>
                <td class="px-4 py-3">
                    <button type="button" class="text-red-600 hover:text-red-900 remove-product">
                        {{ __('messages.Remove') }}
                    </button>
                </td>
            `;

            tableBody.appendChild(row);

            // Add event listeners
            const quantityInput = row.querySelector('.quantity-input');
            const costInput = row.querySelector('.cost-input');
            const removeBtn = row.querySelector('.remove-product');

            quantityInput.addEventListener('input', updateRowTotal);
            costInput.addEventListener('input', updateRowTotal);
            removeBtn.addEventListener('click', function() {
                row.remove();
                updateTotal();
                if (tableBody.children.length === 0) {
                    noProductsMessage.style.display = 'block';
                }
            });

            function updateRowTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const cost = parseFloat(costInput.value) || 0;
                const total = quantity * cost;
                row.querySelector('.total-cell').textContent = `₪${total.toFixed(2)}`;
                updateTotal();
            }
        }

        function updateTotal() {
            const rows = document.querySelectorAll('#products-table-body tr');
            let total = 0;

            rows.forEach(row => {
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
                total += quantity * cost;
            });

            document.getElementById('total-amount').textContent = `₪${total.toFixed(2)}`;
        }

        // Form validation
        document.getElementById('purchase-bill-form').addEventListener('submit', function(e) {
            const productRows = document.querySelectorAll('#products-table-body tr');
            if (productRows.length === 0) {
                e.preventDefault();
                alert('{{ __('messages.Please add at least one product') }}');
                return;
            }
        });
    </script>
</x-app-layout>