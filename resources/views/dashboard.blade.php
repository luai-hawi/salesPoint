@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    
    // Get shop name based on user role
    $shopName = __('messages.Shop'); // Default fallback
    if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id) {
        $shopName = auth()->user()-> shopOwner->name ?? 'Shop';
    } elseif (auth()->user()->role !== 'employee') {
        $shopName = auth()->user()->name ?? 'Shop';
    }
    $isRestaurant = auth()->user()->role === 'restaurant' || (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id && auth()->user()->shopOwner->role === 'restaurant');
@endphp
<x-app-layout>
    <x-slot name="header">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <h2 class="font-bold text-xl sm:text-2xl text-gray-800 leading-tight flex items-center">
            <!-- Your existing header content -->
        </h2>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
            <!-- Add this cash drawer button -->
            <button type="button" id="open-cash-drawer" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-3 rounded-lg transition-colors flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                {{ __('dashboard.Open Drawer') }}
            </button>
            
            <!-- Your existing today's sales display -->
            <div class="text-xs sm:text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
                {{ __('dashboard.Today\'s Sales') }}: <span class="font-bold text-green-600">₪{{ number_format($totalToday ?? 0, 2) }}</span>
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
                    <div id="product-cards-container" class="max-h-96 overflow-y-auto">
                        <div id="product-results" class="grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 p-4">
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
                                            <option value="{{ $customer->id }}" 
                                                    data-name="{{ $customer->name }}" 
                                                    data-phone="{{ $customer->phone }}" 
                                                    data-balance="{{ $customer->balance }}"
                                                    data-last-bill="{{ $customer->last_bill_amount ?? 0 }}"
                                                    data-last-bill-id="{{ $customer->last_bill_id ?? '' }}"
                                                    data-last-bill-date="{{ $customer->last_bill_date ?? '' }}">
                                                {{ $customer->name }} - {{ $customer->phone }} 
                                                @if(($customer->last_bill_amount ?? 0) > 0)
                                                    ({{ __('dashboard.Last Bill') }}: ₪{{ number_format($customer->last_bill_amount, 2) }})
                                                @else
                                                    ({{ __('dashboard.No Recent Bill') }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Customer Balance Info - Updated to show last bill -->
                                <div id="customer-balance-info" class="hidden p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-blue-800">{{ __('dashboard.Last Bill Amount') }}:</span>
                                        <span id="current-debt" class="text-sm font-bold text-blue-900">₪0.00</span>
                                    </div>
                                    <div class="text-xs text-blue-600 mt-1" id="bill-date-info"></div>
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
                                
                                <!-- Change Calculator Panel -->
                                <div id="change-calculator" class="hidden p-4 bg-gradient-to-r from-green-50 to-blue-50 border-2 border-dashed border-green-300 rounded-lg">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        {{ __('dashboard.Change Calculator') }}
                                    </h4>
                                    
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">{{ __('dashboard.Customer Debt') }}:</span>
                                            <span id="calc-debt" class="font-medium text-red-600">₪0.00</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">{{ __('dashboard.Payment Amount') }}:</span>
                                            <span id="calc-payment" class="font-medium text-green-600">₪0.00</span>
                                        </div>
                                        <div class="col-span-2 pt-2 border-t border-gray-300">
                                            <div class="flex justify-between items-center">
                                                <span class="font-semibold text-gray-700">{{ __('dashboard.Result') }}:</span>
                                                <span id="calc-result" class="font-bold text-lg text-blue-600">₪0.00</span>
                                            </div>
                                            <div id="calc-status" class="text-xs text-center mt-1 font-medium text-gray-500"></div>
                                        </div>
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
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
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
                                <button type="button" id="print-receipt-button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
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
                                    <span class="font-bold text-lg">₪<span id="total_discount_display">0.00</span></span>
                                </div>
                                <input type="hidden" id="total_discount" value="0">
                            </div>
                            
                            <div class="bg-white bg-opacity-30 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">{{ __('dashboard.Total Amount:') }}</span>
                                    <span class="font-bold text-2xl">₪<span id="total_price_display">0.00</span></span>
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
                                <span class="font-bold text-blue-800">₪{{ number_format($totalToday ?? 0, 2) }}</span>
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

    <!-- Barcode Duplicate Selection Modal -->
    <div id="barcode-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
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
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ __('dashboard.Multiple Products Found') }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    {{__('messages.Multiple products were found with barcode')}} "<span id="duplicate-barcode"></span>". {{__('messages.Please select which product you want to add')}}:
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
                        {{__('messages.Cancel')}}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimized Styles -->
    <style>
        /* Core styles only - removed duplicate and unused styles */
        .customer-suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #e5e7eb;
        }
        .customer-suggestion-item:hover { background-color: #f3f4f6; }
        .customer-suggestion-item:last-child { border-bottom: none; }

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

        .product-row.compact {
            padding: 8px 12px;
            background-color: #f9fafb;
        }

        .filter-btn.active {
            background-color: rgb(59 130 246);
            color: white;
            border-color: rgb(59 130 246);
        }

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

        .product-row {
            animation: slideIn 0.2s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .product-card:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }
    </style>

    <!-- Clean JavaScript -->
    <script>
        // Global variables
        const products = @json($products);
        let availableTags = [];
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

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            fetchProducts(true);
            
            if (!isRestaurant) {
                document.getElementById('barcode_input').focus();
                setupCustomerSearch();
            } else {
                setupRestaurantCustomerSelectors();
                loadRecentPayments();
            }
            
            fetchTags();
        });

        function setupRestaurantCustomerSelectors() {
            const paymentCustomerSelect = document.getElementById('payment_customer_select');
            const paymentAmountInput = document.getElementById('payment_amount');
            
            if (paymentCustomerSelect) {
                paymentCustomerSelect.addEventListener('change', function() {
                    const selectedOption = this.selectedOptions[0];
                    const customerBalanceInfo = document.getElementById('customer-balance-info');
                    const currentDebtSpan = document.getElementById('current-debt');
                    const billDateInfo = document.getElementById('bill-date-info');
                    const changeCalculator = document.getElementById('change-calculator');
                    
                    if (selectedOption && selectedOption.value) {
                        const customerId = selectedOption.value;
                        const lastBillAmount = parseFloat(selectedOption.dataset.lastBill) || 0;
                        const lastBillId = selectedOption.dataset.lastBillId;
                        const lastBillDate = selectedOption.dataset.lastBillDate;
                        
                        document.getElementById('payment_customer_id').value = customerId;
                        
                        // Show customer bill info
                        customerBalanceInfo.classList.remove('hidden');
                        currentDebtSpan.textContent = `₪${lastBillAmount.toFixed(2)}`;
                        
                        // Update color and info based on last bill amount
                        if (lastBillAmount > 0) {
                            currentDebtSpan.className = 'text-sm font-bold text-red-600';
                            billDateInfo.textContent = lastBillId ? `Bill #${lastBillId} - ${lastBillDate || ''}` : 'Recent bill';
                        } else {
                            currentDebtSpan.className = 'text-sm font-bold text-gray-600';
                            billDateInfo.textContent = 'No recent bills';
                        }
                        
                        loadRecentPayments();
                        updateChangeCalculator();
                        
                    } else {
                        document.getElementById('payment_customer_id').value = '';
                        customerBalanceInfo.classList.add('hidden');
                        changeCalculator.classList.add('hidden');
                        loadRecentPayments();
                    }
                });
            }
            
            // Add event listener to payment amount input for change calculation
            if (paymentAmountInput) {
                paymentAmountInput.addEventListener('input', updateChangeCalculator);
            }
        }

        // Updated change calculator to work with last bill amount only
        function updateChangeCalculator() {
            const paymentCustomerSelect = document.getElementById('payment_customer_select');
            const paymentAmountInput = document.getElementById('payment_amount');
            const changeCalculator = document.getElementById('change-calculator');
            
            if (!paymentCustomerSelect || !paymentAmountInput) return;
            
            const selectedOption = paymentCustomerSelect.selectedOptions[0];
            const paymentAmount = parseFloat(paymentAmountInput.value) || 0;
            
            if (selectedOption && selectedOption.value && paymentAmount > 0) {
                const lastBillAmount = parseFloat(selectedOption.dataset.lastBill) || 0;
                
                // Show calculator
                changeCalculator.classList.remove('hidden');
                
                // Update calculator values
                document.getElementById('calc-debt').textContent = `₪${lastBillAmount.toFixed(2)}`;
                document.getElementById('calc-payment').textContent = `₪${paymentAmount.toFixed(2)}`;
                
                // Calculate result based on last bill only
                let result = 0;
                let status = '';
                let resultClass = 'font-bold text-lg text-blue-600';
                
                if (lastBillAmount > 0) {
                    // Customer has a recent bill
                    result = paymentAmount - lastBillAmount;
                    if (result > 0) {
                        status = '{{ __('dashboard.Return to customer') }}';
                        resultClass = 'font-bold text-lg text-green-600';
                    } else if (result < 0) {
                        status = '{{ __('dashboard.Still owes for this bill') }}';
                        resultClass = 'font-bold text-lg text-red-600';
                        result = Math.abs(result);
                    } else {
                        status = '{{ __('dashboard.Exact payment for bill') }}';
                        resultClass = 'font-bold text-lg text-blue-600';
                    }
                } else {
                    // No recent bill - this is just a payment
                    result = paymentAmount;
                    status = '{{ __('dashboard.Payment without recent bill') }}';
                    resultClass = 'font-bold text-lg text-blue-600';
                }
                
                document.getElementById('calc-result').textContent = `₪${result.toFixed(2)}`;
                document.getElementById('calc-result').className = resultClass;
                document.getElementById('calc-status').textContent = status;
                
            } else {
                changeCalculator.classList.add('hidden');
            }
        }

        // Updated change calculator labels
        function updateCalculatorLabels() {
            // Update the calculator labels to reflect last bill instead of total debt
            const calcDebtLabel = document.querySelector('#change-calculator .text-gray-600');
            if (calcDebtLabel && calcDebtLabel.textContent.includes('Customer Debt')) {
                calcDebtLabel.textContent = '{{ __('dashboard.Last Bill Amount') }}:';
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
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton.innerHTML;
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('dashboard.Processing...') }}
            `;
            
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
                    const result = await response.json();
                    showNotification('Payment added successfully!', 'success');
                    
                    // Reset form
                    this.reset();
                    document.getElementById('payment_customer_id').value = '';
                    document.getElementById('payment_customer_select').value = '';
                    document.getElementById('customer-balance-info').classList.add('hidden');
                    document.getElementById('change-calculator').classList.add('hidden');
                    
                    // Reload recent payments
                    loadRecentPayments();
                    
                    // Update customer balance in dropdown if still selected
                    const paymentCustomerSelect = document.getElementById('payment_customer_select');
                    if (result.new_balance !== undefined) {
                        Array.from(paymentCustomerSelect.options).forEach(option => {
                            if (option.value === customerId) {
                                option.dataset.balance = result.new_balance;
                                // Update option text to reflect new balance
                                const parts = option.textContent.split(' (');
                                const baseText = parts[0];
                                if (result.new_balance != 0) {
                                    option.textContent = `${baseText} ({{ __('dashboard.Debt') }}: ₪${Math.abs(result.new_balance).toFixed(2)})`;
                                } else {
                                    option.textContent = `${baseText} ({{ __('dashboard.No Debt') }})`;
                                }
                            }
                        });
                    }
                    
                } else {
                    const errorData = await response.json();
                    showNotification(errorData.message || 'Failed to add payment', 'error');
                }
            } catch (error) {
                console.error('Payment error:', error);
                showNotification('Failed to add payment', 'error');
            } finally {
                // Restore button
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;
            }
        });

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
                if (!response.ok) throw new Error('Failed to fetch payments');
                
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
                                <div class="font-medium text-gray-900">${parseFloat(payment.amount).toFixed(2)}</div>
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

        // Fetch available tags
        async function fetchTags() {
            try {
                const response = await fetch('/api/tags');
                if (response.ok) {
                    availableTags = await response.json();
                }
            } catch (error) {
                console.error('Failed to fetch tags:', error);
            }
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
            if (!isRestaurant) {
                document.getElementById('barcode_input').focus();
            }
        }

        // Enhanced barcode input handler (only for non-restaurant)
        if (!isRestaurant) {
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
                            result.has_tags = result.has_tags || false;
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
                        <div class="px-8 font-medium text-gray-900">${product.name}</div>
                        <div class="px-8 text-sm text-gray-500">Price: ${product.selling_price} | Stock: ${product.quantity}</div>
                    </div>
                    <button class="select-duplicate-product bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm" data-product='${JSON.stringify(product)}'>
                        {{__('messages.Select')}}
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
                if (!isRestaurant) {
                    document.getElementById('barcode_input').focus();
                }
            }
        });

        // Close modal handlers
        function closeBarcodeModal() {
            document.getElementById('barcode-modal').classList.add('hidden');
        }

        document.getElementById('close-modal')?.addEventListener('click', closeBarcodeModal);
        document.querySelector('.modal-overlay')?.addEventListener('click', closeBarcodeModal);

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

        // Product fetching
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

        // Create product card
        function createProductCard(product) {
            const card = document.createElement('div');
            const isOutOfStock = product.quantity === 0;
            
            card.className = `product-card bg-white p-3 border rounded-lg shadow-sm cursor-pointer ${isOutOfStock ? 'out-of-stock' : ''}`;
            card.dataset.productId = product.id;
            card.dataset.cost_price = product.cost_price;
            card.dataset.selling_price = product.selling_price;
            card.dataset.has_tags = product.has_tags ? 'true' : 'false';
            card.dataset.category = product.category || '';

            let firstImage = null;
            try {
                const pictures = typeof product.pictures === 'string' ? JSON.parse(product.pictures) : product.pictures;
                firstImage = Array.isArray(pictures) ? pictures[0] : null;
            } catch (e) {
                // Silent fail
            }

            const imageHtml = firstImage
                ? `<img src="/storage/${firstImage}" class="w-full h-20 object-cover rounded-lg bg-gray-100" loading="lazy" alt="${product.name}">`
                : `<div class="w-full h-20 bg-gray-200 rounded-lg flex items-center justify-center">
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
                        <div class="text-xs text-gray-500 font-semibold">${product.selling_price}</div>
                        <div class="mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${isOutOfStock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                                ${isOutOfStock ? '{{__('messages.Out of Stock')}}' : `${product.quantity} {{__('messages.in stock')}}`}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            return card;
        }

        // Render products with category grouping
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
                                {{__('messages.Uncategorized')}}
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

        // Loading indicator
        function showLoadingIndicator(show) {
            const indicator = document.getElementById('loading-indicator');
            if (indicator) {
                indicator.classList.toggle('hidden', !show);
            }
        }

        // Add product row
        function addProductRow(product) {
            if (!product) return;

            if (product.quantity === 0) {
                showNotification(`{{ __('messages.{product} is out of stock!') }}`.replace('{product}', product.name), 'warning');
            }

            if (product.has_tags && availableTags.length > 0) {
                showTagsDialog(product);
                return;
            }

            const existing = [...document.querySelectorAll('input[name="product_ids[]"]')].find(input => {
                const row = input.closest('.product-row');
                const tagsInput = row.querySelector('input[name="product_tags[]"]');
                return input.value == product.id && (!tagsInput || !tagsInput.value);
            });

            if (existing) {
                const row = existing.closest('.product-row');
                const qty = row.querySelector('.quantity');
                const currentQty = parseInt(qty.value);
                
                if (currentQty >= product.quantity) {
                    showNotification(`{{ __('messages.Cannot add more {product}. Only {quantity} in stock.') }}`.replace('{product}', product.name).replace('{quantity}', product.quantity), 'warning');
                    return;
                }
                
                qty.value = currentQty + 1;
                calculateTotal();
                return;
            }

            const row = document.createElement('div');
            row.className = 'product-row compact bg-gray-50 border border-gray-200 rounded-lg';

            const id = product.id;
            const cost = product.cost_price;
            const price = product.selling_price;
            const maxStock = product.quantity;

            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${id}">
                <input type="hidden" name="cost_prices[]" value="${cost}">
                <input type="hidden" name="product_tags[]" value="">
                
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

            productsList.appendChild(row);
            calculateTotal();
        }

        // Show tags selection dialog
        function showTagsDialog(product) {
            const modal = document.createElement('div');
            modal.id = 'tags-modal';
            modal.className = 'fixed inset-0 z-50 overflow-y-auto';
            modal.innerHTML = `
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="modal-overlay fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>
                    
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        {{__('messages.Select Tags for')}} ${product.name}
                                    </h3>
                                    <div class="mt-4">
                                        <div id="tags-list" class="space-y-2 max-h-60 overflow-y-auto">
                                            ${availableTags.map(tag => `
                                                <label class="flex items-center p-2 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer">
                                                    <input type="checkbox" value="${tag.id}" data-name="${tag.name}" data-price="${tag.price}" class="tag-checkbox mr-3">
                                                    <div class="flex-1">
                                                        <div class="font-medium">${tag.name}</div>
                                                        <div class="text-sm text-gray-500">+${parseFloat(tag.price).toFixed(2)}</div>
                                                    </div>
                                                </label>
                                            `).join('')}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" id="confirm-tags" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                {{__('messages.Add to Bill')}}
                            </button>
                            <button type="button" id="cancel-tags" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                {{__('messages.Cancel')}}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            document.getElementById('confirm-tags').addEventListener('click', () => {
                const selectedTags = [];
                const checkboxes = modal.querySelectorAll('.tag-checkbox:checked');
                
                checkboxes.forEach(cb => {
                    selectedTags.push(`${cb.dataset.name}@${cb.dataset.price}`);
                });
                
                const tagsString = selectedTags.join('&');
                addProductRowWithTags(product, tagsString);
                document.body.removeChild(modal);
                if (!isRestaurant) {
                    document.getElementById('barcode_input').focus();
                }
            });
            
            document.getElementById('cancel-tags').addEventListener('click', () => {
                document.body.removeChild(modal);
                if (!isRestaurant) {
                    document.getElementById('barcode_input').focus();
                }
            });
            
            modal.querySelector('.modal-overlay').addEventListener('click', () => {
                document.body.removeChild(modal);
                if (!isRestaurant) {
                    document.getElementById('barcode_input').focus();
                }
            });
        }

        // Add product row with tags
        function addProductRowWithTags(product, tagsString) {
            const existing = [...document.querySelectorAll('input[name="product_ids[]"]')].find(input => {
                const row = input.closest('.product-row');
                const tagsInput = row.querySelector('input[name="product_tags[]"]');
                return input.value == product.id && tagsInput && tagsInput.value === tagsString;
            });

            if (existing) {
                const row = existing.closest('.product-row');
                const qty = row.querySelector('.quantity');
                const currentQty = parseInt(qty.value);
                
                if (currentQty >= product.quantity) {
                    showNotification(`{{ __('messages.Cannot add more {product}. Only {quantity} in stock.') }}`.replace('{product}', product.name).replace('{quantity}', product.quantity), 'warning');
                    return;
                }
                
                qty.value = currentQty + 1;
                calculateTotal();
                showNotification(`{{ __('messages.Added {product} with tags to bill') }}`.replace('{product}', product.name), 'success');
                return;
            }

            const row = document.createElement('div');
            row.className = 'product-row compact bg-gray-50 border border-gray-200 rounded-lg';
            
            const tagsDisplay = tagsString ? tagsString.split('&').map(tag => {
                const [name, price] = tag.split('@');
                return `${name} (+${parseFloat(price).toFixed(2)})`;
            }).join(', ') : '';
            
            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${product.id}">
                <input type="hidden" name="cost_prices[]" value="${product.cost_price}">
                <input type="hidden" name="product_tags[]" value="${tagsString}">
                
                <div class="flex items-center justify-between p-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">${product.name}</div>
                        <div class="text-xs text-gray-500">${product.quantity} in stock</div>
                        ${tagsString ? `<div class="text-xs text-blue-600 mt-1">Tags: ${tagsDisplay}</div>` : ''}
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
                            <input type="number" name="selling_prices[]" class="selling-price w-full px-2 py-1 border border-gray-300 rounded text-xs h-7" min="0" step="0.01" value="${product.selling_price}" required>
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
            
            productsList.appendChild(row);
            calculateTotal();
            showNotification(`{{ __('messages.Added {product} with tags to bill') }}`.replace('{product}', product.name), 'success');
        }

        // Clear all products
        document.getElementById('clear-all').addEventListener('click', () => {
            if (confirm('{{ __('messages.Are you sure you want to clear all products?') }}')) {
                productsList.innerHTML = '';
                calculateTotal();
                showNotification('{{ __('messages.All products cleared') }}', 'info');
            }
        });

        // Calculate total
        function calculateTotal() {
            let total = 0;
            let totalDiscount = 0;

            const rows = document.querySelectorAll('.product-row');

            for (const row of rows) {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const discount = parseFloat(row.querySelector('.discount')?.value || 0);
                const price = parseFloat(row.querySelector('.selling-price')?.value || 0);
                const discountType = row.querySelector('.discount-type')?.value || 'total';
                const tagsInput = row.querySelector('input[name="product_tags[]"]');
                const tagsString = tagsInput ? tagsInput.value : '';

                let tagsTotal = 0;
                if (tagsString) {
                    const tagPairs = tagsString.split('&');
                    for (const pair of tagPairs) {
                        if (pair.includes('@')) {
                            const [name, tagPrice] = pair.split('@');
                            tagsTotal += parseFloat(tagPrice) || 0;
                        }
                    }
                }

                let subtotal = (price * qty) + (tagsTotal * qty);
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

        // Event delegation
        document.addEventListener('click', e => {
            if (e.target.closest('.remove-row')) {
                e.target.closest('.product-row').remove();
                calculateTotal();
                showNotification('{{ __('messages.Product removed') }}', 'info');
                return;
            }

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

            const card = e.target.closest('.product-card');
            if (card) {
                const nameElement = card.querySelector('.text-sm.font-medium');
                if (!nameElement) return;

                const product = {
                    id: parseInt(card.dataset.productId),
                    name: nameElement.textContent,
                    cost_price: parseFloat(card.dataset.cost_price),
                    selling_price: parseFloat(card.dataset.selling_price),
                    quantity: parseInt(card.querySelector('.bg-green-100, .bg-red-100')?.textContent.match(/\d+/)?.[0] || 0),
                    has_tags: card.dataset.has_tags === 'true'
                };

                addProductRow(product);
                showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}', product.name), 'success');
                if (!isRestaurant) {
                    document.getElementById('barcode_input').focus();
                }
            }
        });

        document.addEventListener('input', e => {
            if (['quantity', 'discount', 'selling-price'].some(cls => e.target.classList.contains(cls))) {
                calculateTotal();
            }
        });

        // NEW CLEAN PRINT SYSTEM - Always opens in new tab
        
        // Standard Print Button
        document.getElementById('print-button').addEventListener('click', () => {
            const printData = collectPrintData();
            openStandardPrintTab(printData);
        });

        // Receipt Print Button  
        document.getElementById('print-receipt-button').addEventListener('click', () => {
            const printData = collectPrintData();
            openReceiptPrintTab(printData);
        });

        // Collect print data from current form
        function collectPrintData() {
            const rows = document.querySelectorAll('.product-row');
            const products = [];
            let total = 0, totalDiscount = 0, subtotal = 0;

            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const discountValue = parseFloat(row.querySelector('.discount')?.value || 0);
                const price = parseFloat(row.querySelector('.selling-price')?.value || 0);
                const discountType = row.querySelector('.discount-type')?.value || 'total';
                const tagsInput = row.querySelector('input[name="product_tags[]"]');
                const tagsString = tagsInput ? tagsInput.value : '';

                let tagsTotal = 0;
                if (tagsString) {
                    const tagPairs = tagsString.split('&');
                    tagPairs.forEach(pair => {
                        if (pair.includes('@')) {
                            const [name, tagPrice] = pair.split('@');
                            tagsTotal += parseFloat(tagPrice) || 0;
                        }
                    });
                }

                let actualDiscount = 0;
                if (discountType === 'per-unit') {
                    actualDiscount = discountValue * qty;
                } else {
                    actualDiscount = discountValue;
                }

                let name = 'Unknown';
                const nameDiv = row.querySelector('.font-medium.text-gray-900');
                if (nameDiv) name = nameDiv.textContent?.trim() || 'Unknown';

                const unitPriceWithTags = price + tagsTotal;
                const subtotalWithTags = (price * qty) + (tagsTotal * qty);
                const finalSubtotal = Math.max(0, subtotalWithTags - actualDiscount);
                
                subtotal += subtotalWithTags;
                total += finalSubtotal;
                totalDiscount += actualDiscount;

                products.push({
                    name: name,
                    qty: qty,
                    price: price,
                    tagsTotal: tagsTotal,
                    tagsString: tagsString,
                    actualDiscount: actualDiscount,
                    discountType: discountType,
                    finalSubtotal: finalSubtotal
                });
            });

            let customerName = '';
            let customerPhone = '';

            if (isRestaurant) {
                const customerSelect = document.getElementById('customer_id');
                if (customerSelect && customerSelect.value) {
                    const selectedOption = customerSelect.selectedOptions[0];
                    if (selectedOption) {
                        const fullText = selectedOption.textContent;
                        const parts = fullText.split(' - ');
                        customerName = parts[0] || '';
                        customerPhone = parts[1] ? parts[1].split(' (')[0] : '';
                    }
                }
            } else {
                const customerSearch = document.getElementById('customer_search');
                customerName = customerSearch ? customerSearch.value : '';
                if (customerName && customers) {
                    const foundCustomer = customers.find(c => c.name.toLowerCase() === customerName.toLowerCase());
                    customerPhone = foundCustomer ? foundCustomer.phone : '';
                }
            }

            const userDetails = {!! json_encode(auth()->user()->details ?? "") !!}.replace(/\\n/g, '\n');
            const shopOwnerName = 
                @if(auth()->user()->role === 'employee' && auth()->user()->shop_owner_id)
                    '{{ auth()->user()->shopOwner->name ?? 'Shop Owner' }}'
                @else
                    '{{ auth()->user()->name ?? 'Shop Owner' }}'
                @endif
            ;

            return {
                products: products,
                subtotal: subtotal,
                totalDiscount: totalDiscount,
                total: total,
                customerName: customerName,
                customerPhone: customerPhone,
                userDetails: userDetails,
                shopName: shopName,
                shopOwnerName: shopOwnerName,
                userName: '{{ auth()->user()->name }}',
                currentDate: new Date().toLocaleDateString('en-GB'),
                currentTime: new Date().toLocaleTimeString('en-GB', { hour12: false }),
                currentDateTime: new Date().toLocaleString('en-GB'),
                billId: currentBillId || '-'
            };
        }

       // Updated print functions with close button
        function openStandardPrintTab(data) {
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            if (!printWindow) {
                showNotification('Please allow popups for printing', 'error');
                return;
            }

            const standardHtml = generateStandardPrintHtml(data);
            printWindow.document.write(standardHtml);
            printWindow.document.close();

            printWindow.onload = function() {
                // Add close button that won't be printed
                const closeButton = printWindow.document.createElement('button');
                closeButton.innerHTML = '✕ Close Window';
                closeButton.style.cssText = `
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    z-index: 9999;
                    padding: 8px 16px;
                    background-color: #dc2626;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: bold;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                `;
                
                // Hide button when printing
                const style = printWindow.document.createElement('style');
                style.textContent = '@media print { .close-btn { display: none !important; } }';
                printWindow.document.head.appendChild(style);
                closeButton.className = 'close-btn';
                
                closeButton.onclick = () => printWindow.close();
                printWindow.document.body.appendChild(closeButton);
                
                // Show print dialog
                setTimeout(() => {
                    printWindow.print();
                }, 500);
            };
        }

        function openReceiptPrintTab(data) {
            const printWindow = window.open('', '_blank', 'width=400,height=600');
            if (!printWindow) {
                showNotification('Please allow popups for printing', 'error');
                return;
            }

            const receiptHtml = generateReceiptPrintHtml(data);
            printWindow.document.write(receiptHtml);
            printWindow.document.close();

            printWindow.onload = function() {
                // Add close button that won't be printed
                const closeButton = printWindow.document.createElement('button');
                closeButton.innerHTML = '✕ Close';
                closeButton.style.cssText = `
                    position: fixed;
                    top: 5px;
                    right: 5px;
                    z-index: 9999;
                    padding: 4px 8px;
                    background-color: #dc2626;
                    color: white;
                    border: none;
                    border-radius: 3px;
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: bold;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                `;
                
                // Hide button when printing
                const style = printWindow.document.createElement('style');
                style.textContent = '@media print { .close-btn { display: none !important; } }';
                printWindow.document.head.appendChild(style);
                closeButton.className = 'close-btn';
                
                closeButton.onclick = () => printWindow.close();
                printWindow.document.body.appendChild(closeButton);
                
                // Show print dialog
                setTimeout(() => {
                    printWindow.print();
                }, 500);
            };
        }

        // Generate Standard Print HTML - Updated with translations
        function generateStandardPrintHtml(data) {
            const productsHtml = data.products.map(product => {
                const tagsDisplay = product.tagsString ? product.tagsString.split('&').map(tag => {
                    const [name, price] = tag.split('@');
                    return `${name} (+${parseFloat(price).toFixed(2)}₪)`;
                }).join(', ') : '';

                return `
                    <tr>
                        <td class="border-2 border-black px-2 py-1 text-center">
                            <div class="font-semibold text-xs">${product.name}</div>
                            ${tagsDisplay ? `<div class="text-xs text-blue-600">{{ __('messages.Tags') }}: ${tagsDisplay}</div>` : ''}
                        </td>
                        <td class="border-2 border-black px-2 py-1 text-center font-semibold">${product.qty}</td>
                        <td class="border-2 border-black px-2 py-1 text-center font-semibold">
                            <div>${product.price.toFixed(2)}₪</div>
                            ${product.tagsTotal > 0 ? `<small class="text-xs">+${product.tagsTotal.toFixed(2)}₪ {{ __('messages.Tags') }}</small>` : ''}
                        </td>
                        <td class="border-2 border-black px-2 py-1 text-center font-semibold">
                            ${product.actualDiscount > 0 ? `
                                <div>${product.actualDiscount.toFixed(2)}₪</div>
                                <small class="text-xs">${product.discountType === 'per-unit' ? '{{ __('messages.Per Unit') }}' : '{{ __('messages.Total') }}'}</small>
                            ` : '-'}
                        </td>
                        <td class="border-2 border-black px-2 py-1 text-center font-semibold">${product.finalSubtotal.toFixed(2)}₪</td>
                    </tr>
                `;
            }).join('');

            return `
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <title>{{ __('messages.Invoice') }} - ${data.shopName}</title>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <style>
                        * { box-sizing: border-box; margin: 0; padding: 0; }
                        
                        body {
                            font-family: 'Arial', 'Tahoma', sans-serif;
                            font-size: 16px;
                            font-weight: bold;
                            line-height: 1.4;
                            color: black;
                            background: white;
                            margin: 0;
                            padding: 10mm;
                            direction: rtl;
                            text-align: right;
                        }
                        
                        table {
                            width: 100% !important;
                            border-collapse: collapse !important;
                            margin: 3mm 0 !important;
                            direction: rtl;
                        }
                        
                        th, td {
                            border: 2px solid black !important;
                            padding: 4mm !important;
                            text-align: center !important;
                            font-weight: bold !important;
                            font-size: 14px !important;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                            vertical-align: middle;
                        }
                        
                        .text-center { text-align: center !important; }
                        .text-right { text-align: right !important; }
                        .text-left { text-align: left !important; }
                        .font-bold { font-weight: bold !important; }
                        .text-xl { font-size: 20px !important; }
                        .text-lg { font-size: 18px !important; }
                        .text-sm { font-size: 14px !important; }
                        .text-xs { font-size: 11px !important; line-height: 1.3 !important; }
                        .mb-3 { margin-bottom: 3mm !important; }
                        .mb-4 { margin-bottom: 4mm !important; }
                        
                        .grid { display: flex; flex-wrap: wrap; gap: 4mm; margin: 3mm 0; }
                        .grid-cols-2 > div { flex: 1; min-width: 45%; }
                        
                        tfoot tr { background-color: #f3f4f6 !important; }
                        tfoot .bg-gray-100 { background-color: #e5e7eb !important; }
                        tfoot .bg-gray-200 { background-color: #d1d5db !important; }
                        
                        small { display: block; margin-top: 2px; color: #666; font-size: 10px !important; }
                        
                        @media print {
                            @page { margin: 5mm; size: A4; }
                            body { font-size: 16px !important; direction: rtl !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="text-center mb-3">
                        <div class="text-xl font-bold">${data.shopOwnerName}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4 text-xs">
                        <div class="text-right">
                            <div class="font-semibold">${data.shopName}</div>
                            <div class="font-medium">{{ __('messages.Bill number') }}: #${data.billId}</div>
                            <div>{{ __('messages.Printed by') }}: ${data.userName}</div>
                            <div>${data.currentDateTime}</div>
                            ${data.userDetails ? `<div class="mt-1">${data.userDetails.replace(/\n/g, '<br>')}</div>` : ''}
                        </div>
                        
                        <div class="text-left">
                            ${data.customerName ? `<div class="font-semibold">{{ __('messages.Customer') }}: ${data.customerName}</div>` : ''}
                            ${data.customerPhone ? `<div>{{ __('messages.Phone') }}: ${data.customerPhone}</div>` : ''}
                        </div>
                    </div>
                    
                    <table class="w-full border-2 border-black text-xs mb-4">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Product') }}</th>
                                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Quantity') }}</th>
                                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Unit Price') }}</th>
                                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Discount') }}</th>
                                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${productsHtml}
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50">
                                <td colspan="3" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Subtotal') }}</td>
                                <td class="border-2 border-black px-2 py-2 text-center font-bold">-</td>
                                <td class="border-2 border-black px-2 py-2 text-center font-bold">${data.subtotal.toFixed(2)}₪</td>
                            </tr>
                            <tr class="bg-gray-100">
                                <td colspan="3" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Total Discount') }}</td>
                                <td class="border-2 border-black px-2 py-2 text-center font-bold">${data.totalDiscount.toFixed(2)}₪</td>
                                <td class="border-2 border-black px-2 py-2 text-center font-bold">-</td>
                            </tr>
                            <tr class="bg-gray-200">
                                <td colspan="4" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Final Total') }}</td>
                                <td class="border-2 border-black px-2 py-2 text-center font-bold">${data.total.toFixed(2)}₪</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="text-left">
                        <div class="text-xs">HawiTech</div>
                        <div class="text-xs">{{ __('messages.WhatsApp') }}: +(970) 599647713</div>
                    </div>
                </body>
                </html>
            `;
        }

        // Generate Receipt Print HTML - Updated with larger text and removed boldness
        function generateReceiptPrintHtml(data) {
            const productsHtml = data.products.map(product => {
                const tagsDisplayArabic = product.tagsString ? product.tagsString.split('&').map(tag => {
                    const [name, price] = tag.split('@');
                    return `${name} (+${parseFloat(price).toFixed(1)})`;
                }).join(', ') : '';

                return `
                    <tr>
                        <td class="border px-1 py-1 text-center text-sm">
                            <div>${product.name}</div>
                            ${tagsDisplayArabic ? `<div class="text-xs">{{__('messages.Tags')}}: ${tagsDisplayArabic}</div>` : ''}
                        </td>
                        <td class="border px-1 py-1 text-center text-sm">${product.qty}</td>
                        <td class="border px-1 py-1 text-center text-sm">
                            <div>${product.price.toFixed(1)}</div>
                            ${product.tagsTotal > 0 ? `<div class="text-xs">+${product.tagsTotal.toFixed(1)}</div>` : ''}
                        </td>
                        <td class="border px-1 py-1 text-center text-sm">${product.finalSubtotal.toFixed(1)}</td>
                    </tr>
                `;
            }).join('');

            return `
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <title>Receipt - ${data.shopName}</title>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <style>
                        * { box-sizing: border-box; margin: 0; padding: 0; }
                        
                        body {
                            font-family: 'Arial', 'Courier New', monospace;
                            font-size: 14px;
                            font-weight: normal;
                            line-height: 1.4;
                            color: black;
                            background: white;
                            direction: rtl;
                            margin: 0;
                            padding: 2mm;
                            width: 100%;
                            max-width: 104mm;
                            min-width: 56mm;
                        }
                        
                        .receipt-container {
                            width: 100%;
                            max-width: 100%;
                            overflow-wrap: break-word;
                            word-wrap: break-word;
                        }
                        
                        table {
                            width: 100% !important;
                            border-collapse: collapse !important;
                            margin: 1mm 0 !important;
                            table-layout: fixed !important;
                        }
                        
                        th, td {
                            border: 1px solid black !important;
                            padding: 2mm !important;
                            text-align: center !important;
                            font-weight: normal !important;
                            font-size: 13px !important;
                            word-wrap: break-word !important;
                            overflow-wrap: break-word !important;
                            vertical-align: middle !important;
                            hyphens: auto !important;
                        }
                        
                        th {
                            font-weight: bold !important;
                            background-color: #f5f5f5 !important;
                        }
                        
                        /* Column widths for better text fit */
                        .col-product { width: 40% !important; }
                        .col-qty { width: 15% !important; }
                        .col-price { width: 20% !important; }
                        .col-total { width: 25% !important; }
                        
                        h1, h2, h3 {
                            font-size: 16px !important;
                            font-weight: bold !important;
                            margin: 2mm 0 !important;
                            text-align: center !important;
                            word-wrap: break-word !important;
                        }
                        
                        .text-center { text-align: center !important; }
                        .text-right { text-align: right !important; }
                        .text-left { text-align: left !important; }
                        .font-bold { font-weight: bold !important; }
                        .text-lg { font-size: 15px !important; font-weight: normal !important; }
                        .text-sm { font-size: 13px !important; font-weight: normal !important; }
                        .text-xs { font-size: 11px !important; font-weight: normal !important; }
                        .mb-1 { margin-bottom: 1mm !important; }
                        .mb-2 { margin-bottom: 2mm !important; }
                        .mb-3 { margin-bottom: 3mm !important; }
                        .mt-2 { margin-top: 2mm !important; }
                        .mt-3 { margin-top: 3mm !important; }
                        .py-1 { padding: 1mm 0 !important; }
                        .py-2 { padding: 2mm 0 !important; }
                        .bg-gray-200 { background-color: #e5e7eb !important; }
                        
                        .totals-section {
                            border: 1px solid black;
                            margin: 2mm 0;
                        }
                        
                        .totals-row {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 2mm 3mm;
                            border-bottom: 1px solid black;
                            font-weight: normal;
                            font-size: 13px;
                        }
                        
                        .totals-row:last-child {
                            border-bottom: none;
                            background-color: #e5e7eb;
                            font-size: 14px;
                            font-weight: bold;
                        }
                        
                        hr {
                            border: 1px solid black;
                            margin: 2mm 0;
                        }
                        
                        .info-grid {
                            display: flex;
                            justify-content: space-between;
                            margin: 2mm 0;
                            flex-wrap: wrap;
                        }
                        
                        .info-left, .info-right {
                            flex: 1;
                            min-width: 45%;
                            font-size: 12px;
                            font-weight: normal;
                        }
                        
                        .info-left .font-bold,
                        .info-right .font-bold {
                            font-weight: bold;
                        }
                        
                        /* Responsive adjustments for very small widths */
                        @media (max-width: 60mm) {
                            body { font-size: 12px; padding: 1mm; }
                            th, td { font-size: 11px !important; padding: 1mm !important; }
                            h1, h2, h3 { font-size: 14px !important; }
                            .info-grid { flex-direction: column; }
                            .info-left, .info-right { min-width: 100%; margin-bottom: 1mm; }
                            .totals-row { font-size: 11px; padding: 1.5mm 2mm; }
                            .totals-row:last-child { font-size: 12px; }
                        }
                        
                        @media print {
                            @page { 
                                margin: 0; 
                                size: auto;
                            }
                            
                            body {
                                margin: 0 !important;
                                padding: 1mm !important;
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-container">
                        <!-- Header -->
                        <div class="text-center mb-3">
                            <h1>${data.shopName}</h1>
                            <div class="text-sm">HawiTech</div>
                            <div class="text-xs">WhatsApp: +(970) 599647713</div>
                            <hr>
                        </div>

                        <!-- Shop Owner Name -->
                        <div class="text-center mb-2">
                            <div class="text-lg bg-gray-200 py-1" style="padding: 2mm; border: 1px solid black;">
                                ${data.shopOwnerName}
                            </div>
                        </div>

                        <!-- Bill Info -->
                        <div class="info-grid text-sm">
                            <div class="info-right">
                                <div class="font-bold">{{__('messages.Date')}}: ${data.currentDate}</div>
                                <div class="font-bold">{{__('messages.Time')}}: ${data.currentTime}</div>
                            </div>
                            <div class="info-left">
                                <div class="font-bold">{{__('messages.Bill number')}}: ${data.billId}</div>
                                ${data.customerName ? `<div class="font-bold">${data.customerName}</div>` : ''}
                            </div>
                        </div>

                        <!-- Creator Info -->
                        <div class="mb-2">
                            <div class="font-bold text-sm">{{__('messages.Created By')}}: ${data.userName}</div>
                            ${data.userDetails ? `<div class="text-sm">${data.userDetails.replace(/\n/g, '<br>')}</div>` : ''}
                        </div>

                        <!-- Products Table -->
                        <table>
                            <thead>
                                <tr class="bg-gray-200">
                                    <th class="col-product">{{__('messages.Product')}}</th>
                                    <th class="col-qty">{{__('messages.Qty')}}</th>
                                    <th class="col-price">{{__('messages.Unit Price')}}</th>
                                    <th class="col-total">{{__('messages.Total')}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${productsHtml}
                            </tbody>
                        </table>

                        <!-- Totals Section -->
                        <div class="totals-section">
                            <div class="totals-row">
                                <div>{{__('messages.Subtotal')}}:</div>
                                <div>${data.subtotal.toFixed(1)}</div>
                            </div>
                            <div class="totals-row">
                                <div>{{__('messages.Total discount')}}:</div>
                                <div>${data.totalDiscount.toFixed(1)}</div>
                            </div>
                            <div class="totals-row">
                                <div>{{__('messages.Final Total')}}:</div>
                                <div>${data.total.toFixed(1)}</div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-3 text-sm">
                            <div class="mb-1">{{__('messages.Thank you for your business!')}}</div>
                            <hr>
                            <div>HawiTech</div>
                            <div>WhatsApp: +(970) 599647713</div>
                        </div>
                    </div>
                </body>
                </html>
            `;
        }

        // Scroll handler
        document.getElementById('product-cards-container').addEventListener('scroll', (e) => {
            const container = e.target;
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            
            if (scrollTop + clientHeight >= scrollHeight - 100) {
                fetchProducts();
            }
        });

        // Notification system
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

        // Form validation
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

        // Cleanup
        window.addEventListener('beforeunload', () => {
            clearTimeout(debounceTimeout);
            clearTimeout(customerDebounceTimeout);
            clearTimeout(paymentCustomerDebounceTimeout);
        });

        // Set current bill ID
        window.setBillId = function(billId) {
            currentBillId = billId;
        };


