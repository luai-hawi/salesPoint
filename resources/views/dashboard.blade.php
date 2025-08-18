<x-app-layout>
    <x-slot name="header">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <h2 class="font-bold text-xl sm:text-2xl text-gray-800 leading-tight flex items-center">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 mr-2 sm:mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            {{ __('Point of Sale Dashboard') }}
        </h2>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
            <div class="text-xs sm:text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
                Today's Sales: <span class="font-bold text-green-600">${{ number_format($totalToday ?? 0, 2) }}</span>
            </div>
        </div>
    </div>
</x-slot>

    <!-- Enhanced Layout with Full Screen Width -->
    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 max-w-none">
                
                <!-- Left Panel - Product Search & Selection -->
                <div class="lg:col-span-5 space-y-4">
                    <!-- Search Controls -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Product Search</h3>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative mb-4">
                            <input 
                                type="text" 
                                id="product-search" 
                                placeholder="Search products by name or barcode..." 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                            <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                        <!-- Filter Options -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button id="filter-all" class="filter-btn active px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200 transition-colors">
                                All Products
                            </button>
                            <button id="filter-in-stock" class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                In Stock Only
                            </button>
                            <button id="filter-out-of-stock" class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                Out of Stock
                            </button>
                        </div>
                    </div>

                    <!-- Product Results -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-4 border-b border-gray-100">
                            <h4 class="font-medium text-gray-800 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                Available Products
                            </h4>
                        </div>
                        <div id="product-cards-container" class="max-h-96 overflow-y-auto scroll-container">
                            <div id="product-results" class="grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 p-4 products-grid">
                                <!-- Products will be loaded here -->
                            </div>
                            <div id="loading-indicator" class="hidden p-4 text-center">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                                <p class="text-sm text-gray-500 mt-2">Loading products...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content - Bill Creation -->
                <div class="lg:col-span-4 space-y-4">
                    <!-- Barcode Scanner -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Quick Scanner</h3>
                        </div>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="barcode_input" 
                                placeholder="Scan or enter barcode..." 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors font-mono"
                                autocomplete="off"
                            />
                            <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Bill Form -->
                    <div id="printable" class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Create New Bill
                            </h3>
                        </div>
                        
                        <form id="create-bill" method="POST" action="{{ route('bills.store') }}" class="p-6">
                            @csrf

                            <!-- Customer Selection -->
                            <div class="mb-6">
                                <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-2">Customer (Optional)</label>
                                <select name="customer_id" id="customer_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <option value="">-- Walk-in Customer --</option>
                                    @foreach($customers as $customer)
                                        <option data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }} @if($customer->phone) - {{ $customer->phone }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Note -->
                            <div class="mb-6">
                                <label for="note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                                <textarea name="note" id="note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Add any notes for this bill..."></textarea>
                            </div>

                            <!-- Damaged Checkbox -->
                            <div class="mb-6 flex items-center p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <input type="checkbox" name="is_damaged" id="is_damaged" class="h-4 w-4 text-amber-600 border-amber-300 rounded focus:ring-amber-500">
                                <label for="is_damaged" class="ml-2 text-sm text-amber-800 font-medium">Mark as Damaged Bill (100% discount)</label>
                            </div>

                            <!-- Products List -->
                            <div id="products-list" class="space-y-3 mb-6">
                                <!-- Products will be added here dynamically -->
                            </div>

                            <!-- Add Product Button -->
                            <button type="button" id="add-product-row" class="w-full mb-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Product Manually
                            </button>

                            <!-- Action Buttons -->
                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Create Bill (F2)
                                </button>
                                <button type="button" id="clear-all" class="bg-red-600 hover:bg-red-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                                <button type="button" id="print-button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Panel - Totals & Summary -->
                <div class="lg:col-span-3 space-y-4">
                    <!-- Bill Summary -->
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-sm text-white p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            Bill Summary
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">Total Discount:</span>
                                    <span class="font-bold text-lg">$<span id="total_discount_display">0.00</span></span>
                                </div>
                                <input type="hidden" id="total_discount" value="0">
                            </div>
                            
                            <div class="bg-white bg-opacity-30 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">Total Amount:</span>
                                    <span class="font-bold text-2xl">$<span id="total_price_display">0.00</span></span>
                                </div>
                                <input type="hidden" id="total_price" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Today's Sales -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            Today's Performance
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="text-sm text-blue-700">Total Sales:</span>
                                <span class="font-bold text-blue-800">${{ number_format($totalToday ?? 0, 2) }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-700">Bills Created:</span>
                                <span class="font-bold text-gray-800" id="bills_count">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Quick Actions
                        </h3>
                        
                        <div class="space-y-2">
                            <a href="{{ route('bills.index') }}" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                View All Bills
                            </a>
                            
                            <a href="{{ route('products.index') }}" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                Manage Products
                            </a>
                            
                            <a href="{{ route('customers.index') }}" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                Manage Customers
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Printable Invoice --}}
    <div id="print-area" class="print-hidden p-6 text-sm">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">Bee Phone</h1>
            <p>{{ now()->format('Y-m-d H:i') }}</p>
            <hr class="my-2">
        </div>
        <div id="print-customer" class="font-semibold text-left"></div>
        <div id="print-customer-phone" class="font-semibold text-left"></div>

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

    {{-- Enhanced Performance Styles --}}
    <style>
        /* Filter button styles */
        .filter-btn.active {
            background-color: rgb(59 130 246);
            color: white;
            border-color: rgb(59 130 246);
        }

        /* Optimized scrolling container */
        .scroll-container {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            overflow-y: auto;
            /* Enable hardware acceleration for scrolling */
            transform: translate3d(0, 0, 0);
            will-change: scroll-position;
            /* Add momentum scrolling for iOS */
            -webkit-transform: translate3d(0, 0, 0);
            /* Optimize for scrolling performance */
            contain: layout style paint;
        }

        /* Optimized grid container */
        .products-grid {
            /* Use CSS Grid with GPU acceleration */
            transform: translate3d(0, 0, 0);
            will-change: contents;
            /* Enable CSS containment for better performance */
            contain: layout style;
            /* Optimize paint and layout operations */
            backface-visibility: hidden;
            perspective: 1000px;
        }

        /* High-performance product cards */
        .product-card {
            /* Minimal transitions for better performance */
            transition: transform 0.1s ease-out, box-shadow 0.1s ease-out;
            /* Enable hardware acceleration */
            transform: translate3d(0, 0, 0);
            will-change: transform;
            /* Optimize for paint and layout */
            contain: layout style paint;
            backface-visibility: hidden;
            /* Reduce browser reflow/repaint */
            overflow: hidden;
            position: relative;
        }

        .product-card:hover {
            /* Lightweight hover effect */
            transform: translate3d(0, -1px, 0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .product-card.out-of-stock {
            opacity: 0.7;
            /* Disable hover effects for out-of-stock items */
            pointer-events: none;
        }

        .product-card.out-of-stock:hover {
            transform: translate3d(0, 0, 0);
            box-shadow: none;
        }

        /* Optimized image rendering */
        .product-card img {
            /* Fast image transitions */
            transition: transform 0.15s ease-out;
            transform: translate3d(0, 0, 0);
            /* Improve image rendering performance */
            image-rendering: auto;
            backface-visibility: hidden;
        }

        .product-card:hover img {
            transform: translate3d(0, 0, 0) scale(1.02);
        }

        /* Optimize text rendering */
        .product-card .text-sm {
            /* Prevent text selection for better performance */
            user-select: none;
            -webkit-user-select: none;
            /* Optimize text rendering */
            text-rendering: optimizeSpeed;
        }

        /* Virtual scrolling optimization */
        .product-card {
            /* Fixed height for better virtualization */
            min-height: 120px;
            /* Optimize for CSS Grid auto-sizing */
            height: auto;
        }

        /* Reduce paint complexity */
        .product-card * {
            /* Optimize all child elements */
            transform: translate3d(0, 0, 0);
        }

        /* Product row animations (keep minimal) */
        .product-row {
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translate3d(0, -5px, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        /* Barcode duplicate selection modal */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        /* Optimize for mobile scrolling */
        @media (max-width: 768px) {
            .scroll-container {
                /* Enhanced mobile scrolling */
                -webkit-overflow-scrolling: touch;
                overflow-scrolling: touch;
            }
            
            .products-grid {
                /* Reduce grid complexity on mobile */
                grid-template-columns: repeat(2, 1fr);
            }
            
            .product-card {
                /* Disable hover effects on mobile */
                transition: none;
            }
            
            .product-card:hover {
                transform: translate3d(0, 0, 0);
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }

        /* Print optimizations remain the same */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            body * {
                visibility: hidden !important;
                height: 0 !important;
                overflow: hidden !important;
            }

            #print-area, #print-area * {
                visibility: visible !important;
                height: auto !important;
                overflow: visible !important;
            }

            #print-area {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding: 0.5cm !important;
                background: white;
            }

            html, body {
                height: auto !important;
                overflow: visible !important;
            }

            #print-area table {
                page-break-inside: auto;
            }

            #print-area tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .product-row, .x-block, .py-12, .flex, form {
                display: none !important;
            }
        }

        #print-area {
            display: none;
        }

        @media print {
            #print-area {
                display: block !important;
            }
        }
    </style>

    <!-- Barcode Duplicate Selection Modal -->
    <div id="barcode-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="modal-overlay fixed inset-0 transition-opacity" aria-hidden="true"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Multiple Products Found
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Multiple products were found with barcode "<span id="duplicate-barcode"></span>". Please select which product you want to add:
                                </p>
                            </div>
                            <div id="duplicate-products" class="mt-4 space-y-2">
                                <!-- Duplicate products will be listed here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="close-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Enhanced High-Performance JavaScript --}}
    <script>
        const products = @json($products);
        const totalSalesToday = {{ $totalToday ?? 0 }};
        const productsList = document.getElementById('products-list');

        // State management
        let currentFilter = 'all';
        let debounceTimeout = null;
        let currentPage = 1;
        let hasMore = true;
        let isLoading = false;
        let searchTerm = '';
        
        // Performance optimization variables
        let scrollTimeout = null;
        let renderQueue = [];
        let isRenderingQueue = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('barcode_input').focus();
            fetchProducts(true);
            updateTotalSalesToday();
            setupIntersectionObserver();
        });

        // Intersection Observer for better scroll performance
        let intersectionObserver;
        function setupIntersectionObserver() {
            const options = {
                root: document.getElementById('product-cards-container'),
                rootMargin: '50px',
                threshold: 0.1
            };

            intersectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target.querySelector('img[data-src]');
                        if (img) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            intersectionObserver.unobserve(entry.target);
                        }
                    }
                });
            }, options);
        }



        
        // Enhanced barcode input handler with duplicate detection
        document.getElementById('barcode_input').addEventListener('keydown', async e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                console.log("Barcode input detected:", e.target.value);
                const code = e.target.value.trim();
                if (!code) return;

                try {
                    const response = await fetch(`/products/search?barcode=${encodeURIComponent(code)}`);
                    if (!response.ok) {
                        showNotification('Error fetching product from server.', 'error');
                        return;
                    }
                    const result = await response.json();

                    if (result && result.multiple_products) {
                        showBarcodeModal(result.products, result.barcode);
                        e.target.value = '';
                    } else if (result && result.id) {
                        addProductRow(result);
                        e.target.value = '';
                        showNotification(`Added ${result.name} to bill`, 'success');
                    } else {
                        showNotification('Product not found for barcode: ' + code, 'warning');
                    }
                } catch (err) {
                    console.error('Fetch error:', err);
                    showNotification('Failed to fetch product data.', 'error');
                }
            }
        });

        // Show modal for duplicate barcodes
        function showBarcodeModal(products, barcode) {
            const modal = document.getElementById('barcode-modal');
            const duplicateBarcode = document.getElementById('duplicate-barcode');
            const duplicateProducts = document.getElementById('duplicate-products');
            
            duplicateBarcode.textContent = barcode;
            duplicateProducts.innerHTML = '';
            
            products.forEach(product => {
                const productDiv = document.createElement('div');
                productDiv.className = 'flex items-center justify-between p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors';
                productDiv.innerHTML = `
                    <div class="flex-1">
                        <div class="font-medium text-gray-900">${product.name}</div>
                        <div class="text-sm text-gray-500">Price: ${product.selling_price} | Stock: ${product.quantity}</div>
                    </div>
                    <button class="select-duplicate-product bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm" data-product='${JSON.stringify(product)}'>
                        Select
                    </button>
                `;
                duplicateProducts.appendChild(productDiv);
            });
            
            modal.classList.remove('hidden');
        }

        // Handle duplicate product selection
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('select-duplicate-product')) {
                const product = JSON.parse(e.target.dataset.product);
                addProductRow(product);
                closeBarcodeModal();
                showNotification(`Added ${product.name} to bill`, 'success');
            }
        });

        // Close modal handlers
        function closeBarcodeModal() {
            document.getElementById('barcode-modal').classList.add('hidden');
        }

        document.getElementById('close-modal').addEventListener('click', closeBarcodeModal);
        document.querySelector('.modal-overlay').addEventListener('click', closeBarcodeModal);

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                
                currentFilter = e.target.id.replace('filter-', '');
                searchTerm = document.getElementById('product-search').value.trim();
                fetchProducts(true);
            });
        });

        // Enhanced product search with debouncing
        document.getElementById('product-search').addEventListener('input', function () {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                searchTerm = this.value.trim();
                currentPage = 1;
                hasMore = true;
                fetchProducts(true);
            }, 300);
        });

        // Optimized product fetching with batching
        function fetchProducts(reset = false) {
            if (isLoading || !hasMore) return;
            isLoading = true;

            if (reset) {
                const container = document.getElementById('product-results');
                // Use replaceChildren for better performance
                container.replaceChildren();
                currentPage = 1;
                hasMore = true;
                showLoadingIndicator(true);
            }

            const params = new URLSearchParams({
                search: searchTerm,
                page: currentPage,
                filter: currentFilter,
                per_page: 12 // Reduced for better performance
            });

            fetch(`/products/searchAll?${params}`)
                .then(response => {
                    if (!response.ok) throw new Error('Search failed');
                    return response.json();
                })
                .then(data => {
                    const products = data.data || [];
                    
                    if (products.length === 0 && currentPage === 1) {
                        document.getElementById('product-results').innerHTML = 
                            '<p class="text-gray-500 text-center py-4 col-span-full">No products found</p>';
                        hasMore = false;
                        return;
                    }

                    // Batch render products for better performance
                    const filteredProducts = filterProducts(products);
                    batchRenderProducts(filteredProducts);

                    hasMore = data.current_page < data.last_page;
                    currentPage++;
                })
                .catch(error => {
                    if (currentPage === 1) {
                        document.getElementById('product-results').innerHTML = 
                            '<p class="text-red-500 text-center py-4 col-span-full">Error loading products</p>';
                    }
                    console.error(error);
                    showNotification('Error loading products', 'error');
                })
                .finally(() => {
                    isLoading = false;
                    showLoadingIndicator(false);
                });
        }

        // Batch rendering for better performance
        function batchRenderProducts(products) {
            renderQueue = [...renderQueue, ...products];
            
            if (!isRenderingQueue) {
                processRenderQueue();
            }
        }

        function processRenderQueue() {
            if (renderQueue.length === 0) {
                isRenderingQueue = false;
                return;
            }

            isRenderingQueue = true;
            const fragment = document.createDocumentFragment();
            const batchSize = 6; // Render in smaller batches
            
            for (let i = 0; i < Math.min(batchSize, renderQueue.length); i++) {
                const product = renderQueue.shift();
                const card = createOptimizedProductCard(product);
                fragment.appendChild(card);
            }

            document.getElementById('product-results').appendChild(fragment);

            // Continue processing in next frame
            if (renderQueue.length > 0) {
                requestAnimationFrame(processRenderQueue);
            } else {
                isRenderingQueue = false;
            }
        }

        // Filter products based on current filter
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

        // Highly optimized product card creation
        function createOptimizedProductCard(product) {
            const card = document.createElement('div');
            const isOutOfStock = product.quantity === 0;
            
            card.className = `product-card bg-white p-3 border rounded-lg shadow-sm cursor-pointer ${isOutOfStock ? 'out-of-stock' : ''}`;
            card.dataset.productId = product.id;
            card.dataset.cost_price = product.cost_price;
            card.dataset.selling_price = product.selling_price;

            let firstImage = null;
            try {
                const pictures = typeof product.pictures === 'string' ? JSON.parse(product.pictures) : product.pictures;
                firstImage = Array.isArray(pictures) ? pictures[0] : null;
            } catch (e) {
                // Silent fail for better performance
            }

            // Use template string for better performance
            const imageHtml = firstImage
                ? `<img data-src="/storage/${firstImage}" class="w-full h-20 object-cover rounded-lg bg-gray-100" loading="lazy" alt="${product.name}">`
                : `<div class="w-full h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                   </div>`;

            card.innerHTML = `
                <div class="space-y-2">
                    <div class="relative overflow-hidden rounded-lg">
                        ${imageHtml}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">${product.name}</div>
                        <div class="text-xs text-gray-500 font-semibold">${product.selling_price}</div>
                        <div class="mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${isOutOfStock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                                ${isOutOfStock ? 'Out of Stock' : `${product.quantity} in stock`}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            // Setup lazy loading for images
            if (firstImage) {
                intersectionObserver.observe(card);
            }

            return card;
        }

        // Loading indicator
        function showLoadingIndicator(show) {
            const indicator = document.getElementById('loading-indicator');
            indicator.classList.toggle('hidden', !show);
        }

        // Enhanced product row addition
        function addProductRow(product = null) {
            if (product) {
                if (product.quantity === 0) {
                    showNotification(`${product.name} is out of stock!`, 'warning');
                    return;
                }

                const existing = [...document.querySelectorAll('input[name="product_ids[]"]')].find(input => input.value == product.id);
                if (existing) {
                    const row = existing.closest('.product-row');
                    const qty = row.querySelector('.quantity');
                    const currentQty = parseInt(qty.value);
                    
                    if (currentQty >= product.quantity) {
                        showNotification(`Cannot add more ${product.name}. Only ${product.quantity} in stock.`, 'warning');
                        return;
                    }
                    
                    qty.value = currentQty + 1;

                    const manualRow = [...document.querySelectorAll('.product-select')].find(select => !select.disabled && select.value == product.id)?.closest('.product-row');
                    if (manualRow) manualRow.remove();

                    calculateTotal();
                    return;
                }
            }

            const row = document.createElement('div');
            row.className = 'product-row bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-3';

            const id = product?.id ?? '';
            const cost = product?.cost_price ?? '';
            const price = product?.selling_price ?? '';
            const maxStock = product?.quantity ?? 999;

            if (product) {
                row.innerHTML = `
                    <input type="hidden" name="product_ids[]" value="${id}">
                    <input type="hidden" name="cost_prices[]" value="${cost}">
                    <input type="hidden" name="selling_prices[]" value="${price}">
                    
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="font-medium text-gray-900">${product.name}</div>
                            <div class="text-sm text-gray-500">${price} each • ${maxStock} in stock</div>
                        </div>
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Quantity</label>
                            <input type="number" name="quantities[]" class="quantity w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="1" max="${maxStock}" value="1" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Discount</label>
                            <input type="number" name="discounts[]" class="discount w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="0" step="0.01" value="0" required>
                        </div>
                    </div>
                `;
            } else {
                row.innerHTML = `
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-900">Select Product</h4>
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Product</label>
                        <select class="product-select w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select Product</option>
                            ${products.map(p => `
                                <option value="${p.id}" data-cost="${p.cost_price}" data-price="${p.selling_price}" data-stock="${p.quantity}">
                                    ${p.name} (${p.selling_price}) - ${p.quantity} in stock
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Quantity</label>
                            <input type="number" name="quantities[]" class="quantity w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="1" value="1" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Discount</label>
                            <input type="number" name="discounts[]" class="discount w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="0" step="0.01" value="0" required>
                        </div>
                    </div>
                `;
            }

            productsList.appendChild(row);
            calculateTotal();
        }

        // Enhanced event listeners
        document.getElementById('add-product-row').addEventListener('click', () => {
            addProductRow();
        });

        document.getElementById('clear-all').addEventListener('click', () => {
            if (confirm('Are you sure you want to clear all products?')) {
                productsList.innerHTML = '';
                calculateTotal();
                showNotification('All products cleared', 'info');
            }
        });

        // Enhanced product selection handling
        document.addEventListener('change', function(e) {
            if (e.target.matches('.product-select') && !e.target.disabled) {
                const selectedId = e.target.value;
                const currentRow = e.target.closest('.product-row');
                if (!selectedId || !currentRow) return;

                const option = e.target.selectedOptions[0];
                const stock = parseInt(option.dataset.stock);
                
                if (stock === 0) {
                    showNotification('Selected product is out of stock!', 'warning');
                    e.target.value = '';
                    return;
                }

                const existingRow = Array.from(document.querySelectorAll('.product-row')).find(row => {
                    const hiddenId = row.querySelector('input[name="product_ids[]"]');
                    return hiddenId?.value == selectedId && row !== currentRow;
                });

                if (existingRow) {
                    const qtyInput = existingRow.querySelector('.quantity');
                    const currentQty = parseInt(qtyInput.value || 0);
                    
                    if (currentQty >= stock) {
                        showNotification(`Cannot add more. Only ${stock} in stock.`, 'warning');
                        e.target.value = '';
                        return;
                    }
                    
                    qtyInput.value = currentQty + 1;
                    currentRow.remove();
                } else {
                    const product = products.find(p => p.id == selectedId);
                    if (!product) return;

                    const qtyInput = currentRow.querySelector('.quantity');
                    qtyInput.max = stock;

                    const hiddenInputs = `
                        <input type="hidden" name="product_ids[]" value="${product.id}">
                        <input type="hidden" name="cost_prices[]" value="${product.cost_price}">
                        <input type="hidden" name="selling_prices[]" value="${product.selling_price}">
                    `;
                    currentRow.insertAdjacentHTML('afterbegin', hiddenInputs);
                    e.target.disabled = true;
                    
                    const selectDiv = e.target.closest('div');
                    selectDiv.innerHTML = `
                        <label class="block text-xs font-medium text-gray-700 mb-1">Product</label>
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm">
                            ${product.name} (${product.selling_price}) - ${stock} in stock
                        </div>
                    `;
                }

                calculateTotal();
            }
        });

        // Enhanced calculation with validation
        function calculateTotal() {
            let total = 0;
            let discount = 0;

            const rows = document.querySelectorAll('.product-row');

            for (const row of rows) {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const disc = parseFloat(row.querySelector('.discount')?.value || 0);
                const price = parseFloat(row.querySelector('input[name="selling_prices[]"]')?.value || 0);

                const subtotal = (price * qty);
                const finalSubtotal = Math.max(0, subtotal - disc);
                
                total += finalSubtotal;
                discount += disc;
            }

            document.getElementById('total_price').value = total.toFixed(2);
            document.getElementById('total_discount').value = discount.toFixed(2);
            document.getElementById('total_price_display').textContent = total.toFixed(2);
            document.getElementById('total_discount_display').textContent = discount.toFixed(2);
        }

        // Optimized event delegation
        document.addEventListener('click', e => {
            if (e.target.closest('.remove-row')) {
                e.target.closest('.product-row').remove();
                calculateTotal();
                showNotification('Product removed', 'info');
                return;
            }

            const card = e.target.closest('.product-card');
            if (card && !card.classList.contains('out-of-stock')) {
                // Get product name from the card more efficiently
                const nameElement = card.querySelector('.text-sm.font-medium');
                if (!nameElement) return;

                const product = {
                    id: parseInt(card.dataset.productId),
                    name: nameElement.textContent,
                    cost_price: parseFloat(card.dataset.cost_price),
                    selling_price: parseFloat(card.dataset.selling_price),
                    quantity: parseInt(card.querySelector('.bg-green-100, .bg-red-100')?.textContent.match(/\d+/)?.[0] || 0)
                };

                addProductRow(product);
                showNotification(`Added ${product.name} to bill`, 'success');
            }
        });

        document.addEventListener('input', e => {
            if (['quantity', 'discount'].some(cls => e.target.classList.contains(cls))) {
                if (e.target.classList.contains('quantity')) {
                    const max = parseInt(e.target.max) || 999;
                    const value = parseInt(e.target.value) || 0;
                    
                    if (value > max) {
                        e.target.value = max;
                        showNotification(`Maximum quantity is ${max}`, 'warning');
                    }
                }
                
                calculateTotal();
            }
        });

        // Enhanced print functionality
        document.getElementById('print-button').addEventListener('click', () => {
            const printList = document.getElementById('print-products-list');
            printList.innerHTML = '';

            let total = 0, discount = 0;

            document.querySelectorAll('.product-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const disc = parseFloat(row.querySelector('.discount')?.value || 0);
                const price = parseFloat(row.querySelector('input[name="selling_prices[]"]')?.value || 0);

                let name = 'Unknown';
                const select = row.querySelector('.product-select');
                if (select && !select.disabled) {
                    name = select.selectedOptions[0]?.textContent.split('(')[0]?.trim() || 'Unknown';
                } else {
                    const nameDiv = row.querySelector('.font-medium.text-gray-900');
                    if (nameDiv) name = nameDiv.textContent?.trim() || 'Unknown';
                }

                const sub = Math.max(0, (price * qty) - disc);
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
                printList.appendChild(tr);
            });

            document.getElementById('print-total-price').textContent = total.toFixed(2) + '₪';
            document.getElementById('print-total-discount').textContent = discount.toFixed(2) + '₪';

            const customerSelect = document.querySelector('select[name="customer_id"]');
            const selectedOption = customerSelect?.selectedOptions[0];
            const customerName = selectedOption?.dataset.name || '';
            const customerPhone = selectedOption?.dataset.phone || '';

            document.getElementById('print-customer').textContent = customerName ? `Customer: ${customerName}` : '';
            document.getElementById('print-customer-phone').textContent = customerPhone ? `Phone: ${customerPhone}` : '';

            window.print();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', e => {
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('create-bill').submit();
            }
            
            if (e.key === 'Escape') {
                if (!document.getElementById('barcode-modal').classList.contains('hidden')) {
                    closeBarcodeModal();
                }
            }
            
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('barcode_input').focus();
            }
        });

        // High-performance scroll handler with throttling
        document.getElementById('product-cards-container').addEventListener('scroll', (e) => {
            if (scrollTimeout) return;
            
            scrollTimeout = setTimeout(() => {
                const container = e.target;
                // Use more efficient scroll detection
                const scrollTop = container.scrollTop;
                const scrollHeight = container.scrollHeight;
                const clientHeight = container.clientHeight;
                
                if (scrollTop + clientHeight >= scrollHeight - 100) {
                    fetchProducts();
                }
                scrollTimeout = null;
            }, 150); // Slightly increased debounce for better performance
        });

        // Enhanced notification system with better performance
        function showNotification(message, type = 'info') {
            // Reuse existing notifications if possible
            let notification = document.querySelector('.notification-toast');
            
            if (!notification) {
                notification = document.createElement('div');
                notification.className = 'notification-toast fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
                document.body.appendChild(notification);
            }

            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            // Reset classes and apply new ones
            notification.className = `notification-toast fixed top-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            notification.textContent = message;
            
            // Clear any existing timeout
            if (notification.hideTimeout) {
                clearTimeout(notification.hideTimeout);
            }
            
            // Animate in
            requestAnimationFrame(() => {
                notification.classList.remove('translate-x-full');
            });
            
            // Auto remove
            notification.hideTimeout = setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Update today's sales display
        function updateTotalSalesToday() {
            // You can add real-time updates here if needed
        }

        // Enhanced form validation
        document.getElementById('create-bill').addEventListener('submit', (e) => {
            const rows = document.querySelectorAll('.product-row');
            if (rows.length === 0) {
                e.preventDefault();
                showNotification('Please add at least one product to the bill', 'warning');
                return;
            }

            // Validate quantities
            let hasError = false;
            rows.forEach(row => {
                const qty = parseInt(row.querySelector('.quantity')?.value || 0);
                const max = parseInt(row.querySelector('.quantity')?.max || 999);
                
                if (qty > max) {
                    hasError = true;
                    showNotification('Some products exceed available stock', 'error');
                }
            });

            if (hasError) {
                e.preventDefault();
                return;
            }

            showNotification('Creating bill...', 'info');
        });

        // Auto-focus management
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('barcode_input').focus();
        });

        // Prevent form submission on Enter in barcode input
        document.getElementById('barcode_input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // Performance monitoring (optional - remove in production)
        if (typeof performance !== 'undefined') {
            let frameCount = 0;
            let lastTime = performance.now();
            
            function measureFPS() {
                frameCount++;
                const currentTime = performance.now();
                
                if (currentTime >= lastTime + 1000) {
                    const fps = Math.round((frameCount * 1000) / (currentTime - lastTime));
                    if (fps < 30) {
                        console.warn('Low FPS detected:', fps);
                    }
                    frameCount = 0;
                    lastTime = currentTime;
                }
                
                requestAnimationFrame(measureFPS);
            }
            
            // Start FPS monitoring (comment out in production)
            // requestAnimationFrame(measureFPS);
        }

        // Cleanup function for better memory management
        window.addEventListener('beforeunload', () => {
            if (intersectionObserver) {
                intersectionObserver.disconnect();
            }
            
            // Clear any pending timeouts
            clearTimeout(debounceTimeout);
            clearTimeout(scrollTimeout);
            
            // Clear render queue
            renderQueue = [];
            isRenderingQueue = false;
        });
    </script>
</x-app-layout>