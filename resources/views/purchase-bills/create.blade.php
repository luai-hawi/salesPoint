@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @if (request('duplicate'))
                    {{ __('messages.Duplicate Purchase Bill') }}
                @else
                    {{ __('messages.Create Purchase Bill') }}
                @endif
            </h2>
            <a href="{{ route('purchase-bills.index') }}"
                class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                {{ __('messages.Back to Purchase Bills') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('purchase-bills.store') }}" id="purchase-bill-form">
                        @csrf

                        <!-- Bill Header Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label for="supplier_id"
                                    class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Supplier') }}
                                    *</label>
                                <select name="supplier_id" id="supplier_id" required
                                    class="w-full border border-gray-300 rounded-lg px-8 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">{{ __('messages.Select Supplier') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ old('supplier_id', $duplicatedBill->supplier_id ?? '') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="purchase_date"
                                    class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Purchase Date') }}
                                    *</label>
                                <input type="date" name="purchase_date" id="purchase_date" required
                                    value="{{ old('purchase_date', date('Y-m-d')) }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('purchase_date')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="reference_number"
                                    class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Reference Number') }}</label>
                                <input type="text" name="reference_number" id="reference_number"
                                    value="{{ old('reference_number', $duplicatedBill->reference_number ?? '') }}"
                                    placeholder="{{ __('messages.Supplier\'s invoice number') }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @error('reference_number')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="notes"
                                    class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Notes') }}</label>
                                <textarea name="notes" id="notes" rows="2"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $duplicatedBill->notes ?? '') }}</textarea>
                                @error('notes')
                                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Advanced Product Search -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('messages.Add Products') }}</h3>

                            <!-- Barcode Scanner -->
                            <div class="mb-4">
                                <div class="relative">
                                    <input type="text" id="barcode_input"
                                        placeholder="{{ __('messages.Scan or enter barcode...') }}"
                                        class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors font-mono"
                                        autocomplete="off" />
                                    <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z">
                                        </path>
                                    </svg>
                                    <!-- Camera Scanner Icon -->
                                    <button type="button" id="scan-barcode-btn"
                                        class="absolute right-3 top-3.5 h-5 w-5 text-gray-400 hover:text-purple-500 transition-colors cursor-pointer"
                                        title="{{ __('messages.Scan with camera') }}">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Product Search -->
                            <div class="mb-4">
                                <div class="relative">
                                    <input type="text" id="product-search"
                                        placeholder="{{ __('messages.Search products by name...') }}"
                                        class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Filter Options -->
                            <div class="flex flex-wrap gap-2 mb-4">
                                <button id="filter-all"
                                    class="filter-btn active px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200 transition-colors">
                                    {{ __('messages.All Products') }}
                                </button>
                                <button id="filter-in-stock"
                                    class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                    {{ __('messages.In Stock Only') }}
                                </button>
                                <button id="filter-out-of-stock"
                                    class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                    {{ __('messages.Out of Stock') }}
                                </button>
                            </div>

                            <!-- Product Results -->
                            <div class="bg-white rounded-xl border border-gray-200">
                                <div class="p-4 border-b border-gray-100">
                                    <h4 class="font-medium text-gray-800 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4">
                                            </path>
                                        </svg>
                                        {{ __('messages.Available Products') }}
                                    </h4>
                                </div>
                                <div id="product-cards-container" class="max-h-80 overflow-y-auto">
                                    <div id="product-results"
                                        class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 p-4">
                                        <!-- Products will be loaded here -->
                                    </div>
                                    <div id="loading-indicator" class="hidden p-4 text-center">
                                        <div
                                            class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto">
                                        </div>
                                        <p class="text-sm text-gray-500 mt-2">{{ __('messages.Loading products...') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Products Table -->
                        <div class="mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ __('messages.Product') }}</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ __('messages.Quantity') }}</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ __('messages.Unit Cost') }}</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ __('messages.Barcodes') }}</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ __('messages.Total') }}</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ __('messages.Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="products-table-body" class="bg-white divide-y divide-gray-200">
                                        <!-- Products will be added here dynamically -->
                                    </tbody>
                                </table>

                                <div id="no-products-message" class="text-center py-8 text-gray-500">
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
                            <a href="{{ route('purchase-bills.index') }}"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                                {{ __('messages.Cancel') }}
                            </a>
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                {{ __('messages.Create Purchase Bill') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Styles for product cards and filters -->
    <style>
        .product-card {
            transition: transform 0.15s ease-out, box-shadow 0.15s ease-out;
        }

        .product-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .product-card.out-of-stock {
            opacity: 0.7;
        }

        .filter-btn.active {
            background-color: rgb(59 130 246);
            color: white;
            border-color: rgb(59 130 246);
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .product-card:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }
    </style>

    <!-- JavaScript for Dynamic Product Management -->
    <script>
        let productIndex = 0;
        const productsData = @json($products->keyBy('id'));
        const duplicatedBill = @json($duplicatedBill ?? null);

        // Advanced search variables (copied from dashboard)
        let currentFilter = 'all';
        let debounceTimeout = null;
        let currentPage = 1;
        let hasMore = true;
        let isLoading = false;
        let searchTerm = '';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load products initially
            fetchProducts(true);

            // Initialize with duplicated products if exists
            if (duplicatedBill && duplicatedBill.products) {
                const tableBody = document.getElementById('products-table-body');
                const noProductsMessage = document.getElementById('no-products-message');

                noProductsMessage.style.display = 'none';

                duplicatedBill.products.forEach(function(product) {
                    let barcodes = [];
                    try {
                        barcodes = JSON.parse(product.pivot.barcodes) || [];
                    } catch (e) {
                        barcodes = [];
                    }
                    addProductRow(product.id, product.name, product.pivot.unit_cost, product.pivot.quantity,
                        barcodes);
                });

                updateTotal();
            }

            // Focus barcode input
            document.getElementById('barcode_input').focus();
        });

        // Enhanced barcode input handler (copied from dashboard)
        document.getElementById('barcode_input').addEventListener('keydown', async e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = e.target.value.trim();
                if (!code) return;

                try {
                    const response = await fetch(`/products/search?barcode=${encodeURIComponent(code)}`);
                    if (!response.ok) {
                        showNotification('{{ __('messages.Error fetching product from server.') }}', 'error');
                        return;
                    }
                    const result = await response.json();

                    if (result && result.multiple_products) {
                        showBarcodeModal(result.products, result.barcode);
                        e.target.value = '';
                    } else if (result && result.id) {
                        addProductToTable(result);
                        e.target.value = '';
                        showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}',
                            result.name), 'success');
                    } else {
                        showNotification('{{ __('messages.Product not found for barcode: {code}') }}'.replace(
                            '{code}', code), 'warning');
                    }
                } catch (err) {
                    console.error('Fetch error:', err);
                    showNotification('{{ __('messages.Failed to fetch product data.') }}', 'error');
                }
            }
        });

        // Show modal for duplicate barcodes (copied from dashboard)
        function showBarcodeModal(products, barcode) {
            const modal = document.createElement('div');
            modal.id = 'barcode-modal';
            modal.className = 'fixed inset-0 z-50 overflow-y-auto';
            modal.innerHTML = `
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="modal-overlay fixed inset-0 bg-black bg-opacity-50 transition-opacity" aria-hidden="true"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        {{ __('messages.Multiple Products Found') }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            Multiple products were found with barcode "${barcode}". Please select which product you want to add:
                                        </p>
                                    </div>
                                    <div id="duplicate-products" class="mt-4 space-y-2">
                                        ${products.map(product => `
                                                                                                                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                                                                                                                <div class="flex-1">
                                                                                                                                    <div class="font-medium text-gray-900">${product.name}</div>
                                                                                                                                    <div class="text-sm text-gray-500">Cost: ₪${product.cost_price} | Stock: ${product.quantity}</div>
                                                                                                                                </div>
                                                                                                                                <button class="select-duplicate-product bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm" data-product='${JSON.stringify(product)}'>
                                                                                                                                    {{ __('messages.Select') }}
                                                                                                                                </button>
                                                                                                                            </div>
                                                                                                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" id="close-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ __('messages.Cancel') }}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add event listeners
            modal.querySelector('#close-modal').addEventListener('click', () => {
                document.body.removeChild(modal);
                document.getElementById('barcode_input').focus();
            });

            modal.querySelector('.modal-overlay').addEventListener('click', () => {
                document.body.removeChild(modal);
                document.getElementById('barcode_input').focus();
            });

            modal.querySelectorAll('.select-duplicate-product').forEach(btn => {
                btn.addEventListener('click', () => {
                    const product = JSON.parse(btn.dataset.product);
                    addProductToTable(product);
                    document.body.removeChild(modal);
                    showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}',
                        product.name), 'success');
                    document.getElementById('barcode_input').focus();
                });
            });
        }

        // Filter buttons (copied from dashboard)
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                currentFilter = e.target.id.replace('filter-', '');
                searchTerm = document.getElementById('product-search').value.trim();
                fetchProducts(true);
            });
        });

        // Enhanced product search with debouncing (copied from dashboard)
        document.getElementById('product-search').addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                searchTerm = this.value.trim();
                currentPage = 1;
                hasMore = true;
                fetchProducts(true);
            }, 300);
        });

        // Product fetching (adapted from dashboard)
        function fetchProducts(reset = false) {
            if (isLoading || !hasMore) return;
            isLoading = true;

            if (reset) {
                const container = document.getElementById('product-results');
                container.replaceChildren();
                currentPage = 1;
                hasMore = true;
                showLoadingIndicator(true);
            }

            const params = new URLSearchParams({
                search: searchTerm,
                page: currentPage,
                filter: currentFilter,
                per_page: 12
            });

            fetch(`/products/searchAll?${params}`)
                .then(response => {
                    if (!response.ok) throw new Error('{{ __('messages.Search failed') }}');
                    return response.json();
                })
                .then(data => {
                    const products = data.data || [];

                    if (products.length === 0 && currentPage === 1) {
                        document.getElementById('product-results').innerHTML =
                            '<p class="text-gray-500 text-center py-4 col-span-full">{{ __('messages.No products found') }}</p>';
                        hasMore = false;
                        return;
                    }

                    const filteredProducts = filterProducts(products);
                    renderProducts(filteredProducts);

                    hasMore = data.current_page < data.last_page;
                    currentPage++;
                })
                .catch(error => {
                    if (currentPage === 1) {
                        document.getElementById('product-results').innerHTML =
                            '<p class="text-red-500 text-center py-4 col-span-full">{{ __('messages.Error loading products') }}</p>';
                    }
                    console.error(error);
                    showNotification('{{ __('messages.Error loading products') }}', 'error');
                })
                .finally(() => {
                    isLoading = false;
                    showLoadingIndicator(false);
                });
        }

        // Filter products based on current filter (copied from dashboard)
        function filterProducts(products) {
            switch (currentFilter) {
                case 'in-stock':
                    return products.filter(p => p.quantity > 0);
                case 'out-of-stock':
                    return products.filter(p => p.quantity === 0);
                default:
                    return products;
            }
        }

        // Create product card (adapted from dashboard for purchase bills)
        function createProductCard(product) {
            const card = document.createElement('div');
            const isOutOfStock = product.quantity === 0;

            card.className =
                `product-card bg-white p-3 border rounded-lg shadow-sm cursor-pointer ${isOutOfStock ? 'out-of-stock' : ''}`;
            card.dataset.productId = product.id;
            card.dataset.cost_price = product.cost_price;
            card.dataset.selling_price = product.selling_price;
            card.dataset.category = product.category || '';

            let firstImage = null;
            try {
                const pictures = typeof product.pictures === 'string' ? JSON.parse(product.pictures) : product.pictures;
                firstImage = Array.isArray(pictures) ? pictures[0] : null;
            } catch (e) {
                // Silent fail
            }

            const imageHtml = firstImage ?
                `<img src="/storage/${firstImage}" class="w-full h-20 object-cover rounded-lg bg-gray-100" loading="lazy" alt="${product.name}">` :
                `<div class="w-full h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                   </div>`;

            const categoryBadge = product.category ?
                `<div class="mb-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${product.category}
                    </span>
                 </div>` : '';

            card.innerHTML = `
                <div class="space-y-2">
                    <div class="relative overflow-hidden rounded-lg">
                        ${imageHtml}
                    </div>
                    <div class="min-w-0">
                        ${categoryBadge}
                        <div class="text-sm font-medium text-gray-900 truncate">${product.name}</div>
                        <div class="text-xs text-gray-500 font-semibold">Cost: ₪${product.cost_price}</div>
                        <div class="mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${isOutOfStock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                                ${isOutOfStock ? '{{ __('messages.Out of Stock') }}' : `${product.quantity} {{ __('messages.in stock') }}`}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            return card;
        }

        // Render products with category grouping (copied from dashboard)
        function renderProducts(products) {
            const groupedProducts = {};
            let uncategorizedProducts = [];

            products.forEach(product => {
                const category = product.category || '';
                if (category) {
                    if (!groupedProducts[category]) {
                        groupedProducts[category] = [];
                    }
                    groupedProducts[category].push(product);
                } else {
                    uncategorizedProducts.push(product);
                }
            });

            const container = document.getElementById('product-results');

            Object.keys(groupedProducts).sort().forEach(category => {
                if (Object.keys(groupedProducts).length > 1 || uncategorizedProducts.length > 0) {
                    const categoryHeader = document.createElement('div');
                    categoryHeader.className = 'col-span-full mb-2 mt-4 first:mt-0';
                    categoryHeader.innerHTML = `
                        <div class="flex items-center">
                            <div class="flex-grow border-t border-gray-300"></div>
                            <span class="flex-shrink mx-4 text-sm font-medium text-gray-600 bg-gray-50 px-3 py-1 rounded-full">
                                ${category}
                            </span>
                            <div class="flex-grow border-t border-gray-300"></div>
                        </div>
                    `;
                    container.appendChild(categoryHeader);
                }

                groupedProducts[category].forEach(product => {
                    const card = createProductCard(product);
                    container.appendChild(card);
                });
            });

            if (uncategorizedProducts.length > 0) {
                if (Object.keys(groupedProducts).length > 0) {
                    const uncategorizedHeader = document.createElement('div');
                    uncategorizedHeader.className = 'col-span-full mb-2 mt-4';
                    uncategorizedHeader.innerHTML = `
                        <div class="flex items-center">
                            <div class="flex-grow border-t border-gray-300"></div>
                            <span class="flex-shrink mx-4 text-sm font-medium text-gray-500 bg-gray-50 px-3 py-1 rounded-full">
                                {{ __('messages.Uncategorized') }}
                            </span>
                            <div class="flex-grow border-t border-gray-300"></div>
                        </div>
                    `;
                    container.appendChild(uncategorizedHeader);
                }

                uncategorizedProducts.forEach(product => {
                    const card = createProductCard(product);
                    container.appendChild(card);
                });
            }
        }

        // Loading indicator (copied from dashboard)
        function showLoadingIndicator(show) {
            const indicator = document.getElementById('loading-indicator');
            if (indicator) {
                indicator.classList.toggle('hidden', !show);
            }
        }

        // Scroll handler for infinite scroll (copied from dashboard)
        document.getElementById('product-cards-container').addEventListener('scroll', (e) => {
            const container = e.target;
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;

            if (scrollTop + clientHeight >= scrollHeight - 100) {
                fetchProducts();
            }
        });

        // Add product to table when clicked from search results
        document.addEventListener('click', e => {
            const card = e.target.closest('.product-card');
            if (card) {
                const nameElement = card.querySelector('.text-sm.font-medium');
                if (!nameElement) return;

                const product = {
                    id: parseInt(card.dataset.productId),
                    name: nameElement.textContent,
                    cost_price: parseFloat(card.dataset.cost_price),
                    selling_price: parseFloat(card.dataset.selling_price),
                    quantity: parseFloat(card.querySelector('.bg-green-100, .bg-red-100')?.textContent.match(
                        /\d+\.?\d*/)?.[0] || 0)
                };

                addProductToTable(product);
                showNotification(`{{ __('messages.Added {product} to purchase bill') }}`.replace('{product}',
                    product.name), 'success');
                document.getElementById('barcode_input').focus();
            }
        });

        // Function to add product to table (combines dashboard logic with purchase bill table)
        function addProductToTable(product) {
            addProductRow(product.id, product.name, product.cost_price, 1);
        }

        // Original table management functions (preserved from original)
        function addProductRow(productId, productName, currentCost, quantity = 1, barcodes = []) {
            const tableBody = document.getElementById('products-table-body');
            const noProductsMessage = document.getElementById('no-products-message');

            // Check if product already exists
            const existingRow = document.querySelector(`input[value="${productId}"][name="product_ids[]"]`);
            if (existingRow) {
                const row = existingRow.closest('tr');
                const quantityInput = row.querySelector('.quantity-input');
                const currentQty = parseFloat(quantityInput.value);
                quantityInput.value = currentQty + quantity;

                // Update row total
                const costInput = row.querySelector('.cost-input');
                const cost = parseFloat(costInput.value) || 0;
                const total = (currentQty + quantity) * cost;
                row.querySelector('.total-cell').textContent = `₪${total.toFixed(2)}`;
                updateTotal();
                return;
            }

            noProductsMessage.style.display = 'none';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">${productName}</div>
                    <input type="hidden" name="product_ids[]" value="${productId}">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="quantities[]" min="0.01" step="0.01"
                            class="w-20 border border-gray-300 rounded px-2 py-1 quantity-input">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="unit_costs[]" min="0" step="0.01"
                            class="w-24 border border-gray-300 rounded px-2 py-1 cost-input">
                </td>
                <td class="px-4 py-3">
                    <div class="barcodes-container w-48">
                        <div class="barcodes-list max-h-20 overflow-y-auto border border-gray-300 rounded p-1 mb-1 text-sm">
                            <!-- Barcodes will be listed here -->
                        </div>
                        <div class="flex">
                            <input type="text" class="barcode-input flex-1 border border-gray-300 rounded-l px-2 py-1 text-sm" placeholder="Enter barcode">
                            <button type="button" class="add-barcode-btn bg-blue-600 text-white px-2 py-1 rounded-r text-sm hover:bg-blue-700" data-product-id="${productId}">Add</button>
                        </div>
                    </div>
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

            // Set input values
            const quantityInput = row.querySelector('.quantity-input');
            const costInput = row.querySelector('.cost-input');
            quantityInput.value = quantity;
            costInput.value = currentCost;

            // Setup barcodes UI
            const barcodesContainer = row.querySelector('.barcodes-container');
            const barcodesList = barcodesContainer.querySelector('.barcodes-list');
            const addBtn = barcodesContainer.querySelector('.add-barcode-btn');
            const barcodeInput = barcodesContainer.querySelector('.barcode-input');
            const prodId = addBtn.dataset.productId;

            function addBarcode(code) {
                const item = document.createElement('div');
                item.className = 'flex justify-between items-center py-1';
                item.innerHTML = `
                    <span class="text-gray-700 text-xs">${code}</span>
                    <button type="button" class="remove-barcode text-red-600 hover:text-red-800 ml-1 text-sm">×</button>
                `;
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `barcodes_${prodId}[]`;
                hiddenInput.value = code;
                barcodesContainer.appendChild(hiddenInput);
                barcodesList.appendChild(item);
                item.querySelector('.remove-barcode').addEventListener('click', () => {
                    item.remove();
                    hiddenInput.remove();
                });
            }

            // Add existing barcodes
            barcodes.forEach(addBarcode);

            // Add event for add button
            addBtn.addEventListener('click', async () => {
                const code = barcodeInput.value.trim();
                if (code) {
                    // Check if barcode already exists
                    try {
                        const response = await fetch('{{ route('products.check-barcodes') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                barcode: code,
                                additional_barcodes: []
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Failed to check barcode');
                        }

                        const data = await response.json();
                        const duplicates = Array.isArray(data.duplicates) ? data.duplicates : [];

                        if (duplicates.length > 0) {
                            const lines = duplicates.map(item => {
                                const productNames = (item.products || [])
                                    .map(product => {
                                        const label = product.source === 'main' ?
                                            '{{ __('messages.Main barcode') }}' :
                                            '{{ __('messages.Additional barcode') }}';
                                        return product.name ? `${product.name} (${label})` : label;
                                    })
                                    .filter(Boolean)
                                    .join(', ');
                                return `${item.barcode}: ${productNames}`;
                            });

                            const message =
                                '{{ __('messages.Barcode already exists. Do you want to continue?') }}' +
                                '\n\n' + lines.join('\n');

                            if (confirm(message)) {
                                addBarcode(code);
                                barcodeInput.value = '';
                            }
                        } else {
                            addBarcode(code);
                            barcodeInput.value = '';
                        }
                    } catch (error) {
                        console.error('Error checking barcode:', error);
                        // Continue adding barcode if verification fails
                        addBarcode(code);
                        barcodeInput.value = '';
                    }
                }
            });

            // Prevent form submission on enter in barcode input
            barcodeInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addBtn.click();
                }
            });

            // Add event listeners
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

            updateTotal();
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

        // Notification system (copied from dashboard)
        function showNotification(message, type = 'info') {
            let notification = document.querySelector('.notification-toast');

            if (!notification) {
                notification = document.createElement('div');
                notification.className =
                    'notification-toast fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
                document.body.appendChild(notification);
            }

            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };

            notification.className =
                `notification-toast fixed top-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            notification.textContent = message;

            if (notification.hideTimeout) {
                clearTimeout(notification.hideTimeout);
            }

            requestAnimationFrame(() => {
                notification.classList.remove('translate-x-full');
            });

            notification.hideTimeout = setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', e => {
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('barcode_input').focus();
            }

            if (e.key === 'Escape') {
                const modal = document.getElementById('barcode-modal');
                if (modal) {
                    document.body.removeChild(modal);
                    document.getElementById('barcode_input').focus();
                }
            }
        });

        // Cleanup
        window.addEventListener('beforeunload', () => {
            clearTimeout(debounceTimeout);
        });

        // Barcode Scanner Function using HTML5 QR Code
        function initBarcodeScanner(inputId) {
            // Check if scanner modal already exists
            if (document.getElementById('barcode-scanner-modal')) {
                return;
            }

            const scannerModal = document.createElement('div');
            scannerModal.id = 'barcode-scanner-modal';
            scannerModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90';
            scannerModal.innerHTML = `
                <div class="bg-white rounded-lg p-4 w-full max-w-lg mx-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">{{ __('messages.Scan Barcode') }}</h3>
                        <button type="button" id="close-scanner" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="scanner-container" class="relative bg-black rounded-lg overflow-hidden" style="height: 350px;"></div>
                    <p class="text-sm text-gray-500 mt-2 text-center">{{ __('messages.Point camera at barcode') }}</p>
                </div>
            `;
            document.body.appendChild(scannerModal);

            const inputElement = document.getElementById(inputId);
            let hasScanned = false;
            let html5Qrcode = null;

            // Use HTML5 QR Code - Direct camera start
            try {
                const scannerContainer = document.getElementById('scanner-container');

                // Create video element for camera
                const videoElement = document.createElement('video');
                videoElement.style.width = '100%';
                videoElement.style.height = '100%';
                videoElement.style.objectFit = 'cover';
                videoElement.setAttribute('playsinline', 'true');
                scannerContainer.appendChild(videoElement);

                html5Qrcode = new Html5Qrcode("scanner-container");

                // Start camera directly
                html5Qrcode.start({
                        facingMode: "environment"
                    }, {
                        fps: 10,
                        qrbox: {
                            width: 250,
                            height: 150
                        },
                        aspectRatio: 1.0
                    },
                    (decodedText, decodedResult) => {
                        if (hasScanned) return;

                        const code = decodedText.trim();

                        // Validate: code should be at least 4 characters
                        if (code.length < 4) {
                            return;
                        }

                        hasScanned = true;

                        // Stop scanner
                        html5Qrcode.stop().then(() => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                            // Trigger Enter key event
                            const enterEvent = new KeyboardEvent('keydown', {
                                key: 'Enter',
                                keyCode: 13,
                                which: 13,
                                bubbles: true
                            });
                            inputElement.dispatchEvent(enterEvent);
                        }).catch(err => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                            const enterEvent = new KeyboardEvent('keydown', {
                                key: 'Enter',
                                keyCode: 13,
                                which: 13,
                                bubbles: true
                            });
                            inputElement.dispatchEvent(enterEvent);
                        });
                    },
                    (errorMessage) => {
                        // Parse error, ignore
                    }
                ).catch(err => {
                    console.error('Camera start error:', err);
                    alert('{{ __('messages.Camera access denied or not available') }}');
                    scannerModal.remove();
                });

            } catch (err) {
                console.error('HTML5 QR Code scanner error:', err);
                alert('{{ __('messages.Camera access denied or not available') }}');
                scannerModal.remove();
            }

            document.getElementById('close-scanner').addEventListener('click', function() {
                if (html5Qrcode) {
                    html5Qrcode.stop().then(() => {
                        scannerModal.remove();
                    }).catch(err => {
                        scannerModal.remove();
                    });
                } else {
                    scannerModal.remove();
                }
            });

            scannerModal.addEventListener('click', function(e) {
                if (e.target === scannerModal) {
                    if (html5Qrcode) {
                        html5Qrcode.stop().then(() => {
                            scannerModal.remove();
                        }).catch(err => {
                            scannerModal.remove();
                        });
                    } else {
                        scannerModal.remove();
                    }
                }
            });
        }

        // Initialize scanner button
        document.addEventListener('DOMContentLoaded', function() {
            const scanBtn = document.getElementById('scan-barcode-btn');
            if (scanBtn) {
                scanBtn.addEventListener('click', function() {
                    initBarcodeScanner('barcode_input');
                });
            }
        });
    </script>
</x-app-layout>