// Cash Drawer Integration - FIXED VERSION
class CashDrawerManager {
    constructor() {
        this.platform = this.detectPlatform();
        this.method = this.determineBestMethod();
        this.escPosCommand = '\x1B\x70\x00\x19\xFA'; // ESC/POS drawer open command
    }
    
    detectPlatform() {
        const userAgent = navigator.userAgent.toLowerCase();
        const isAndroid = /android/.test(userAgent);
        const isWindows = /windows/.test(userAgent);
        const isWebView = /wv/.test(userAgent); // Android WebView
        
        if (isWebView || (isAndroid && window.Android)) return 'android-webview';
        if (isAndroid) return 'android-browser';
        if (isWindows) return 'windows';
        return 'other';
    }
    
    determineBestMethod() {
        switch(this.platform) {
            case 'android-webview':
                return window.Android ? 'native-bridge' : 'web-fallback';
            case 'android-browser':
                return 'web-intent';
            case 'windows':
                return navigator.serial ? 'webserial' : 'network-bridge';
            default:
                return 'network-bridge';
        }
    }
    
    async openDrawer() {
        try {
            switch(this.method) {
                case 'native-bridge':
                    return await this.openViaAndroidBridge();
                case 'web-intent':
                    return await this.openViaWebIntent();
                case 'webserial':
                    return await this.openViaWebSerial();
                case 'network-bridge':
                    return await this.openViaNetworkBridge();
                default:
                    return await this.openViaWebFallback();
            }
        } catch (error) {
            console.error('Cash drawer error:', error);
            throw new Error(`Failed to open cash drawer: ${error.message}`);
        }
    }
    
