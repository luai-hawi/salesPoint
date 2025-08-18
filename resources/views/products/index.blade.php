<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                {{ __('Product Management') }}
            </h2>
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                Total Products: <span class="font-bold text-blue-600">{{ $products->total() }}</span>
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
                            placeholder="Search by name, barcode, or price..." 
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                            value="{{ request('search') }}"
                        />
                        <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <a href="{{ route('products.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Product
                        </a>
                        
                        <a href="{{ route('products.export') }}" class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export CSV
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
                        Product Inventory
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
                        <p class="text-gray-500 mt-2">Loading products...</p>
                    </div>

                    <!-- Empty State -->
                    @if($products->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">No products found</h3>
                            <p class="mt-2 text-gray-500">Get started by creating your first product.</p>
                            <div class="mt-6">
                                <a href="{{ route('products.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                    Add Product
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
                                Add Stock
                            </h3>
                            <div class="mt-4">
                                <p class="text-sm text-gray-500 mb-4" id="product-name-display"></p>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity to Add</label>
                                        <input type="number" id="add-quantity" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Cost Price per Unit</label>
                                        <input type="number" id="add-cost-price" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="confirm-add-stock" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Add Stock
                    </button>
                    <button type="button" id="cancel-add-stock" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
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
                loadProducts(url.toString());
            }, typingDelay);
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
                
                attachEventListeners();
                attachPaginationLinks();
            })
            .catch(error => {
                console.error('Error loading products:', error);
                showNotification('Failed to load products', 'error');
            })
            .finally(() => {
                document.getElementById('loading-indicator').classList.add('hidden');
            });
        }

        // Modal functionality
        function showAddStockModal(productId, productName) {
            currentProductId = productId;
            document.getElementById('product-name-display').textContent = `Adding stock for: ${productName}`;
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
                showNotification('Please enter a valid quantity', 'error');
                return;
            }

            if (!costPrice || costPrice <= 0) {
                showNotification('Please enter a valid cost price', 'error');
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
                    
                    showNotification('Stock added successfully!', 'success');
                    hideAddStockModal();
                } else {
                    showNotification('Failed to add stock', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to add stock', 'error');
            });
        });

        // Modal close handlers
        document.getElementById('cancel-add-stock').addEventListener('click', hideAddStockModal);
        document.querySelector('#bulk-add-modal .bg-opacity-75').addEventListener('click', hideAddStockModal);

        // Delete confirmation
        function confirmDelete(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
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

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            attachEventListeners();
            attachPaginationLinks();
        });
    </script>
</x-app-layout>