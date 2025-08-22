@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    
    // Get shop name based on user role
    $shopName = 'Shop'; // Default fallback
    if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id) {
        $shopName = auth()->user()-> shopOwner->name ?? 'Shop';
    } elseif (auth()->user()->role !== 'employee') {
        $shopName = auth()->user()->name ?? 'Shop';
    }
    
    $isRestaurant = auth()->user()->role === 'restaurant';
@endphp
<x-app-layout>
    <x-slot name="header">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <h2 class="font-bold text-xl sm:text-2xl text-gray-800 leading-tight flex items-center">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 mr-2 sm:mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            {{ $isRestaurant ? __('dashboard.Restaurant Dashboard') : __('dashboard.Point of Sale Dashboard') }}
        </h2>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
            <div class="text-xs sm:text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
                {{ __('dashboard.Today\'s Sales') }}: <span class="font-bold text-green-600">${{ number_format($totalToday ?? 0, 2) }}</span>
            </div>
        </div>
    </div>
</x-slot>

    <!-- Enhanced Layout with Full Screen Width -->
    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 max-w-none">
                
                <!-- Left Panel - Product Search & Selection / Restaurant Quick Payments -->
            <div class="lg:col-span-5 space-y-4">
                <!-- Product Search Controls - Shown for all users -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center mb-4">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.Product Search') }}</h3>
                    </div>
                    
                    <!-- Search Input -->
                    <div class="relative mb-4">
                        <input
                            type="text"
                            id="product-search"
                            placeholder="{{ __('dashboard.Search products by name...') }}"
                            class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        />
                        <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    <!-- Filter Options -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button id="filter-all" class="filter-btn active px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200 transition-colors">
                            {{ __('dashboard.All Products') }}
                        </button>
                        <button id="filter-in-stock" class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                            {{ __('dashboard.In Stock Only') }}
                        </button>
                        <button id="filter-out-of-stock" class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                            {{ __('dashboard.Out of Stock') }}
                        </button>
                    </div>
                </div>

                <!-- Product Results - Shown for all users -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-4 border-b border-gray-100">
                        <h4 class="font-medium text-gray-800 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            {{ __('dashboard.Available Products') }}
                        </h4>
                    </div>
                    <div id="product-cards-container" class="max-h-96 overflow-y-auto scroll-container">
                        <div id="product-results" class="grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 p-4 products-grid">
                            <!-- Products will be loaded here -->
                        </div>
                        <div id="loading-indicator" class="hidden p-4 text-center">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="text-sm text-gray-500 mt-2">{{ __('dashboard.Loading products...') }}</p>
                        </div>
                    </div>
                </div>

                @if($isRestaurant)
                    <!-- Restaurant Quick Customer Payments Panel -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.Quick Customer Payments') }}</h3>
                        </div>

                        <!-- Quick Payment Form -->
                        <form id="quick-payment-form" class="space-y-4">
                            @csrf
                            <input type="hidden" id="payment_customer_id" name="customer_id">
                            
                            <!-- Customer Dropdown for Restaurant -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Customer') }}</label>
                                <select id="payment_customer_select" name="customer_select" class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" required>
                                    <option value="">{{ __('dashboard.Select Customer') }}</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-balance="{{ $customer->balance }}">
                                            {{ $customer->name }} - {{ $customer->phone }} (Balance: {{ $customer->balance ?? '0.00' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Amount') }}</label>
                                    <input type="number" id="payment_amount" name="amount" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="0.00" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Type') }}</label>
                                    <select id="payment_type" name="type" class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                        <option value="cash">{{ __('dashboard.Cash') }}</option>
                                        <option value="card">{{ __('dashboard.Card') }}</option>
                                        <option value="transfer">{{ __('dashboard.Transfer') }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Note') }}</label>
                                <textarea id="payment_note" name="note" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="{{ __('dashboard.Payment note...') }}"></textarea>
                            </div>
                            
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('dashboard.Add Payment') }}
                            </button>
                        </form>
                    </div>

                    <!-- Recent Payments -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-4 border-b border-gray-100">
                            <h4 class="font-medium text-gray-800 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                {{ __('dashboard.Recent Payments') }}
                            </h4>
                        </div>
                        <div id="recent-payments" class="max-h-96 overflow-y-auto">
                            <div class="p-4 text-center text-gray-500">
                                {{ __('dashboard.No recent payments') }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
                                

                <!-- Main Content - Bill Creation -->
                <div class="lg:col-span-4 space-y-4">
                    @if(!$isRestaurant)
                        <!-- Barcode Scanner (Hidden for Restaurant) -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.Quick Scanner') }}</h3>
                            </div>
                            <div class="relative">
                                <input
                                    type="text"
                                    id="barcode_input"
                                    placeholder="{{ __('dashboard.Scan or enter barcode...') }}"
                                    class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors font-mono"
                                    autocomplete="off"
                                />
                                <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                                </svg>
                            </div>
                        </div>
                    @endif

                    <!-- Bill Form -->
                    <div id="printable" class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ __('dashboard.Create New Bill') }}
                            </h3>
                        </div>
                        
                        <form id="create-bill" method="POST" action="{{ route('bills.store') }}" class="p-6">
                            @csrf

                           <!-- Customer Selection -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Customer') }}</label>
                                @if($isRestaurant)
                                    <!-- Restaurant: Dropdown selector -->
                                    <select name="customer_id" id="customer_id" class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                        <option value="">{{ __('dashboard.Select Customer') }}</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">
                                                {{ $customer->name }} - {{ $customer->phone }} (Balance: {{ $customer->balance ?? '0.00' }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <!-- Regular: Search input -->
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="customer_search" 
                                            name="customer_search"
                                            placeholder="{{ __('dashboard.Search customer by name or enter new customer...') }}"
                                            class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                            autocomplete="off"
                                        />
                                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <input type="hidden" name="customer_id" id="customer_id" value="">
                                        
                                        <!-- Customer suggestions dropdown -->
                                        <div id="customer_suggestions" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto mt-1">
                                            <!-- Suggestions will be populated here -->
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Note -->
                            <div class="mb-6">
                                <label for="note" class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Note') }}</label>
                                <textarea name="note" id="note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="{{ __('dashboard.Add any notes for this bill...') }}"></textarea>
                            </div>

                            <!-- Damaged Checkbox -->
                            <div class="mb-6 flex items-center p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <input type="checkbox" name="is_damaged" id="is_damaged" class="mr-2 h-4 w-4 text-amber-600 border-amber-300 rounded focus:ring-amber-500">
                                <label for="is_damaged" class="ml-2 text-sm text-amber-800 font-medium">{{ __('dashboard.Mark as Damaged Bill (100% discount)') }}</label>
                            </div>

                            <!-- Products List -->
                            <div id="products-list" class="space-y-2 mb-6 max-h-96 overflow-y-auto">
                                <!-- Products will be added here dynamically -->
                            </div>

                            <!-- Add Product Button -->
                            <button type="button" id="add-product-row" class="w-full mb-6 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('dashboard.Add Product Manually') }}
                            </button>

                            <!-- Action Buttons -->
                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('dashboard.Create Bill (F2)') }}
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
                                <button type="button" id="print-receipt-button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
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
                            {{ __('dashboard.Bill Summary') }}
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">{{ __('dashboard.Total Discount:') }}</span>
                                    <span class="font-bold text-lg">$<span id="total_discount_display">0.00</span></span>
                                </div>
                                <input type="hidden" id="total_discount" value="0">
                            </div>
                            
                            <div class="bg-white bg-opacity-30 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">{{ __('dashboard.Total Amount:') }}</span>
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
                            {{ __('dashboard.Today\'s Performance') }}
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="text-sm text-blue-700">{{ __('dashboard.Total Sales:') }}</span>
                                <span class="font-bold text-blue-800">${{ number_format($totalToday ?? 0, 2) }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-700">{{ __('dashboard.Bills Created:') }}</span>
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
                            {{ __('dashboard.Quick Actions') }}
                        </h3>
                        
                        <div class="space-y-2">
                            <a href="{{ route('bills.index') }}" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ __('dashboard.View All Bills') }}
                            </a>
                            
                            @if(!$isRestaurant)
                            <a href="{{ route('products.index') }}" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                {{ __('dashboard.Manage Products') }}
                            </a>
                            @endif
                            
                            <a href="{{ route('customers.index') }}" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                {{ __('dashboard.Manage Customers') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Standard Printable Invoice --}}