    // Android WebView with native bridge
    async openViaAndroidBridge() {
        if (typeof window.Android !== 'undefined' && window.Android.openCashDrawer) {
            window.Android.openCashDrawer();
            return { success: true, method: 'Android Native Bridge' };
        }
        throw new Error('Android bridge not available');
    }
    
    // Android browser with web intent
    async openViaWebIntent() {
        const intentUrl = `intent://drawer/open#Intent;scheme=cashpos;package=com.yourapp.pos;end`;
        window.location.href = intentUrl;
        return { success: true, method: 'Android Web Intent' };
    }
    
    // Windows/Chrome with WebSerial API
    async openViaWebSerial() {
        if (!navigator.serial) {
            throw new Error('WebSerial not supported');
        }
        
        try {
            const port = await navigator.serial.requestPort();
            await port.open({ baudRate: 9600 });
            
            const writer = port.writable.getWriter();
            const data = new TextEncoder().encode(this.escPosCommand);
            await writer.write(data);
            writer.releaseLock();
            await port.close();
            
            return { success: true, method: 'WebSerial API' };
        } catch (error) {
            throw new Error(`WebSerial failed: ${error.message}`);
        }
    }
    
    // Network bridge (local service) - Works on both Windows and Android
    async openViaNetworkBridge() {
        const endpoints = [
            'http://localhost:8080/drawer/open',
            'http://localhost:3000/drawer/open',
            'http://127.0.0.1:8080/drawer/open'
        ];
        
        for (const endpoint of endpoints) {
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ command: 'open_drawer' }),
                    signal: AbortSignal.timeout(2000)
                });
                
                if (response.ok) {
                    return { success: true, method: `Network Bridge (${endpoint})` };
                }
            } catch (error) {
                console.log(`Failed to connect to ${endpoint}:`, error.message);
            }
        }
        
        throw new Error('No network bridge service found. Please install the POS Bridge service.');
    }
    
    // FIXED: Fallback method with proper string escaping
    async openViaWebFallback() {
        return new Promise((resolve) => {
            // Create a hidden print frame
            const printFrame = document.createElement('iframe');
            printFrame.style.display = 'none';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = 'none';
            document.body.appendChild(printFrame);
            
            // Create the print content using DOM manipulation instead of template literals
            const printDoc = printFrame.contentDocument || printFrame.contentWindow.document;
            printDoc.open();
            printDoc.write('<!DOCTYPE html><html><head><title>Print</title></head><body></body></html>');
            printDoc.close();
            
            // Add event listener for the message
            const messageHandler = (e) => {
                if (e.data === 'drawer-attempted') {
                    cleanup();
                    resolve({ success: true, method: 'Web Fallback (Print Dialog)' });
                }
            };
            
            const cleanup = () => {
                if (printFrame && printFrame.parentNode) {
                    document.body.removeChild(printFrame);
                }
                window.removeEventListener('message', messageHandler);
            };
            
            window.addEventListener('message', messageHandler);
            
            // Try to trigger print dialog in the iframe
            printFrame.onload = () => {
                try {
                    if (printFrame.contentWindow) {
                        // Send message back to parent after attempting print
                        setTimeout(() => {
                            printFrame.contentWindow.print();
                            window.postMessage('drawer-attempted', '*');
                        }, 100);
                    }
                } catch (error) {
                    console.log('Print attempt failed:', error);
                    window.postMessage('drawer-attempted', '*');
                }
            };
            
            // Timeout fallback
            setTimeout(() => {
                if (printFrame && printFrame.parentNode) {
                    cleanup();
                    resolve({ success: true, method: 'Web Fallback (Timeout)' });
                }
            }, 3000);
        });
    }
}

// Initialize cash drawer manager
const drawerManager = new CashDrawerManager();

// Add event listener to the button
document.getElementById('open-cash-drawer')?.addEventListener('click', async function() {
    const originalContent = this.innerHTML;
    
    this.disabled = true;
    this.innerHTML = `
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        {{ __('dashboard.Opening...') }}
    `;
    
    try {
        const result = await drawerManager.openDrawer();
        showNotification(`{{ __('dashboard.Cash drawer opened successfully') }} (${result.method})`, 'success');
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        this.disabled = false;
        this.innerHTML = originalContent;
    }
});
    </script>
</x-app-layout>