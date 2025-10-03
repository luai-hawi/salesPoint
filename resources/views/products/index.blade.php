@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                {{ __('messages.Product Management') }}
            </h2>
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                {{ __('messages.Total Products:') }} <span class="font-bold text-blue-600">{{ $products->total() }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="w-full px-4 sm:px-6 lg:px-8 max-w-none">
            
            <!-- Search and Actions Bar -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-md">
                        <input
                            type="text"
                            id="product-search"
                            name="search"
                            placeholder="{{ __('messages.Search by name, barcode, or price...') }}"
                            class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            value="{{ request('search') }}"
                        />
                        <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    <!-- Low Stock Filter -->
                    <div class="flex items-center ml-4">
                        <label for="low-stock-filter" class="relative cursor-pointer">
                            <input type="checkbox" id="low-stock-filter" class="sr-only" {{ request('low_stock') ? 'checked' : '' }}>
                            <div class="w-12 h-7 bg-gradient-to-r from-gray-300 to-gray-400 rounded-full shadow-inner cursor-pointer transition-all duration-300 ease-in-out" id="toggle-bg"></div>
                            <div class="absolute top-1 left-1 w-5 h-5 bg-white rounded-full shadow-lg transform transition-all duration-300 ease-in-out flex items-center justify-center" id="toggle-circle">
                                <svg class="w-3 h-3 text-orange-500 opacity-0 transition-opacity duration-200" id="warning-icon" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </label>
                        <label for="low-stock-filter" class="ml-3 text-sm font-semibold text-gray-700 cursor-pointer transition-all duration-200 hover:text-orange-600 select-none">{{ __('messages.Show low stock only') }}</label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <a href="{{ route('products.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('messages.Add New Product') }}
                        </a>
                        
                        <button id="view-order-list-btn" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span id="order-count-badge" class="hidden bg-white bg-opacity-20 text-xs font-bold px-2 py-1 rounded-full ml-1">0</span>
                            {{ __('messages.Order List') }}
                        </button>

                        <a href="{{ route('products.export') }}" class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            {{ __('messages.Export CSV') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        {{ __('messages.Product Inventory') }}
                    </h3>
                </div>

                <div id="products-container" class="p-6">
                    <!-- Updated grid with more columns for smaller cards -->
                    <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4">
                        @foreach($products as $product)
                            @include('products.partials.product-card', ['product' => $product])
                        @endforeach
                    </div>

                    <!-- Loading State -->
                    <div id="loading-indicator" class="hidden text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="text-gray-500 mt-2">{{ __('messages.Loading products...') }}</p>
                    </div>

                    <!-- Empty State -->
                    @if($products->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('messages.No products found') }}</h3>
                            <p class="mt-2 text-gray-500">{{ __('messages.Get started by creating your first product.') }}</p>
                            <div class="mt-6">
                                <a href="{{ route('products.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                    {{ __('messages.Add Product') }}
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Pagination -->
                @if($products->hasPages())
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-100" id="pagination-links">
                        {{ $products->links('vendor.pagination.custom-light') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bulk Add Stock Modal -->
    <div id="bulk-add-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ __('messages.Add Stock') }}
                            </h3>
                            <div class="mt-4">
                                <p class="text-sm text-gray-500 mb-4" id="product-name-display"></p>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Quantity to Add') }}</label>
                                        <input type="number" id="add-quantity" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Cost Price per Unit') }}</label>
                                        <input type="number" id="add-cost-price" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="confirm-add-stock" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('messages.Add Stock') }}
                    </button>
                    <button type="button" id="cancel-add-stock" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('messages.Cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Order List Modal -->
    <div id="order-list-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="order-modal-title">
                                {{ __('messages.Order List') }}
                            </h3>
                            <div class="mt-4">
                                <div id="order-items-container" class="space-y-3 max-h-96 overflow-y-auto">
                                    <!-- Order items will be added here -->
                                    <div id="empty-order-message" class="text-center py-8">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('messages.No items in order list') }}</h3>
                                        <p class="mt-1 text-sm text-gray-500">{{ __('messages.Add some products to your order first.') }}</p>
                                    </div>
                                </div>

                                <!-- Order Summary -->
                                <div id="order-summary" class="hidden mt-4 p-4 bg-gray-50 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Order Summary') }}</h4>
                                    <div class="flex justify-between text-sm">
                                        <span>{{ __('messages.Total Items') }}:</span>
                                        <span id="total-order-items" class="font-medium">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="print-order-btn" class="hidden w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        {{ __('messages.Print Order List') }}
                    </button>
                    <button type="button" id="clear-order-btn" class="hidden mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        {{ __('messages.Clear Order') }}
                    </button>
                    <button type="button" id="close-order-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('messages.Cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .product-card {
            transition: all 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .success-flash {
            animation: flash 0.6s ease-in-out;
        }

        @keyframes flash {
            0%, 100% { background-color: transparent; }
            50% { background-color: #dcfce7; }
        }

        /* Responsive grid adjustments */
        @media (max-width: 640px) {
            #products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            #products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            #products-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1025px) and (max-width: 1280px) {
            #products-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        @media (min-width: 1281px) and (max-width: 1536px) {
            #products-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }

        @media (min-width: 1537px) {
            #products-grid {
                grid-template-columns: repeat(8, minmax(0, 1fr));
            }
        }
    </style>

    <script>
        const typingDelay = 500;
        let typingTimer;
        let currentProductId = null;

        // Search functionality
        document.getElementById('product-search').addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const query = this.value.trim();
                const url = new URL('{{ route('products.index') }}', window.location.origin);
                if (query.length > 0) {
                    url.searchParams.set('search', query);
                } else {
                    url.searchParams.delete('search');
                }
                const lowStockChecked = document.getElementById('low-stock-filter').checked;
                if (lowStockChecked) {
                    url.searchParams.set('low_stock', '1');
                } else {
                    url.searchParams.delete('low_stock');
                }
                loadProducts(url.toString());
            }, typingDelay);
        });

        // Function to update toggle visual state
        function updateToggleState(checked) {
            const toggleBg = document.getElementById('toggle-bg');
            const toggleCircle = document.getElementById('toggle-circle');
            const warningIcon = document.getElementById('warning-icon');

            if (checked) {
                toggleBg.className = 'w-12 h-7 bg-gradient-to-r from-orange-400 to-red-500 rounded-full shadow-inner cursor-pointer transition-all duration-300 ease-in-out shadow-orange-200';
                toggleCircle.className = 'absolute top-1 right-1 w-5 h-5 bg-white rounded-full shadow-lg transform transition-all duration-300 ease-in-out flex items-center justify-center';
                warningIcon.classList.remove('opacity-0');
                warningIcon.classList.add('opacity-100');
            } else {
                toggleBg.className = 'w-12 h-7 bg-gradient-to-r from-gray-300 to-gray-400 rounded-full shadow-inner cursor-pointer transition-all duration-300 ease-in-out';
                toggleCircle.className = 'absolute top-1 left-1 w-5 h-5 bg-white rounded-full shadow-lg transform transition-all duration-300 ease-in-out flex items-center justify-center';
                warningIcon.classList.remove('opacity-100');
                warningIcon.classList.add('opacity-0');
            }
        }

        // Low stock filter functionality
        document.getElementById('low-stock-filter').addEventListener('change', function() {
            updateToggleState(this.checked);

            const query = document.getElementById('product-search').value.trim();
            const url = new URL('{{ route('products.index') }}', window.location.origin);
            if (query.length > 0) {
                url.searchParams.set('search', query);
            } else {
                url.searchParams.delete('search');
            }
            if (this.checked) {
                url.searchParams.set('low_stock', '1');
            } else {
                url.searchParams.delete('low_stock');
            }
            loadProducts(url.toString());
        });

        // Initialize toggle state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('low-stock-filter');
            updateToggleState(checkbox.checked);
        });

        // Load products via AJAX
        function loadProducts(url) {
            document.getElementById('loading-indicator').classList.remove('hidden');

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newGrid = doc.querySelector('#products-grid');
                const newPagination = doc.querySelector('#pagination-links');

                if (newGrid) {
                    document.getElementById('products-grid').innerHTML = newGrid.innerHTML;
                }
                if (newPagination) {
                    const paginationContainer = document.getElementById('pagination-links');
                    if (paginationContainer) {
                        paginationContainer.innerHTML = newPagination.innerHTML;
                    }
                }

                // Update checkbox state based on URL
                const urlObj = new URL(url);
                const lowStockParam = urlObj.searchParams.get('low_stock');
                const checkbox = document.getElementById('low-stock-filter');
                checkbox.checked = lowStockParam === '1';
                updateToggleState(checkbox.checked);

                attachEventListeners();
                attachPaginationLinks();
            })
            .catch(error => {
                console.error('Error loading products:', error);
                showNotification("{{ __('messages.Error loading products') }}", 'error');
            })
            .finally(() => {
                document.getElementById('loading-indicator').classList.add('hidden');
            });
        }

        // Modal functionality
        function showAddStockModal(productId, productName) {
            currentProductId = productId;
            document.getElementById('product-name-display').textContent = `{{ __('messages.Adding stock for: ') }}${productName}`;
            document.getElementById('add-quantity').value = 1;
            document.getElementById('add-cost-price').value = '';
            document.getElementById('bulk-add-modal').classList.remove('hidden');
        }

        function hideAddStockModal() {
            document.getElementById('bulk-add-modal').classList.add('hidden');
            currentProductId = null;
        }

        // Add stock functionality
        document.getElementById('confirm-add-stock').addEventListener('click', function() {
            const quantity = parseInt(document.getElementById('add-quantity').value);
            const costPrice = parseFloat(document.getElementById('add-cost-price').value);

            if (!quantity || quantity < 1) {
                showNotification("{{ __('messages.Please enter a valid quantity') }}", 'error');
                return;
            }

            if (!costPrice || costPrice <= 0) {
                showNotification("{{ __('messages.Please enter a valid cost price') }}", 'error');
                return;
            }

            fetch('/batches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    product_id: currentProductId,
                    quantity: quantity,
                    cost_price: costPrice
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success !== false) {
                    // Update the product card
                    const productCard = document.querySelector(`[data-product-id="${currentProductId}"]`);
                    if (productCard) {
                        const quantityDisplay = productCard.querySelector('.quantity-display');
                        
                        if (quantityDisplay && data.updated_quantity !== undefined) {
                            quantityDisplay.textContent = data.updated_quantity;
                            quantityDisplay.parentElement.classList.add('success-flash');
                            setTimeout(() => {
                                quantityDisplay.parentElement.classList.remove('success-flash');
                            }, 600);
                        }
                    }
                    
                    showNotification("{{ __('messages.Stock added successfully!') }}", 'success');
                    hideAddStockModal();
                } else {
                    showNotification("{{ __('messages.Failed to add stock') }}", 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification("{{ __('messages.Failed to add stock') }}", 'error');
            });
        });

        // Modal close handlers
        document.getElementById('cancel-add-stock').addEventListener('click', hideAddStockModal);
        document.querySelector('#bulk-add-modal .bg-opacity-75').addEventListener('click', hideAddStockModal);

        // Delete confirmation
        function confirmDelete(productId, productName) {
            if (confirm(`{{ __('messages.Are you sure you want to delete') }} "${productName}"? {{ __('messages.This action cannot be undone.') }}`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/products/${productId}`;
                form.innerHTML = `
                    @csrf
                    @method('DELETE')
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.remove('translate-x-full'), 100);
            
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Attach event listeners
        function attachEventListeners() {
            // Add stock buttons
            document.querySelectorAll('.add-stock-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    showAddStockModal(productId, productName);
                });
            });

            // Delete buttons
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    confirmDelete(productId, productName);
                });
            });
        }

        // Attach pagination links
        function attachPaginationLinks() {
            document.querySelectorAll('#pagination-links a').forEach(link => {
                link.onclick = function(e) {
                    e.preventDefault();
                    loadProducts(this.href);
                };
            });
        }

        // Order List Management
        let orderList = [];

        // Add to order functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('add-to-order-btn')) {
                const productId = e.target.dataset.productId;
                const productName = e.target.dataset.productName;
                const productPrice = parseFloat(e.target.dataset.productPrice);
                const productImage = e.target.dataset.productImage;

                addToOrderList(productId, productName, productPrice, productImage);
                updateOrderBadge();
                showNotification(`{{ __('messages.Added') }} "${productName}" {{ __('messages.to order list') }}`, 'success');
            }
        });

        function addToOrderList(productId, productName, productPrice, productImage) {
            const existingItem = orderList.find(item => item.id === productId);

            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                orderList.push({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    image: productImage,
                    quantity: 1,
                    notes: ''
                });
            }

            renderOrderList();
        }

        function removeFromOrderList(productId) {
            orderList = orderList.filter(item => item.id !== productId);
            renderOrderList();
            updateOrderBadge();
        }

        function updateOrderQuantity(productId, newQuantity) {
            const item = orderList.find(item => item.id === productId);
            if (item && newQuantity > 0) {
                item.quantity = parseInt(newQuantity);
                renderOrderList();
            }
        }

        function updateOrderNotes(productId, newNotes) {
            const item = orderList.find(item => item.id === productId);
            if (item) {
                item.notes = newNotes;
                // No need to re-render for notes changes
            }
        }

        function clearOrderList() {
            orderList = [];
            renderOrderList();
            updateOrderBadge();
        }

        function renderOrderList() {
            const container = document.getElementById('order-items-container');
            const emptyMessage = document.getElementById('empty-order-message');
            const summary = document.getElementById('order-summary');
            const printBtn = document.getElementById('print-order-btn');
            const clearBtn = document.getElementById('clear-order-btn');

            if (orderList.length === 0) {
                emptyMessage.classList.remove('hidden');
                summary.classList.add('hidden');
                printBtn.classList.add('hidden');
                clearBtn.classList.add('hidden');
                return;
            }

            emptyMessage.classList.add('hidden');
            summary.classList.remove('hidden');
            printBtn.classList.remove('hidden');
            clearBtn.classList.remove('hidden');

            let totalItems = 0;
            const itemsHtml = orderList.map(item => {
                totalItems += item.quantity;
                return `
                    <div class="p-4 bg-white border border-gray-200 rounded-lg space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">${item.name}</h4>
                                <p class="text-xs text-gray-500">₪${item.price.toFixed(2)} each</p>
                            </div>
                            <button class="remove-from-order text-red-500 hover:text-red-700 p-1" data-product-id="${item.id}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <label class="text-xs text-gray-600 mr-2">{{ __('messages.Quantity Needed') }}:</label>
                                <input type="number" min="1" value="${item.quantity}"
                                       class="order-quantity w-16 px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:border-purple-500"
                                       data-product-id="${item.id}">
                            </div>
                            <div class="flex-1">
                                <label class="text-xs text-gray-600 block mb-1">{{ __('messages.Notes') }} ({{ __('messages.Optional') }}):</label>
                                <input type="text" value="${item.notes || ''}" placeholder="{{ __('messages.Add notes...') }}"
                                       class="order-notes w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:border-purple-500"
                                       data-product-id="${item.id}">
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = itemsHtml + '<div id="empty-order-message" class="hidden">' + emptyMessage.innerHTML + '</div>';

            document.getElementById('total-order-items').textContent = totalItems;
        }

        function updateOrderBadge() {
            const badge = document.getElementById('order-count-badge');
            const count = orderList.length;

            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        // Order modal functionality
        document.getElementById('view-order-list-btn').addEventListener('click', function() {
            document.getElementById('order-list-modal').classList.remove('hidden');
        });

        document.getElementById('close-order-modal').addEventListener('click', function() {
            document.getElementById('order-list-modal').classList.add('hidden');
        });

        document.querySelector('#order-list-modal .bg-opacity-75').addEventListener('click', function() {
            document.getElementById('order-list-modal').classList.add('hidden');
        });

        // Clear order functionality
        document.getElementById('clear-order-btn').addEventListener('click', function() {
            if (confirm('{{ __("messages.Are you sure you want to clear the order list?") }}')) {
                clearOrderList();
                showNotification('{{ __("messages.Order list cleared") }}', 'info');
            }
        });

        // Remove from order functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-from-order') || e.target.closest('.remove-from-order')) {
                const button = e.target.classList.contains('remove-from-order') ? e.target : e.target.closest('.remove-from-order');
                const productId = button.dataset.productId;
                const productName = orderList.find(item => item.id === productId)?.name;
                removeFromOrderList(productId);
                showNotification(`{{ __('messages.Removed') }} "${productName}" {{ __('messages.from order list') }}`, 'info');
            }
        });

        // Update quantity functionality
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('order-quantity')) {
                const productId = e.target.dataset.productId;
                const newQuantity = parseInt(e.target.value);
                updateOrderQuantity(productId, newQuantity);
            } else if (e.target.classList.contains('order-notes')) {
                const productId = e.target.dataset.productId;
                const newNotes = e.target.value;
                updateOrderNotes(productId, newNotes);
            }
        });

        // Print functionality
        document.getElementById('print-order-btn').addEventListener('click', function() {
            printOrderList();
        });

        function printOrderList() {
            const printWindow = window.open('', '_blank');
            const currentDate = new Date().toLocaleDateString();
            const currentTime = new Date().toLocaleTimeString();

            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>{{ __('messages.Order List') }}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #7c3aed; text-align: center; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .order-item { display: flex; align-items: center; border: 1px solid #ddd; margin-bottom: 10px; padding: 10px; page-break-inside: avoid; }
                        .product-image { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 15px; border: 1px solid #eee; }
                        .product-info { flex: 1; }
                        .product-name { font-weight: bold; margin-bottom: 5px; }
                        .quantity-section { text-align: center; min-width: 80px; }
                        .quantity-badge { background: #7c3aed; color: white; padding: 4px 8px; border-radius: 12px; font-weight: bold; }
                        .summary { background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 5px; }
                        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                        @media print {
                            body { margin: 0; }
                            .order-item { break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>{{ __('messages.Order List') }}</h1>
                        <p>{{ __('messages.Generated on') }}: ${currentDate} ${currentTime}</p>
                    </div>
            `;

            let totalItems = 0;
            orderList.forEach(item => {
                totalItems += item.quantity;

                printContent += `
                    <div class="order-item">
                        ${item.image ? `<img src="${item.image}" alt="${item.name}" class="product-image">` : '<div class="product-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;"><span style="color: #999; font-size: 24px;">📦</span></div>'}
                        <div class="product-info">
                            <div class="product-name">${item.name}</div>
                            ${item.notes ? `<div class="product-notes" style="font-size: 11px; color: #666; margin-top: 2px; font-style: italic;">${item.notes}</div>` : ''}
                        </div>
                        <div class="quantity-section">
                            <div class="quantity-badge">${item.quantity}</div>
                        </div>
                    </div>
                `;
            });

            printContent += `
                    <div class="summary">
                        <strong>{{ __('messages.Order Summary') }}</strong><br>
                        {{ __('messages.Total Items') }}: ${totalItems}
                    </div>
                    <div class="footer">
                        <p>{{ __('messages.Printed from') }} {{ config('app.name') }}</p>
                    </div>
                </body>
                </html>
            `;

            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            attachEventListeners();
            attachPaginationLinks();
            updateOrderBadge();
        });
    </script>
</x-app-layout>