<div id="print-area" class="print-hidden p-6 text-sm">
    <div class="text-center mb-4">
        <h1 class="text-2xl font-bold">{{ $shopName }}</h1>
        <p>{{ now()->format('Y-m-d H:i') }}</p>
        <p id="print-bill-id" class="text-sm font-medium">{{ __('messages.Bill ID') }}: #<span id="current-bill-id">-</span></p>
        <hr class="my-2">
    </div>
    <div id="print-customer" class="font-semibold text-left"></div>
    <div id="print-customer-phone" class="font-semibold text-left"></div>
    <table class="w-full border border-gray-400 text-sm">
        <thead>
            <tr>
                <th class="border px-2 py-1">{{ __('messages.Product') }}</th>
                <th class="border px-2 py-1 text-right">{{ __('messages.Qty') }}</th>
                <th class="border px-2 py-1 text-right">{{ __('messages.Unit Price') }}</th>
                <th class="border px-2 py-1 text-right">{{ __('messages.Discount') }}</th>
                <th class="border px-2 py-1 text-right">{{ __('messages.Total') }}</th>
            </tr>
        </thead>
        <tbody id="print-products-list"></tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="border px-2 py-1 text-right font-bold">{{ __('messages.Totals') }}</td>
                <td id="print-total-discount" class="border px-2 py-1 text-right">0.00₪</td>
                <td id="print-total-price" class="border px-2 py-1 text-right">0.00₪</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Roll Paper Receipt --}}
<div id="receipt-area" class="print-hidden">
    <div class="receipt-content">
        <div class="text-center mb-2">
            <div class="text-lg font-bold">{{ $shopName }}</div>
            <div class="text-xs">{{ now()->format('Y-m-d H:i:s') }}</div>
            <div id="receipt-bill-id" class="text-xs font-medium">{{ __('messages.Bill') }}: #<span id="receipt-current-bill-id">-</span></div>
            <div class="border-t border-dashed my-2"></div>
        </div>
       
        <div id="receipt-customer" class="text-xs mb-2"></div>
       
        <div id="receipt-products-list" class="text-xs">
            <!-- Products will be added here -->
        </div>
       
        <div class="border-t border-dashed my-2"></div>
       
        <div id="receipt-totals-container">
            <!-- Totals will be added dynamically -->
        </div>
       
        <div class="text-center text-xs mt-3">
            <div>{{ __('messages.Thank you for your business!') }}</div>
            <div class="border-t border-dashed mt-2"></div>
        </div>
    </div>
</div>

    {{-- Enhanced Performance Styles --}}
    <style>
        /* Customer suggestions dropdown */
        .customer-suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #e5e7eb;
        }

        .customer-suggestion-item:hover {
            background-color: #f3f4f6;
        }

        .customer-suggestion-item:last-child {
            border-bottom: none;
        }

        /* Discount type toggle */
        .discount-toggle {
            display: flex;
            background-color: #f3f4f6;
            border-radius: 6px;
            padding: 2px;
        }

        .discount-toggle button {
            flex: 1;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .discount-toggle button.active {
            background-color: white;
            color: #1f2937;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Compact product row styles */
        .product-row.compact {
            padding: 8px 12px;
            background-color: #f9fafb;
        }

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
            transform: translate3d(0, 0, 0);
            will-change: scroll-position;
            -webkit-transform: translate3d(0, 0, 0);
            contain: layout style paint;
        }

        /* Optimized grid container */
        .products-grid {
            transform: translate3d(0, 0, 0);
            will-change: contents;
            contain: layout style;
            backface-visibility: hidden;
            perspective: 1000px;
        }

        /* High-performance product cards */
        .product-card {
            transition: transform 0.1s ease-out, box-shadow 0.1s ease-out;
            transform: translate3d(0, 0, 0);
            will-change: transform;
            contain: layout style paint;
            backface-visibility: hidden;
            overflow: hidden;
            position: relative;
        }

        .product-card:hover {
            transform: translate3d(0, -1px, 0);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .product-card.out-of-stock {
            opacity: 0.7;
            pointer-events: none;
        }

        .product-card.out-of-stock:hover {
            transform: translate3d(0, 0, 0);
            box-shadow: none;
        }

        /* Optimized image rendering */
        .product-card img {
            transition: transform 0.15s ease-out;
            transform: translate3d(0, 0, 0);
            image-rendering: auto;
            backface-visibility: hidden;
        }

        .product-card:hover img {
            transform: translate3d(0, 0, 0) scale(1.02);
        }

        /* Optimize text rendering */
        .product-card .text-sm {
            user-select: none;
            -webkit-user-select: none;
            text-rendering: optimizeSpeed;
        }

        /* Virtual scrolling optimization */
        .product-card {
            min-height: 120px;
            height: auto;
        }

        /* Reduce paint complexity */
        .product-card * {
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

        /* Receipt styles for roll paper */
        #receipt-area {
            display: none;
            font-family: 'Courier New', monospace;
        }

        .receipt-content {
            width: 58mm;
            padding: 2mm;
            font-size: 8pt;
            line-height: 1.2;
        }

        .receipt-product-row {
            margin-bottom: 1mm;
        }

        .receipt-product-name {
            font-weight: bold;
        }

        .receipt-product-details {
            display: flex;
            justify-content: space-between;
            font-size: 7pt;
        }

        /* Optimize for mobile scrolling */
        @media (max-width: 768px) {
            .scroll-container {
                -webkit-overflow-scrolling: touch;
                overflow-scrolling: touch;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .product-card {
                transition: none;
            }
            
            .product-card:hover {
                transform: translate3d(0, 0, 0);
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }

        /* Standard print styles */
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

        /* Receipt print styles */
        @media print {
            .print-receipt #print-area {
                display: none !important;
            }

            .print-receipt #receipt-area, .print-receipt #receipt-area * {
                visibility: visible !important;
                height: auto !important;
                overflow: visible !important;
            }

            .print-receipt #receipt-area {
                display: block !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 58mm !important;
                padding: 0 !important;
                background: white;
                font-size: 8pt !important;
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
                                {{ __('dashboard.Multiple Products Found') }}
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
        const customers = @json($customers);
        let customerDebounceTimeout = null;
        let paymentCustomerDebounceTimeout = null;
        const totalSalesToday = {{ $totalToday ?? 0 }};
        const productsList = document.getElementById('products-list');
        const isRestaurant = {{ $isRestaurant ? 'true' : 'false' }};
        const shopName = '{{ $shopName }}';
        let currentBillId = null;

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
            // Always initialize product search and fetch for both restaurant and regular users
            fetchProducts(true);
            setupIntersectionObserver();
            
            if (!isRestaurant) {
                // Only focus barcode input for non-restaurant users
                document.getElementById('barcode_input').focus();
            } else {
                // Setup restaurant-specific functionality
                loadRecentPayments();
            }
            updateTotalSalesToday();
            if (!isRestaurant) {
                setupCustomerSearch();
            } else {
                setupRestaurantCustomerSelectors();
            }
        });

        // Restaurant customer selector functionality
            function setupRestaurantCustomerSelectors() {
            // Handle payment customer select
            const paymentCustomerSelect = document.getElementById('payment_customer_select');
            if (paymentCustomerSelect) {
                paymentCustomerSelect.addEventListener('change', function() {
                    const selectedOption = this.selectedOptions[0];
                    if (selectedOption && selectedOption.value) {
                        document.getElementById('payment_customer_id').value = selectedOption.value;
                        loadRecentPayments(); // Load recent payments when customer is selected
                    } else {
                        document.getElementById('payment_customer_id').value = '';
                        loadRecentPayments(); // Clear recent payments when no customer is selected
                    }
                });
            }
        }

        
       // Handle quick payment form submission
        document.getElementById('quick-payment-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const customerId = document.getElementById('payment_customer_id').value;
            if (!customerId) {
                showNotification('Please select a customer', 'error');
                return;
            }

            const formData = new FormData(this);
            
            try {
                const response = await fetch(`/customers/${customerId}/payments`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                if (response.ok) {
                    showNotification('Payment added successfully!', 'success');
                    this.reset();
                    document.getElementById('payment_customer_id').value = '';
                    document.getElementById('payment_customer_select').value = '';
                    loadRecentPayments();
                } else {
                    const errorData = await response.json();
                    showNotification(errorData.message || 'Failed to add payment', 'error');
                }
            } catch (error) {
                console.error('Payment error:', error);
                showNotification('Failed to add payment', 'error');
            }
        });

        // Load recent payments for restaurant
        // Load recent payments for restaurant
        async function loadRecentPayments() {
            const customerId = document.getElementById('payment_customer_id').value;
            
            if (!customerId) {
                document.getElementById('recent-payments').innerHTML = `
                    <div class="p-4 text-center text-gray-500">
                        Select a customer to view recent payments
                    </div>
                `;
                return;
            }

            try {
                const response = await fetch(`/customers/${customerId}/recent-payments`);
                if (!response.ok) {
                    throw new Error('Failed to fetch payments');
                }
                
                const payments = await response.json();
                
                if (payments.length === 0) {
                    document.getElementById('recent-payments').innerHTML = `
                        <div class="p-4 text-center text-gray-500">
                            No recent payments for this customer
                        </div>
                    `;
                    return;
                }

                const paymentsHtml = payments.map(payment => `
                    <div class="p-3 border-b border-gray-100 last:border-b-0">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">$${parseFloat(payment.amount).toFixed(2)}</div>
                                <div class="text-xs text-gray-500 capitalize">${payment.type}</div>
                                ${payment.note ? `<div class="text-xs text-gray-600 mt-1">${payment.note}</div>` : ''}
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">${payment.created_at}</div>
                                <div class="text-xs text-gray-400">${payment.created_at_human}</div>
                            </div>
                        </div>
                    </div>
                `).join('');

                document.getElementById('recent-payments').innerHTML = paymentsHtml;
                
            } catch (error) {
                console.error('Failed to load recent payments:', error);
                document.getElementById('recent-payments').innerHTML = `
                    <div class="p-4 text-center text-red-500">
                        Failed to load recent payments
                    </div>
                `;
            }
        }

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

        // Customer search functionality
        function setupCustomerSearch() {
            const searchInput = document.getElementById('customer_search');
            const suggestionsDiv = document.getElementById('customer_suggestions');
            const customerIdInput = document.getElementById('customer_id');

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(customerDebounceTimeout);
                customerDebounceTimeout = setTimeout(() => {
                    if (query.length === 0) {
                        suggestionsDiv.classList.add('hidden');
                        customerIdInput.value = '';
                        return;
                    }

                    const filteredCustomers = customers.filter(customer =>
                        customer.name.toLowerCase().includes(query.toLowerCase()) ||
                        (customer.phone && customer.phone.includes(query))
                    );

                    if (filteredCustomers.length > 0) {
                        showCustomerSuggestions(filteredCustomers);
                    } else {
                        suggestionsDiv.classList.add('hidden');
                        customerIdInput.value = '';
                    }
                }, 300);
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.classList.add('hidden');
                }
            });
        }

        function showCustomerSuggestions(filteredCustomers) {
            const suggestionsDiv = document.getElementById('customer_suggestions');
            suggestionsDiv.innerHTML = '';

            filteredCustomers.forEach(customer => {
                const div = document.createElement('div');
                div.className = 'customer-suggestion-item';
                div.innerHTML = `
                    <div class="font-medium text-gray-900">${customer.name}</div>
                    <div class="text-sm text-gray-500">${customer.phone || ''}</div>
                `;
                div.addEventListener('click', () => selectCustomer(customer));
                suggestionsDiv.appendChild(div);
            });

            suggestionsDiv.classList.remove('hidden');
        }

        function selectCustomer(customer) {
            document.getElementById('customer_search').value = customer.name;
            document.getElementById('customer_id').value = customer.id;
            document.getElementById('customer_suggestions').classList.add('hidden');
        }

        // Enhanced barcode input handler (only for non-restaurant)
        if (!isRestaurant) {
            document.getElementById('barcode_input').addEventListener('keydown', async e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    console.log("Barcode input detected:", e.target.value);
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
                            addProductRow(result);
                            e.target.value = '';
                            showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}', result.name), 'success');
                        } else {
                            showNotification('{{ __('messages.Product not found for barcode: {code}') }}'.replace('{code}', code), 'warning');
                        }
                    } catch (err) {
                        console.error('Fetch error:', err);
                        showNotification('{{ __('messages.Failed to fetch product data.') }}', 'error');
                    }
                }
            });
        }

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
                showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}', product.name), 'success');
            }
        });

        // Close modal handlers
        function closeBarcodeModal() {
            document.getElementById('barcode-modal').classList.add('hidden');
        }

        document.getElementById('close-modal')?.addEventListener('click', closeBarcodeModal);
        document.querySelector('.modal-overlay')?.addEventListener('click', closeBarcodeModal);

        // Filter buttons (now for both restaurant and regular users)
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                
                currentFilter = e.target.id.replace('filter-', '');
                searchTerm = document.getElementById('product-search').value.trim();
                fetchProducts(true);
            });
        });

        // Enhanced product search with debouncing (now for both restaurant and regular users)
        document.getElementById('product-search').addEventListener('input', function () {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                searchTerm = this.value.trim();
                currentPage = 1;
                hasMore = true;
                fetchProducts(true);
            }, 300);
        });

        // Optimized product fetching with batching (now for both restaurant and regular users)
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
                    batchRenderProducts(filteredProducts);

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
            const batchSize = 6;
            
            for (let i = 0; i < Math.min(batchSize, renderQueue.length); i++) {
                const product = renderQueue.shift();
                const card = createOptimizedProductCard(product);
                fragment.appendChild(card);
            }

            document.getElementById('product-results').appendChild(fragment);

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
                                ${isOutOfStock ? '{{__('messages.Out of Stock')}}' : `${product.quantity} {{__('messages.in stock')}}`}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            // Setup lazy loading for images
            if (firstImage && intersectionObserver) {
                intersectionObserver.observe(card);
            }

            return card;
        }

        // Loading indicator
        function showLoadingIndicator(show) {
            const indicator = document.getElementById('loading-indicator');
            if (indicator) {
                indicator.classList.toggle('hidden', !show);
            }
        }

        // Enhanced product row addition
        function addProductRow(product = null) {
            if (product) {
                if (product.quantity === 0) {
                    showNotification(`{{ __('messages.{product} is out of stock!') }}`.replace('{product}', product.name), 'warning');
                }

                const existing = [...document.querySelectorAll('input[name="product_ids[]"]')].find(input => input.value == product.id);
                if (existing) {
                    const row = existing.closest('.product-row');
                    const qty = row.querySelector('.quantity');
                    const currentQty = parseInt(qty.value);
                    
                    if (currentQty >= product.quantity) {
                        showNotification(`{{ __('messages.Cannot add more {product}. Only {quantity} in stock.') }}`.replace('{product}', product.name).replace('{quantity}', product.quantity), 'warning');
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
                row.className = 'product-row compact bg-gray-50 border border-gray-200 rounded-lg';
                row.innerHTML = `
                    <input type="hidden" name="product_ids[]" value="${id}">
                    <input type="hidden" name="cost_prices[]" value="${cost}">
                    
                    <div class="flex items-center justify-between p-2">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">${product.name}</div>
                            <div class="text-xs text-gray-500">${maxStock} in stock</div>
                        </div>
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                    
                <div class="px-2 pb-2">
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="grid grid-cols-2 gap-1 col-span-2">
                            <div>
                                <label class="block text-gray-600 mb-1">Qty</label>
                                <input type="number" name="quantities[]" class="quantity w-full px-2 py-1 border border-gray-300 rounded text-xs h-7" min="1" value="1" required>
                            </div>
                            <div>
                                <label class="block text-gray-600 mb-1">Price</label>
                                <input type="number" name="selling_prices[]" class="selling-price w-full px-2 py-1 border border-gray-300 rounded text-xs h-7" min="0" step="0.01" value="${price}" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-600 mb-1">{{__('messages.Discount')}}</label>
                            <div class="flex gap-1 h-7">
                                <div class="discount-toggle text-xs h-full">
                                    <button type="button" class="discount-type-btn active h-full" data-type="total">{{__('messages.Total')}}</button>
                                    <button type="button" class="discount-type-btn h-full" data-type="per-unit">{{__('messages.Unit')}}</button>
                                </div>
                                <input type="number" name="discounts[]" class="discount w-full px-2 py-1 border border-gray-300 rounded text-xs h-7" min="0" step="0.01" value="0" required>
                                <input type="hidden" name="discount_types[]" class="discount-type" value="total">
                            </div>
                        </div>
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
            if (confirm('{{ __('messages.Are you sure you want to clear all products?') }}')) {
                productsList.innerHTML = '';
                calculateTotal();
                showNotification('{{ __('messages.All products cleared') }}', 'info');
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
                    showNotification('{{ __('messages.Selected product is out of stock!') }}', 'warning');
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
                        showNotification(`{{ __('messages.Cannot add more. Only {stock} in stock.') }}`.replace('{stock}', stock), 'warning');
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
            let totalDiscount = 0;

            const rows = document.querySelectorAll('.product-row');

            for (const row of rows) {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const discount = parseFloat(row.querySelector('.discount')?.value || 0);
                const price = parseFloat(row.querySelector('.selling-price')?.value || 0);
                const discountType = row.querySelector('.discount-type')?.value || 'total';

                let subtotal = price * qty;
                let appliedDiscount = 0;

                if (discountType === 'per-unit') {
                    appliedDiscount = discount * qty;
                } else {
                    appliedDiscount = discount;
                }

                const finalSubtotal = Math.max(0, subtotal - appliedDiscount);
                
                total += finalSubtotal;
                totalDiscount += appliedDiscount;
            }

            document.getElementById('total_price').value = total.toFixed(2);
            document.getElementById('total_discount').value = totalDiscount.toFixed(2);
            document.getElementById('total_price_display').textContent = total.toFixed(2);
            document.getElementById('total_discount_display').textContent = totalDiscount.toFixed(2);
        }

        // Optimized event delegation
        document.addEventListener('click', e => {
            if (e.target.closest('.remove-row')) {
                e.target.closest('.product-row').remove();
                calculateTotal();
                showNotification('{{ __('messages.Product removed') }}', 'info');
                return;
            }

            // Handle discount type toggle
            if (e.target.classList.contains('discount-type-btn')) {
                const row = e.target.closest('.product-row');
                const buttons = row.querySelectorAll('.discount-type-btn');
                const hiddenInput = row.querySelector('.discount-type');
                
                buttons.forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');
                hiddenInput.value = e.target.dataset.type;
                
                calculateTotal();
                return;
            }

            // Product card click (now for both restaurant and regular users)
            const card = e.target.closest('.product-card');
            if (card && !card.classList.contains('out-of-stock')) {
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
                showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}', product.name), 'success');
            }
        });

        document.addEventListener('input', e => {
            if (['quantity', 'discount', 'selling-price'].some(cls => e.target.classList.contains(cls))) {
                calculateTotal();
            }
        });

        // Enhanced print functionality
        document.getElementById('print-button').addEventListener('click', () => {
            updatePrintAreas();
            document.body.classList.remove('print-receipt');
            window.print();
        });

        // Receipt print functionality
        document.getElementById('print-receipt-button').addEventListener('click', () => {
            updatePrintAreas();
            document.body.classList.add('print-receipt');
            window.print();
            document.body.classList.remove('print-receipt');
        });

        function updatePrintAreas() {
    const printList = document.getElementById('print-products-list');
    const receiptList = document.getElementById('receipt-products-list');
    
    printList.innerHTML = '';
    receiptList.innerHTML = '';

    let total = 0, totalDiscount = 0;

    document.querySelectorAll('.product-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
        const discountValue = parseFloat(row.querySelector('.discount')?.value || 0);
        const price = parseFloat(row.querySelector('.selling-price')?.value || 
                    row.querySelector('input[name="selling_prices[]"]')?.value || 0);
        const discountType = row.querySelector('.discount-type')?.value || 'total';
        
        // Calculate actual discount amount based on type
        let actualDiscount = 0;
        if (discountType === 'per-unit') {
            actualDiscount = discountValue * qty;
        } else {
            actualDiscount = discountValue;
        }

        let name = '{{ __('messages.Unknown') }}';
        const select = row.querySelector('.product-select');
        if (select && !select.disabled) {
            name = select.selectedOptions[0]?.textContent.split('(')[0]?.trim() || '{{ __('messages.Unknown') }}';
        } else {
            const nameDiv = row.querySelector('.font-medium.text-gray-900');
            if (nameDiv) name = nameDiv.textContent?.trim() || '{{ __('messages.Unknown') }}';
        }

        const sub = Math.max(0, (price * qty) - actualDiscount);
        total += sub;
        totalDiscount += actualDiscount;

        // Standard print table row
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="border px-2 py-1">${name}</td>
            <td class="border px-2 py-1 text-right">${qty}</td>
            <td class="border px-2 py-1 text-right">${price.toFixed(2)}₪</td>
            <td class="border px-2 py-1 text-right">${actualDiscount.toFixed(2)}₪</td>
            <td class="border px-2 py-1 text-right">${sub.toFixed(2)}₪</td>
        `;
        printList.appendChild(tr);

        // Receipt format
        const receiptDiv = document.createElement('div');
        receiptDiv.className = 'receipt-product-row';
        receiptDiv.innerHTML = `
            <div class="receipt-product-name">${name}</div>
            <div class="receipt-product-details">
                <span>${qty} x ${price.toFixed(2)}₪</span>
                <span>${sub.toFixed(2)}₪</span>
            </div>
            ${actualDiscount > 0 ? `<div class="text-center text-xs">{{ __('messages.Discount') }}: -${actualDiscount.toFixed(2)}₪</div>` : ''}
        `;
        receiptList.appendChild(receiptDiv);
    });
    
    const receiptDiv = document.createElement('div');
    receiptDiv.className = 'receipt-product-row';
    receiptDiv.innerHTML = `
        <div class="border-t border-dashed my-2"></div>
        <div class="border-t border-dashed my-2"></div>
        <div class="text-center text-xs">{{ __('messages.Total') }}: ${total.toFixed(2)}₪</div>
        ${totalDiscount > 0 ? `<div class="text-center text-xs">{{ __('messages.Discount') }}: -${totalDiscount.toFixed(2)}₪</div>` : ''}
    `;
    
    receiptList.appendChild(receiptDiv);

    // Update totals
    document.getElementById('print-total-price').textContent = total.toFixed(2) + '₪';
    document.getElementById('print-total-discount').textContent = totalDiscount.toFixed(2) + '₪';

    // Update customer info
    let customerName = '';
    if (isRestaurant) {
        // For restaurant users, get the selected customer name from the dropdown
        const customerSelect = document.getElementById('customer_id');
        if (customerSelect && customerSelect.value) {
            const selectedOption = customerSelect.selectedOptions[0];
            customerName = selectedOption ? selectedOption.textContent.split(' - ')[0] : '';
        }
    } else {
        // For non-restaurant users, get from the search input
        const customerSearch = document.getElementById('customer_search');
        customerName = customerSearch ? customerSearch.value : '';
    }

    const customerInfo = customerName ? `{{ __('messages.Customer') }}: ${customerName}` : '';

    document.getElementById('print-customer').textContent = customerInfo;
    document.getElementById('receipt-customer').textContent = customerInfo;

    // Update bill ID if available
    if (currentBillId) {
        document.getElementById('current-bill-id').textContent = currentBillId;
        document.getElementById('receipt-current-bill-id').textContent = currentBillId;
    }
}
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
            
            if (e.key === 'F1' && !isRestaurant) {
                e.preventDefault();
                document.getElementById('barcode_input').focus();
            }
        });

        // High-performance scroll handler with throttling (now for both restaurant and regular users)
        document.getElementById('product-cards-container').addEventListener('scroll', (e) => {
            if (scrollTimeout) return;
            
            scrollTimeout = setTimeout(() => {
                const container = e.target;
                const scrollTop = container.scrollTop;
                const scrollHeight = container.scrollHeight;
                const clientHeight = container.clientHeight;
                
                if (scrollTop + clientHeight >= scrollHeight - 100) {
                    fetchProducts();
                }
                scrollTimeout = null;
            }, 150);
        });

        // Enhanced notification system with better performance
        function showNotification(message, type = 'info') {
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
            
            notification.className = `notification-toast fixed top-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
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

        // Update today's sales display
        function updateTotalSalesToday() {
            // You can add real-time updates here if needed
        }

        // Enhanced form validation
        document.getElementById('create-bill').addEventListener('submit', (e) => {
            const rows = document.querySelectorAll('.product-row');
            if (rows.length === 0) {
                e.preventDefault();
                showNotification('{{ __('messages.Please add at least one product to the bill') }}', 'warning');
                return;
            }

            let hasError = false;
            rows.forEach(row => {
                const qty = parseInt(row.querySelector('.quantity')?.value || 0);
                const max = parseInt(row.querySelector('.quantity')?.max || 999);
                
                if (qty > max) {
                    hasError = true;
                    showNotification('{{ __('messages.Some products exceed available stock') }}', 'error');
                }
            });

            if (hasError) {
                e.preventDefault();
                return;
            }

            showNotification('{{ __('messages.Creating bill...') }}', 'info');
        });

        // Auto-focus management
        document.addEventListener('DOMContentLoaded', () => {
            if (!isRestaurant) {
                document.getElementById('barcode_input').focus();
            }
        });

        // Prevent form submission on Enter in barcode input (only for non-restaurant)
        if (!isRestaurant) {
            document.getElementById('barcode_input').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }

        // Cleanup function for better memory management
        window.addEventListener('beforeunload', () => {
            if (intersectionObserver) {
                intersectionObserver.disconnect();
            }
            
            clearTimeout(debounceTimeout);
            clearTimeout(scrollTimeout);
            clearTimeout(customerDebounceTimeout);
            clearTimeout(paymentCustomerDebounceTimeout);
            
            renderQueue = [];
            isRenderingQueue = false;
        });

        // Set current bill ID when bill is created (you'll need to handle this in your Laravel response)
        window.setBillId = function(billId) {
            currentBillId = billId;
        };
    </script>
</x-app-layout>