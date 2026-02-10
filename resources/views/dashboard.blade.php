@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }

    // Get shop name based on user role
    $shopName = __('messages.Shop'); // Default fallback
    if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id) {
        $shopName = auth()->user()->shopOwner->name ?? 'Shop';
    } elseif (auth()->user()->role !== 'employee') {
        $shopName = auth()->user()->name ?? 'Shop';
    }
    $isRestaurant =
        auth()->user()->role === 'restaurant' ||
        (auth()->user()->role === 'employee' &&
            auth()->user()->shop_owner_id &&
            auth()->user()->shopOwner->role === 'restaurant');
@endphp
<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <h2 class="font-bold text-xl sm:text-2xl text-gray-800 leading-tight flex items-center">
                <!-- Your existing header content -->
            </h2>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                <!-- Date Picker for Bill Date -->
                <div class="flex items-center gap-2">
                    <label for="bill_date" class="text-sm font-medium text-gray-700">{{ __('dashboard.Date') }}:</label>
                    <input type="date" id="bill_date" value="{{ date('Y-m-d') }}"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        onchange="document.getElementById('bill_date_hidden').value = this.value">
                </div>

                <!-- Add this cash drawer button -->
                <button type="button" id="open-cash-drawer"
                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-3 rounded-lg transition-colors flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                        </path>
                    </svg>
                    {{ __('dashboard.Open Drawer') }}
                </button>

                <!-- Today's Sales - Only show if user has view_bills permission -->
                <div class="text-xs sm:text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
                    {{ __('dashboard.Today\'s Sales') }}: <span class="font-bold text-green-600">
                        @if (auth()->user()->hasPermission('view_bills'))
                            ₪{{ number_format($totalToday ?? 0, 2) }}
                        @else
                            -
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </x-slot>

    <!-- Enhanced Layout with Full Screen Width -->
    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="w-full px-4 sm:px-6 lg:px-8">

            <!-- Warning Notification for Out-of-Stock Products -->
            @if ($warningProducts->count() > 0)
                <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z">
                                </path>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-yellow-800">
                                {{ __('messages.Out of Stock Products Warning') }}
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>{{ __('messages.You have {count} products that have been out of stock for {months} months or more. These products will be automatically deactivated in {remaining} months if not restocked.', ['count' => $warningProducts->count(), 'months' => $warningMonths, 'remaining' => $deactivationMonths - $warningMonths]) }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($warningProducts as $product)
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ $product->name }} ({{ $product->months_since_sale }}
                                            {{ __('messages.months') }})
                                        </span>
                                    @endforeach
                                </div>
                                <div class="mt-3">
                                    <a href="{{ route('products.out-of-stock') }}"
                                        class="text-sm font-medium text-yellow-800 hover:text-yellow-900">
                                        {{ __('messages.Manage Out of Stock Products') }} →
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button type="button"
                                    class="inline-flex bg-yellow-50 rounded-md p-1.5 text-yellow-500 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-yellow-50 focus:ring-yellow-600"
                                    onclick="this.parentElement.parentElement.parentElement.parentElement.style.display='none'">
                                    <span class="sr-only">{{ __('messages.Dismiss') }}</span>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 max-w-none">

                <!-- Left Panel - Product Search & Selection ONLY -->
                <div class="lg:col-span-4 space-y-4">
                    <!-- Product Search Controls - Shown for all users -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.Product Search') }}</h3>
                        </div>

                        <!-- Search Input -->
                        <div class="relative mb-4">
                            <input type="text" id="product-search"
                                placeholder="{{ __('dashboard.Search products by name...') }}"
                                class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" />
                            <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                        <!-- Filter Options -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button id="filter-all"
                                class="filter-btn active px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200 transition-colors">
                                {{ __('dashboard.All Products') }}
                            </button>
                            <button id="filter-in-stock"
                                class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                {{ __('dashboard.In Stock Only') }}
                            </button>
                            <button id="filter-out-of-stock"
                                class="filter-btn px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 transition-colors">
                                {{ __('dashboard.Out of Stock') }}
                            </button>
                            <button id="toggle-category-mode"
                                class="px-3 py-1 text-xs rounded-full bg-purple-100 text-purple-700 border border-purple-200 hover:bg-purple-200 transition-colors">
                                {{ __('dashboard.Browse by Category') }}
                            </button>
                        </div>

                        <!-- Back to Categories Button -->
                        <div id="back-to-categories" class="hidden mb-4">
                            <button
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                ← {{ __('dashboard.Back to Categories') }}
                            </button>
                        </div>
                    </div>

                    <!-- Product Results - Shown for all users -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-4 border-b border-gray-100">
                            <h4 class="font-medium text-gray-800 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                {{ __('dashboard.Available Products') }}
                            </h4>
                        </div>
                        <div id="product-cards-container" class="max-h-96 overflow-y-auto">
                            <div id="product-results"
                                class="grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 p-4">
                                <!-- Products will be loaded here -->
                            </div>
                            <div id="loading-indicator" class="hidden p-4 text-center">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto">
                                </div>
                                <p class="text-sm text-gray-500 mt-2">{{ __('dashboard.Loading products...') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content - Bill Creation AND Quick Payments (for Restaurant) -->
                <div class="lg:col-span-6 space-y-4">

                    <!-- Bill Form -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                {{ __('dashboard.Create New Bill') }}
                            </h3>
                        </div>

                        <form id="create-bill" method="POST" action="{{ route('bills.store') }}" class="p-6">
                            @csrf
                            <input type="hidden" id="bill_date_hidden" name="bill_date"
                                value="{{ date('Y-m-d') }}">

                            <!-- Customer, Note, and Damaged in one row -->
                            <div class="mb-6">
                                <div class="flex justify-between items-start">
                                    <!-- Customer Selection -->
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Customer') }}</label>
                                        @if ($isRestaurant)
                                            <!-- Restaurant: Dropdown selector -->
                                            <select name="customer_id" id="customer_id"
                                                class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">{{ __('dashboard.Select Customer') }}</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}"
                                                        data-name="{{ $customer->name }}"
                                                        data-phone="{{ $customer->phone }}"
                                                        data-balance="{{ $customer->balance }}"
                                                        data-last-bill="{{ $customer->last_bill_amount ?? 0 }}"
                                                        data-last-bill-id="{{ $customer->last_bill_id ?? '' }}"
                                                        data-last-bill-date="{{ $customer->last_bill_date ?? '' }}">
                                                        {{ $customer->name }} - {{ $customer->phone }} (Balance:
                                                        {{ $customer->balance ?? '0.00' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <!-- Regular: Search input -->
                                            <div class="relative">
                                                <input type="text" id="customer_search" name="customer_search"
                                                    placeholder="{{ __('dashboard.Search customer by name or enter new customer...') }}"
                                                    class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                                    autocomplete="off" />
                                                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                    </path>
                                                </svg>
                                                <input type="hidden" name="customer_id" id="customer_id_hidden"
                                                    value="">

                                                <!-- Customer suggestions dropdown -->
                                                <div id="customer_suggestions"
                                                    class="hidden absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto mt-1">
                                                    <!-- Suggestions will be populated here -->
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Note -->
                                    <div>
                                        <label for="note"
                                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Note') }}</label>
                                        <textarea name="note" id="note" rows="2"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                            placeholder="{{ __('dashboard.Add any notes for this bill...') }}"></textarea>
                                    </div>

                                    <!-- Damaged Toggle -->
                                    <div>
                                        <label for="is_damaged"
                                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Damaged Bill') }}</label>
                                        <div class="flex items-center">
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="is_damaged" id="is_damaged">
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span
                                                class="ml-2 text-sm text-gray-600">{{ __('dashboard.100% discount') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Products List -->
                            <div class="products-table-container mb-6">
                                <table class="products-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('messages.Product') }}</th>
                                            <th>{{ __('messages.Quantity') }}</th>
                                            <th>{{ __('messages.Unit Price') }}</th>
                                            <th>{{ __('messages.Discount') }}</th>
                                            <th>{{ __('messages.Total') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="products-list">
                                        <!-- Products will be added here dynamically -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3">
                                <button type="submit"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('dashboard.Create Bill (F2)') }}
                                </button>

                                <button type="button" id="clear-all"
                                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                </button>
                                <button type="button" id="print-button"
                                    class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                                        </path>
                                    </svg>
                                </button>
                                <button type="button" id="print-receipt-button"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>

                    @if ($isRestaurant)
                        <!-- Restaurant Quick Customer Payments Panel - NOW UNDER BILL FORM -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                    </path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    {{ __('dashboard.Quick Customer Payments') }}</h3>
                            </div>

                            <!-- Quick Payment Form -->
                            <form id="quick-payment-form" class="space-y-4">
                                @csrf
                                <input type="hidden" id="payment_customer_id" name="customer_id">

                                <!-- Customer Dropdown for Restaurant -->
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Customer') }}</label>
                                    <select id="payment_customer_select" name="customer_select"
                                        class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                        required>
                                        <option value="">{{ __('dashboard.Select Customer') }}</option>
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}" data-name="{{ $customer->name }}"
                                                data-phone="{{ $customer->phone }}"
                                                data-balance="{{ $customer->balance }}"
                                                data-last-bill="{{ $customer->last_bill_amount ?? 0 }}"
                                                data-last-bill-id="{{ $customer->last_bill_id ?? '' }}"
                                                data-last-bill-date="{{ $customer->last_bill_date ?? '' }}">
                                                {{ $customer->name }} - {{ $customer->phone }}
                                                @if (($customer->last_bill_amount ?? 0) > 0)
                                                    ({{ __('dashboard.Last Bill') }}:
                                                    ₪{{ number_format($customer->last_bill_amount, 2) }})
                                                @else
                                                    ({{ __('dashboard.No Recent Bill') }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Customer Balance Info with Edit Bill Button -->
                                <div id="customer-balance-info"
                                    class="hidden p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span
                                            class="text-sm font-medium text-blue-800">{{ __('dashboard.Last Bill Amount') }}:</span>
                                        <span id="current-debt" class="text-sm font-bold text-blue-900">₪0.00</span>
                                    </div>
                                    <div class="text-xs text-blue-600 mt-1" id="bill-date-info"></div>

                                    <!-- Edit Last Bill Button -->
                                    <div class="mt-3 flex gap-2">
                                        <button type="button" id="edit-last-bill-btn"
                                            class="hidden flex-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium py-2 px-3 rounded-lg transition-colors flex items-center justify-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                </path>
                                            </svg>
                                            {{ __('dashboard.Edit Last Bill') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Rest of the payment form remains the same -->
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Amount') }}</label>
                                        <input type="number" id="payment_amount" name="amount" step="0.01"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                            placeholder="0.00" required>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Type') }}</label>
                                        <select id="payment_type" name="type"
                                            class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                            required>
                                            <option value="cash">{{ __('dashboard.Cash') }}</option>
                                            <option value="card">{{ __('dashboard.Card') }}</option>
                                            <option value="transfer">{{ __('dashboard.Transfer') }}</option>
                                            <option value="check">{{ __('dashboard.Check') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('dashboard.Note') }}</label>
                                        <input type="text" id="payment_note" name="note"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                            placeholder="{{ __('dashboard.Payment note...') }}">
                                    </div>
                                </div>

                                <!-- Change Calculator Panel -->
                                <div id="change-calculator"
                                    class="hidden p-4 bg-gradient-to-r from-green-50 to-blue-50 border-2 border-dashed border-green-300 rounded-lg">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                        {{ __('dashboard.Change Calculator') }}
                                    </h4>

                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">{{ __('dashboard.Customer Debt') }}:</span>
                                            <span id="calc-debt" class="font-medium text-red-600">â‚ª0.00</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">{{ __('dashboard.Payment Amount') }}:</span>
                                            <span id="calc-payment" class="font-medium text-green-600">â‚ª0.00</span>
                                        </div>
                                        <div class="col-span-2 pt-2 border-t border-gray-300">
                                            <div class="flex justify-between items-center">
                                                <span
                                                    class="font-semibold text-gray-700">{{ __('dashboard.Result') }}:</span>
                                                <span id="calc-result"
                                                    class="font-bold text-lg text-blue-600">â‚ª0.00</span>
                                            </div>
                                            <div id="calc-status"
                                                class="text-xs text-center mt-1 font-medium text-gray-500"></div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('dashboard.Add Payment') }}
                                </button>
                            </form>
                        </div>

                        <!-- Recent Payments -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                            <div class="p-4 border-b border-gray-100">
                                <h4 class="font-medium text-gray-800 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                        </path>
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

                <!-- Right Panel - Totals & Summary -->
                <div class="lg:col-span-2 space-y-4">
                    @if (!$isRestaurant)
                        <!-- Barcode Scanner -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center mb-4">
                                <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z">
                                    </path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.Quick Scanner') }}
                                </h3>
                            </div>
                            <div class="relative">
                                <input type="text" id="barcode_input"
                                    placeholder="{{ __('dashboard.Scan or enter barcode...') }}"
                                    class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors font-mono"
                                    autocomplete="off" />
                                <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                    @endif
                    <!-- Bill Summary -->
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-sm text-white p-4">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                </path>
                            </svg>
                            {{ __('dashboard.Bill Summary') }}
                        </h3>

                        <div class="space-y-3">
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <label for="bill_discount_percent"
                                    class="text-green-100 text-xs font-medium block mb-2">
                                    {{ __('messages.Whole Bill Discount (%)') }}
                                </label>
                                <input type="number" id="bill_discount_percent" min="0" max="100"
                                    step="0.01" placeholder="0"
                                    class="w-full px-3 py-2 rounded-md text-gray-900 border border-white/30 bg-white/90 focus:ring-2 focus:ring-white focus:border-transparent" />
                                <p class="text-[10px] text-green-100 mt-1">
                                    {{ __('messages.Distributes discount across all items') }}
                                </p>
                            </div>
                            <div class="bg-white bg-opacity-20 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">{{ __('dashboard.Total Discount:') }}</span>
                                    <span class="font-bold text-lg">₪<span
                                            id="total_discount_display">0.00</span></span>
                                </div>
                                <input type="hidden" id="total_discount" value="0">
                            </div>

                            <div class="bg-white bg-opacity-30 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-green-100">{{ __('dashboard.Total Amount:') }}</span>
                                    <span class="font-bold text-2xl">₪<span
                                            id="total_price_display">0.00</span></span>
                                </div>
                                <input type="hidden" id="total_price" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Today's Sales -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            {{ __('dashboard.Today\'s Performance') }}
                        </h3>

                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="text-sm text-blue-700">{{ __('dashboard.Total Sales:') }}</span>
                                <span
                                    class="font-bold text-blue-800">₪{{ auth()->user()->hasPermission('view_bills') ? number_format($totalToday ?? 0, 2) : '-' }}</span>
                            </div>

                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-700">{{ __('dashboard.Bills Created:') }}</span>
                                <span class="font-bold text-gray-800" id="bills_count">{{ $billsCount }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            {{ __('dashboard.Quick Actions') }}
                        </h3>

                        <div class="space-y-2">
                            <a href="{{ route('bills.index') }}"
                                class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                {{ __('dashboard.View All Bills') }}
                            </a>

                            @if (!$isRestaurant)
                                <a href="{{ route('products.index') }}"
                                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    {{ __('dashboard.Manage Products') }}
                                </a>
                            @endif

                            <a href="{{ route('customers.index') }}"
                                class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium py-2 px-3 rounded-lg transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                    </path>
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
            <div class="modal-overlay fixed inset-0 bg-black bg-opacity-50 transition-opacity" aria-hidden="true">
            </div>

            <div
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z">
                                </path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ __('dashboard.Multiple Products Found') }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    {{ __('messages.Multiple products were found with barcode') }} "<span
                                        id="duplicate-barcode"></span>".
                                    {{ __('messages.Please select which product you want to add') }}:
                                </p>
                            </div>
                            <div id="duplicate-products" class="mt-4 space-y-2">
                                <!-- Duplicate products will be listed here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="close-modal"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('messages.Cancel') }}
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

        .customer-suggestion-item:hover {
            background-color: #f3f4f6;
        }

        .customer-suggestion-item:last-child {
            border-bottom: none;
        }

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
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .product-card:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }

        /* Compact layout improvements */
        .compact-right-panel {
            max-width: 280px;
        }

        .compact-card {
            padding: 12px !important;
        }

        .compact-card h3 {
            font-size: 14px !important;
            margin-bottom: 8px !important;
        }

        .compact-card .text-sm {
            font-size: 12px !important;
        }

        .inline-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            align-items: start;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #f59e0b;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(24px);
        }

        .products-table-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .products-table thead {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
        }

        .products-table th {
            padding: 8px 6px;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
            font-size: 11px;
        }

        .products-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #f3f4f6;
            text-align: center;
        }

        .products-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .products-table input[type="number"] {
            width: 100%;
            padding: 4px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
        }

        .product-name-cell {
            text-align: right !important;
            min-width: 200px;
            word-wrap: break-word;
        }

        .remove-btn-small {
            padding: 4px;
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .remove-btn-small:hover {
            background-color: #dc2626;
        }
    </style>

    <!-- Clean JavaScript -->
    <script>
        // Global variables
        const products = @json($products);
        const categories = @json($categories ?? []);
        const tags = @json($tags ?? []);
        let availableTags = tags; // Use passed tags instead of fetching
        const customers = @json($customers);
        let customerDebounceTimeout = null;
        let paymentCustomerDebounceTimeout = null;
        const totalSalesToday = {{ $totalToday ?? 0 }};
        const productsList = document.getElementById('products-list');
        const isRestaurant = {{ $isRestaurant ? 'true' : 'false' }};
        const shopName = '{{ $shopName }}';
        let currentBillId = null;

        // Function to update bill date
        function updateBillDate(date) {
            document.getElementById('bill_date_hidden').value = date;
        }

        // Translations for dynamic content
        const translations = {
            'Uncategorized': '{{ __('messages.Uncategorized') }}'
        };

        // State management
        let currentFilter = 'all';
        let debounceTimeout = null;
        let currentPage = 1;
        let hasMore = true;
        let isLoading = false;
        let searchTerm = '';
        let browseByCategory = false;
        let currentCategory = null;
        let pendingAction = null;
        let isProcessingNoCustomerAction = false;
        let allProducts = []; // Store all products for local filtering
        let productsLoaded = false; // Flag to check if all products are loaded

        // Message listener for print window communication
        window.addEventListener('message', async (event) => {
            if (event.data.source === 'printWindow') {
                if (event.data.action === 'saveBill') {
                    // For dashboard, we already saved before opening print window
                    // Just send confirmation back to print window
                    if (window.printWindowRef && !window.printWindowRef.closed) {
                        window.printWindowRef.postMessage({
                            action: 'billSaved',
                            success: true
                        }, '*');
                    }
                }
            }
        });

        // Initialize - Load all products initially for local search
        fetchProducts(true);

        if (!isRestaurant) {
            document.getElementById('barcode_input').focus();
            setupCustomerSearch();
        } else {
            setupRestaurantCustomerSelectors();
            loadRecentPayments();
        }

        // Function for AJAX bill submission
        window.submitBillForm = async function() {
            const form = document.getElementById('create-bill');
            if (!form) {
                console.error('Form not found');
                showNotification('{{ __('messages.Form not found. Please refresh the page.') }}', 'error');
                return;
            }

            const formData = new FormData(form);

            // Get CSRF token safely
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            if (!csrfToken) {
                console.error('CSRF token not found');
                showNotification('{{ __('messages.Security token missing. Please refresh the page.') }}',
                    'error');
                return;
            }

            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <svg class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('dashboard.Creating Bill...') }}
            `;

            // Show loading notification
            showNotification('{{ __('messages.Creating bill...') }}', 'info');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    showNotification('{{ __('messages.Bill created successfully!') }}', 'success');

                    if (result.bill && result.bill.id) {
                        currentBillId = result.bill.id;

                        // Update UI after successful bill creation
                        await updateUIAfterBillCreation(result.bill);
                    }
                } else {
                    try {
                        const errorData = await response.json();
                        showNotification(errorData.message || '{{ __('messages.Failed to create bill') }}',
                            'error');
                    } catch (parseError) {
                        const textResponse = await response.text();
                        showNotification('{{ __('messages.Failed to create bill - server error') }}', 'error');
                    }
                }
            } catch (error) {
                showNotification('{{ __('messages.Failed to create bill - network error') }}', 'error');
            } finally {
                // Reset button state
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        };

        // Function to update UI after bill creation
        async function updateUIAfterBillCreation(bill) {
            try {
                // Update product quantities in the displayed cards and allProducts array
                const productRows = document.querySelectorAll('.product-row');
                productRows.forEach(row => {
                    const productId = row.querySelector('input[name="product_ids[]"]').value;
                    const quantityInput = row.querySelector('.quantity');
                    const quantitySold = parseInt(quantityInput.value);

                    // Update the allProducts array with new quantities
                    const productIndex = allProducts.findIndex(p => p.id == productId);
                    if (productIndex !== -1) {
                        allProducts[productIndex].quantity = Math.max(0, allProducts[productIndex].quantity -
                            quantitySold);
                    }

                    // Update product card quantity display
                    const productCards = document.querySelectorAll(`[data-product-id="${productId}"]`);
                    productCards.forEach(productCard => {
                        // Find the span that contains the quantity text (works for both English and Arabic)
                        const quantitySpans = productCard.querySelectorAll('span');
                        let quantitySpan = null;
                        for (const span of quantitySpans) {
                            const text = span.textContent.trim();
                            if (text.includes('in stock') || text.includes('Out of Stock') ||
                                text.includes('متوفر') || text.includes('غير متوفر')) {
                                quantitySpan = span;
                                break;
                            }
                        }

                        if (quantitySpan) {
                            const currentText = quantitySpan.textContent.trim();

                            // Extract current quantity from text
                            const qtyMatch = currentText.match(/(\d+)/);
                            const currentQty = qtyMatch ? parseInt(qtyMatch[1]) : 0;
                            const newQty = Math.max(0, currentQty - quantitySold);

                            // Update the span text and styling
                            if (newQty === 0 && !isRestaurant) {
                                quantitySpan.textContent = 'Out of Stock';
                                quantitySpan.className =
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800';
                                productCard.classList.add('out-of-stock');
                            } else {
                                quantitySpan.textContent = `${newQty} in stock`;
                                if (newQty <= 10) {
                                    quantitySpan.className =
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800';
                                } else {
                                    quantitySpan.className =
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
                                }
                                productCard.classList.remove('out-of-stock');
                            }
                        }
                    });
                });

                // Update today's sales total in header
                const headerSalesElements = document.querySelectorAll('.text-green-600');
                headerSalesElements.forEach(element => {
                    if (element.textContent.includes('₪')) {
                        const currentTotalText = element.textContent.replace('₪', '').replace(',', '').trim();
                        const currentTotal = parseFloat(currentTotalText) || 0;
                        const newTotal = currentTotal + parseFloat(bill.total_price);
                        element.textContent = '₪' + newTotal.toFixed(2);
                    }
                });

                // Update today's sales total in performance section
                const performanceSalesElements = document.querySelectorAll('.text-blue-800');
                performanceSalesElements.forEach(element => {
                    if (element.textContent.includes('₪')) {
                        const currentTotalText = element.textContent.replace('₪', '').replace(',', '').trim();
                        const currentTotal = parseFloat(currentTotalText) || 0;
                        const newTotal = currentTotal + parseFloat(bill.total_price);
                        element.textContent = '₪' + newTotal.toFixed(2);
                    }
                });

                // Update bill count
                const billsCountElement = document.getElementById('bills_count');
                if (billsCountElement) {
                    const currentCount = parseInt(billsCountElement.textContent.trim()) || 0;
                    billsCountElement.textContent = currentCount + 1;
                }

                // For restaurants, update last bill info
                if (isRestaurant) {
                    const customerId = bill.customer_id;

                    // Update both customer selects (bill form and payment form)
                    const customerSelects = [
                        document.getElementById('customer_id'), // Bill form select
                        document.getElementById('payment_customer_select') // Payment form select
                    ];

                    customerSelects.forEach(customerSelect => {
                        if (customerSelect) {
                            // Find the option for this customer
                            const customerOption = Array.from(customerSelect.options).find(option => option
                                .value == customerId);
                            if (customerOption) {
                                customerOption.setAttribute('data-last-bill', bill.total_price);
                                customerOption.setAttribute('data-last-bill-id', bill.id);
                                customerOption.setAttribute('data-last-bill-date', new Date().toISOString()
                                    .split('T')[0]);

                                // Update the display text
                                const customerName = customerOption.getAttribute('data-name');
                                const customerPhone = customerOption.getAttribute('data-phone');
                                customerOption.textContent =
                                    `${customerName} - ${customerPhone} (Last Bill: ₪${parseFloat(bill.total_price).toFixed(2)})`;
                            }
                        }
                    });

                    // Update customer balance info if visible
                    const customerBalanceInfo = document.getElementById('customer-balance-info');
                    if (!customerBalanceInfo.classList.contains('hidden')) {
                        const currentDebtSpan = document.getElementById('current-debt');
                        const billDateInfo = document.getElementById('bill-date-info');
                        const editLastBillBtn = document.getElementById('edit-last-bill-btn');

                        if (currentDebtSpan) {
                            currentDebtSpan.textContent = '₪' + parseFloat(bill.total_price).toFixed(2);
                        }
                        if (billDateInfo) {
                            billDateInfo.innerHTML = `<div class="text-xs text-blue-600 mt-1">Today</div>`;
                        }
                        if (editLastBillBtn) {
                            editLastBillBtn.classList.remove('hidden');
                            editLastBillBtn.onclick = function() {
                                window.open(`/bills/${bill.id}/edit`, '_blank');
                            };
                        }
                    }

                    // Reload recent payments for the customer
                    if (customerId) {
                        // Update the payment customer ID field temporarily and reload payments
                        const originalPaymentCustomerId = document.getElementById('payment_customer_id').value;
                        document.getElementById('payment_customer_id').value = customerId;
                        loadRecentPayments();
                        // Restore original value
                        document.getElementById('payment_customer_id').value = originalPaymentCustomerId;
                    }
                }

                // Clear the form
                clearBillForm();

            } catch (error) {
                console.error('Error updating UI:', error);
            }
        }

        // Function to clear the bill form
        function clearBillForm() {
            // Clear products list
            const productsList = document.getElementById('products-list');
            productsList.innerHTML = '';

            // Reset totals
            document.getElementById('total_price_display').textContent = '0.00';
            document.getElementById('total_discount_display').textContent = '0.00';
            document.getElementById('total_price').value = '0';
            document.getElementById('total_discount').value = '0';

            // Clear customer selection
            const customerSelect = document.getElementById('customer_id');
            if (customerSelect) {
                customerSelect.value = '';
            }

            const customerSearch = document.getElementById('customer_search');
            if (customerSearch) {
                customerSearch.value = '';
            }

            const customerIdHidden = document.getElementById('customer_id_hidden');
            if (customerIdHidden) {
                customerIdHidden.value = '';
            }

            // Clear note
            const noteTextarea = document.getElementById('note');
            if (noteTextarea) {
                noteTextarea.value = '';
            }

            // Reset damaged toggle
            const isDamagedCheckbox = document.getElementById('is_damaged');
            if (isDamagedCheckbox) {
                isDamagedCheckbox.checked = false;
            }

            // Clear customer suggestions
            const customerSuggestions = document.getElementById('customer_suggestions');
            if (customerSuggestions) {
                customerSuggestions.classList.add('hidden');
                customerSuggestions.innerHTML = '';
            }
        }

        function setupRestaurantCustomerSelectors() {
            const paymentCustomerSelect = document.getElementById('payment_customer_select');
            const paymentAmountInput = document.getElementById('payment_amount');
            const editLastBillBtn = document.getElementById('edit-last-bill-btn');
            const billCustomerSelect = document.getElementById('customer_id');

            // Function to toggle print buttons based on customer selection
            function togglePrintButtons() {
                const printButton = document.getElementById('print-button');
                const printReceiptButton = document.getElementById('print-receipt-button');

                if (printButton && printReceiptButton) {
                    // Always enable buttons
                    printButton.disabled = false;
                    printReceiptButton.disabled = false;
                    printButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    printReceiptButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    printButton.classList.add('hover:bg-gray-700');
                    printReceiptButton.classList.add('hover:bg-blue-700');
                }
            }

            // Add customer selection listener for bill form
            if (billCustomerSelect) {
                billCustomerSelect.addEventListener('change', function() {
                    togglePrintButtons();
                });

                // Initial state
                togglePrintButtons();
            }

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
                            billDateInfo.textContent = lastBillId ? `Bill #${lastBillId} - ${lastBillDate || ''}` :
                                'Recent bill';

                            // Show edit button only if there's a valid bill ID
                            if (lastBillId) {
                                editLastBillBtn.classList.remove('hidden');
                                editLastBillBtn.onclick = function() {
                                    window.location.href = `/bills/${lastBillId}/edit`;
                                };
                            } else {
                                editLastBillBtn.classList.add('hidden');
                            }
                        } else {
                            currentDebtSpan.className = 'text-sm font-bold text-gray-600';
                            billDateInfo.textContent = 'No recent bills';
                            editLastBillBtn.classList.add('hidden');
                        }

                        loadRecentPayments();
                        updateChangeCalculator();

                    } else {
                        document.getElementById('payment_customer_id').value = '';
                        customerBalanceInfo.classList.add('hidden');
                        changeCalculator.classList.add('hidden');
                        editLastBillBtn.classList.add('hidden');
                        loadRecentPayments();
                    }
                });
            }

            // Add event listener to payment amount input for change calculation
            if (paymentAmountInput) {
                paymentAmountInput.addEventListener('input', updateChangeCalculator);
            }
        }

        // Function to check if no customer warning should be shown
        function shouldShowNoCustomerWarning() {
            const cookie = document.cookie.split(';').find(c => c.trim().startsWith('hide_no_customer_warning_v2='));
            return !cookie || cookie.split('=')[1] !== 'true';
        }

        // Function to show no customer modal
        function showNoCustomerModal(action) {
            if (!shouldShowNoCustomerWarning()) {
                action();
                return;
            }
            pendingAction = action;

            const modal = document.createElement('div');
            modal.id = 'no-customer-modal';
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
                                        {{ __('dashboard.No Customer Selected') }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            {{ __('dashboard.You are about to proceed without selecting a customer. Are you sure you want to continue?') }}
                                        </p>
                                    </div>
                                    <div class="mt-4">
                                        <label class="flex items-center">
                                            <input type="checkbox" id="dont-show-again" class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                            <span class="text-sm text-gray-700">{{ __('dashboard.Don\'t show this message again') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" id="continue-without-customer" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ __('dashboard.Continue') }}
                            </button>
                            <button type="button" id="cancel-no-customer" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ __('dashboard.Cancel') }}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            document.getElementById('continue-without-customer').addEventListener('click', () => {
                if (document.getElementById('dont-show-again').checked) {
                    document.cookie = "hide_no_customer_warning_v2=true; path=/; max-age=31536000";
                }
                document.body.removeChild(modal);
                if (pendingAction) {
                    pendingAction();
                    pendingAction = null;
                }
            });

            document.getElementById('cancel-no-customer').addEventListener('click', () => {
                document.body.removeChild(modal);
                pendingAction = null;
            });

            modal.querySelector('.modal-overlay').addEventListener('click', () => {
                document.body.removeChild(modal);
                pendingAction = null;
            });
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
                showNotification('{{ __('messages.Please select a customer') }}', 'error');
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content'),
                        'Accept': 'application/json',
                    },
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    showNotification('{{ __('messages.Payment added successfully!') }}', 'success');

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
                                    option.textContent =
                                        `${baseText} ({{ __('dashboard.Debt') }}: ₪${Math.abs(result.new_balance).toFixed(2)})`;
                                } else {
                                    option.textContent =
                                        `${baseText} ({{ __('dashboard.No Debt') }})`;
                                }
                            }
                        });
                    }

                } else {
                    const errorData = await response.json();
                    showNotification(errorData.message || '{{ __('messages.Failed to add payment') }}',
                        'error');
                }
            } catch (error) {
                console.error('Payment error:', error);
                showNotification('{{ __('messages.Failed to add payment') }}', 'error');
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

                const data = await response.json(); // This is the full response object
                const payments = data.payments; // Extract the payments array

                if (!Array.isArray(payments) || payments.length === 0) {
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
                                <div class="font-medium text-gray-900">₪${parseFloat(payment.amount).toFixed(2)}</div>
                                <div class="text-xs text-gray-500 capitalize">${payment.type || 'payment'}</div>
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


        // Fetch categories - now uses local data
        function fetchCategories(search = '') {
            let filteredCategories = categories;
            if (search) {
                filteredCategories = categories.filter(cat =>
                    cat.toLowerCase().includes(search.toLowerCase())
                );
            }
            renderCategories(filteredCategories);
        }

        // Customer search functionality
        function setupCustomerSearch() {
            const searchInput = document.getElementById('customer_search');
            const suggestionsDiv = document.getElementById('customer_suggestions');
            const customerIdInput = document.getElementById('customer_id_hidden');

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
            document.getElementById('customer_id_hidden').value = customer.id;
            document.getElementById('customer_suggestions').classList.add('hidden');
            if (!isRestaurant) {
                document.getElementById('barcode_input').focus();
            }
        }

        // Enhanced barcode input handler (only for non-restaurant) - LOCAL LOOKUP
        if (!isRestaurant) {
            document.getElementById('barcode_input').addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const code = e.target.value.trim();
                    if (!code) return;

                    // Search locally in products array (prefer fully loaded data)
                    const matchingProducts = [];
                    const codeLower = code.toLowerCase();
                    const productsToSearch = (productsLoaded && allProducts.length > 0) ? allProducts : products;
                    productsToSearch.forEach(product => {
                        // Check main barcode
                        if (product.barcode && product.barcode.toLowerCase() === codeLower) {
                            matchingProducts.push(product);
                            return;
                        }
                        // Check additional barcodes
                        if (product.barcodes) {
                            let barcodes = product.barcodes;
                            if (typeof barcodes === 'string') {
                                try {
                                    barcodes = JSON.parse(barcodes);
                                } catch (e) {
                                    barcodes = [];
                                }
                            }

                            if (!Array.isArray(barcodes)) {
                                barcodes = [];
                            }

                            if (barcodes.some(b => b && typeof b === 'string' && b.toLowerCase() ===
                                    codeLower)) {
                                matchingProducts.push(product);
                            }
                        }
                    });

                    if (matchingProducts.length > 1) {
                        showBarcodeModal(matchingProducts, code);
                        e.target.value = '';
                    } else if (matchingProducts.length === 1) {
                        const product = matchingProducts[0];
                        product.has_tags = product.has_tags || false;
                        addProductRow(product);
                        e.target.value = '';
                        showNotification(`{{ __('messages.Added {product} to bill') }}`.replace(
                            '{product}', product.name), 'success');
                    } else {
                        showNotification('{{ __('messages.Product not found for barcode: {code}') }}'
                            .replace('{code}', code), 'warning');
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
                productDiv.className =
                    'flex items-center justify-between p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors';
                productDiv.innerHTML = `
                    <div class="flex-1">
                        <div class="px-8 font-medium text-gray-900">${product.name}</div>
                        <div class="px-8 text-sm text-gray-500">Price: ${product.selling_price} | Stock: ${product.quantity}</div>
                    </div>
                    <button class="select-duplicate-product bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm" data-product='${JSON.stringify(product)}'>
                        {{ __('messages.Select') }}
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
                showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}', product
                    .name), 'success');
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


        // Filter buttons - now local
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                currentFilter = e.target.id.replace('filter-', '');
                renderFilteredProducts();
            });
        });

        // Category mode toggle - now local
        document.getElementById('toggle-category-mode').addEventListener('click', () => {
            browseByCategory = !browseByCategory;
            currentCategory = null;
            showBackButton(false);
            const btn = document.getElementById('toggle-category-mode');
            btn.textContent = browseByCategory ? '{{ __('dashboard.Show All Products') }}' :
                '{{ __('dashboard.Browse by Category') }}';
            // Clear search when toggling modes
            searchTerm = '';
            document.getElementById('product-search').value = '';

            renderFilteredProducts();
        });

        // Back to categories button - now local
        document.getElementById('back-to-categories').querySelector('button').addEventListener('click', () => {
            currentCategory = null;
            renderFilteredProducts();
            showBackButton(false);
        });

        // Enhanced product search with debouncing - now local
        document.getElementById('product-search').addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                searchTerm = this.value.trim();
                renderFilteredProducts();
            }, 300);
        });

        // Product fetching
        function fetchProducts(reset = false, category = null) {
            if (isLoading) return;
            isLoading = true;

            if (reset) {
                const container = document.getElementById('product-results');
                container.replaceChildren();
                currentPage = 1;
                hasMore = true;
                showLoadingIndicator(true);
            }

            const params = new URLSearchParams({
                search: '', // Load all products initially
                page: 1,
                filter: 'all',
                per_page: 999999 // Load all products
            });
            if (category) params.append('category', category);

            fetch(`/products/searchAll?${params}`)
                .then(response => {
                    if (!response.ok) throw new Error('{{ __('messages.Search failed') }}');
                    return response.json();
                })
                .then(data => {
                    const products = data.data || [];

                    // Store all products for local filtering
                    allProducts = products;
                    productsLoaded = true;

                    // Render filtered products
                    renderFilteredProducts();
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

        // Render filtered products locally
        function renderFilteredProducts() {
            if (!productsLoaded) return;

            const container = document.getElementById('product-results');
            container.innerHTML = '';

            if (browseByCategory && !currentCategory) {
                // Show categories
                const categorySet = new Set();
                allProducts.forEach(p => {
                    if (p.category) categorySet.add(p.category);
                });
                renderCategories(Array.from(categorySet));
                return;
            }

            // Filter products
            let filtered = allProducts.filter(product => {
                // Search filter
                if (searchTerm) {
                    const words = searchTerm.toLowerCase().split(/\s+/).filter(word => word.length > 0);
                    const matchesAllWords = words.every(word => {
                        const nameMatch = product.name.toLowerCase().includes(word);
                        const categoryMatch = product.category?.toLowerCase().includes(word);
                        const barcodeMatch = product.barcode && product.barcode.toLowerCase().includes(
                            word);
                        const additionalBarcodeMatch = product.barcodes && Array.isArray(product
                                .barcodes) && product
                            .barcodes.some(b => b && typeof b === 'string' && b.toLowerCase().includes(
                                word));
                        return nameMatch || categoryMatch || barcodeMatch || additionalBarcodeMatch;
                    });
                    if (!matchesAllWords) {
                        return false;
                    }
                }
                // Category filter
                if (currentCategory && product.category !== currentCategory) {
                    return false;
                }
                // Stock filter
                switch (currentFilter) {
                    case 'in-stock':
                        return product.quantity > 0;
                    case 'out-of-stock':
                        return product.quantity === 0;
                    default:
                        return true;
                }
            });

            if (filtered.length === 0) {
                container.innerHTML =
                    '<p class="text-gray-500 text-center py-4 col-span-full">{{ __('messages.No products found') }}</p>';
                return;
            }

            renderProducts(filtered, false, currentCategory);
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

            card.className =
                `product-card bg-white p-3 border rounded-lg shadow-sm cursor-pointer ${isOutOfStock && !isRestaurant ? 'out-of-stock' : ''}`;
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

            const imageHtml = firstImage ?
                `<img data-src="/storage/${firstImage}" class="lazy-image w-full h-20 object-cover rounded-lg bg-gray-100" alt="${product.name}">` :
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
                        <div class="text-sm font-medium text-gray-900" title="${product.name}">${product.name}</div>
                        <div class="text-xs text-gray-500 font-semibold">${product.selling_price}</div>
                        <div class="mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${isOutOfStock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                                ${isOutOfStock ? '{{ __('messages.Out of Stock') }}' : `${!isRestaurant ? `${product.quantity} {{ __('messages.in stock') }}` : ''}`}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            return card;
        }

        // Render products with category grouping
        function renderProducts(products, append = false, currentCategory = null) {
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

            if (!append) {
                container.innerHTML = '';
            }

            // If viewing a specific category, don't add category headers
            const showHeaders = !currentCategory;

            Object.keys(groupedProducts).sort().forEach(category => {
                if (showHeaders && (Object.keys(groupedProducts).length > 1 || uncategorizedProducts.length > 0)) {
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
                if (showHeaders && Object.keys(groupedProducts).length > 0) {
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

            // Initialize lazy loading for new images
            initializeLazyLoading();
        }

        // Lazy loading for product images - loads in batches
        function initializeLazyLoading() {
            const lazyImages = document.querySelectorAll('.lazy-image');
            const imagesArray = Array.from(lazyImages);

            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const index = imagesArray.indexOf(img);
                            // Load this image and the next 11 (batch of 12)
                            for (let i = index; i < Math.min(index + 12, imagesArray.length); i++) {
                                const imageToLoad = imagesArray[i];
                                if (imageToLoad.classList.contains('lazy-image')) {
                                    imageToLoad.src = imageToLoad.dataset.src;
                                    imageToLoad.classList.remove('lazy-image');
                                    observer.unobserve(imageToLoad);
                                }
                            }
                        }
                    });
                });

                lazyImages.forEach(img => imageObserver.observe(img));
            } else {
                // Fallback for browsers without IntersectionObserver
                lazyImages.forEach(img => {
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-image');
                });
            }
        }

        // Render categories for browsing
        function renderCategories(categories) {
            const container = document.getElementById('product-results');
            container.innerHTML = '';

            if (categories.length === 0) {
                container.innerHTML =
                    '<p class="text-gray-500 text-center py-4 col-span-full">{{ __('messages.No categories found') }}</p>';
                return;
            }

            categories.forEach(cat => {
                const displayName = translations[cat] || cat;
                const card = document.createElement('div');
                card.className =
                    'bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 p-8 rounded-xl cursor-pointer text-center border border-blue-300 shadow-sm hover:shadow-md transition-all duration-200 transform hover:scale-105';

                card.innerHTML = `
                    <div class="text-xl font-bold text-blue-800">${displayName}</div>
                `;
                card.onclick = () => {
                    currentCategory = cat;
                    searchTerm = ''; // Clear search when selecting category
                    document.getElementById('product-search').value = '';
                    renderFilteredProducts();
                    showBackButton(true);
                };
                container.appendChild(card);
            });
        }

        // Show/hide back button
        function showBackButton(show) {
            const backBtn = document.getElementById('back-to-categories');
            if (show) {
                backBtn.classList.remove('hidden');
            } else {
                backBtn.classList.add('hidden');
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
                showNotification(`{{ __('messages.{product} is out of stock!') }}`.replace('{product}', product.name),
                    'warning');
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
                    showNotification(`{{ __('messages.Cannot add more {product}. Only {quantity} in stock.') }}`.replace(
                        '{product}', product.name).replace('{quantity}', product.quantity), 'warning');
                    return;
                }

                qty.value = currentQty + 1;
                calculateTotal();
                return;
            }

            const row = document.createElement('tr');
            row.className = 'product-row';

            const id = product.id;
            const cost = product.cost_price;
            const price = product.selling_price;
            const maxStock = product.quantity;

            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${id}">
                <input type="hidden" name="cost_prices[]" value="${cost}">
                <input type="hidden" name="product_tags[]" value="">

                <td class="product-name-cell">
                    <div class="text-sm font-medium text-gray-900" title="${product.name}">${product.name}</div>
                    <div class="text-xs text-gray-500">${maxStock} in stock</div>
                </td>
                <td>
                    <input type="number" name="quantities[]" class="quantity" min="1" value="1" required>
                </td>
                <td>
                    <input type="number" name="selling_prices[]" class="selling-price" min="0" step="0.01" value="${price}" required>
                </td>
                <td>
                    <div class="flex gap-1">
                        <div class="discount-toggle text-xs">
                            <button type="button" class="discount-type-btn active" data-type="total">{{ __('messages.Total') }}</button>
                            <button type="button" class="discount-type-btn" data-type="per-unit">{{ __('messages.Per Unit') }}</button>
                        </div>
                        <input type="number" name="discounts[]" class="discount" min="0" step="0.01" value="0" required>
                        <input type="hidden" name="discount_types[]" class="discount-type" value="total">
                    </div>
                </td>
                <td class="text-center font-semibold total-cell">0.00</td>
                <td>
                    <button type="button" class="remove-row">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </td>
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
                                        {{ __('messages.Select Tags for') }} ${product.name}
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
                                {{ __('messages.Add to Bill') }}
                            </button>
                            <button type="button" id="cancel-tags" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                {{ __('messages.Cancel') }}
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
                    showNotification(`{{ __('messages.Cannot add more {product}. Only {quantity} in stock.') }}`.replace(
                        '{product}', product.name).replace('{quantity}', product.quantity), 'warning');
                    return;
                }

                qty.value = currentQty + 1;
                calculateTotal();
                showNotification(`{{ __('messages.Added {product} with tags to bill') }}`.replace('{product}', product
                    .name), 'success');
                return;
            }

            const row = document.createElement('tr');
            row.className = 'product-row';

            const tagsDisplay = tagsString ? tagsString.split('&').map(tag => {
                const [name, price] = tag.split('@');
                return `${name} (+${parseFloat(price).toFixed(2)})`;
            }).join(', ') : '';

            row.innerHTML = `
                <input type="hidden" name="product_ids[]" value="${product.id}">
                <input type="hidden" name="cost_prices[]" value="${product.cost_price}">
                <input type="hidden" name="product_tags[]" value="${tagsString}">

                <td class="product-name-cell">
                    <div class="text-sm font-medium text-gray-900" title="${product.name}">${product.name}</div>
                    <div class="text-xs text-gray-500">${product.quantity} in stock</div>
                    ${tagsString ? `<div class="text-xs text-blue-600 mt-1">Tags: ${tagsDisplay}</div>` : ''}
                </td>
                <td>
                    <input type="number" name="quantities[]" class="quantity" min="1" value="1" required>
                </td>
                <td>
                    <input type="number" name="selling_prices[]" class="selling-price" min="0" step="0.01" value="${product.selling_price}" required>
                </td>
                <td>
                    <div class="flex gap-1">
                        <div class="discount-toggle text-xs">
                            <button type="button" class="discount-type-btn active" data-type="total">{{ __('messages.Total') }}</button>
                            <button type="button" class="discount-type-btn" data-type="per-unit">{{ __('messages.Per Unit') }}</button>
                        </div>
                        <input type="number" name="discounts[]" class="discount" min="0" step="0.01" value="0" required>
                        <input type="hidden" name="discount_types[]" class="discount-type" value="total">
                    </div>
                </td>
                <td class="text-center font-semibold total-cell">0.00</td>
                <td>
                    <button type="button" class="remove-btn-small">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </td>
            `;

            productsList.appendChild(row);
            calculateTotal();
            showNotification(`{{ __('messages.Added {product} with tags to bill') }}`.replace('{product}', product.name),
                'success');
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
        let isApplyingBillDiscount = false;

        function getBillDiscountPercent() {
            const input = document.getElementById('bill_discount_percent');
            if (!input) return 0;
            const value = parseFloat(input.value);
            if (Number.isNaN(value)) return 0;
            return Math.min(Math.max(value, 0), 100);
        }

        function applyBillDiscountPercent(percent) {
            if (percent <= 0) return;
            isApplyingBillDiscount = true;

            const rows = document.querySelectorAll('.product-row');
            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity')?.value || 0);
                const price = parseFloat(row.querySelector('.selling-price')?.value || 0);
                const tagsInput = row.querySelector('input[name="product_tags[]"]');
                const tagsString = tagsInput ? tagsInput.value : '';

                let tagsTotal = 0;
                if (tagsString) {
                    const tagPairs = tagsString.split('&');
                    for (const pair of tagPairs) {
                        if (pair.includes('@')) {
                            const [, tagPrice] = pair.split('@');
                            tagsTotal += parseFloat(tagPrice) || 0;
                        }
                    }
                }

                const subtotal = (price * qty) + (tagsTotal * qty);
                const discountValue = subtotal * (percent / 100);

                const discountInput = row.querySelector('.discount');
                const discountTypeInput = row.querySelector('.discount-type');
                const discountTypeButtons = row.querySelectorAll('.discount-type-btn');

                if (discountTypeInput) {
                    discountTypeInput.value = 'total';
                }

                if (discountTypeButtons.length) {
                    discountTypeButtons.forEach(btn => btn.classList.remove('active'));
                    const totalButton = row.querySelector('.discount-type-btn[data-type="total"]');
                    if (totalButton) {
                        totalButton.classList.add('active');
                    }
                }

                if (discountInput) {
                    discountInput.value = discountValue.toFixed(2);
                }
            });

            isApplyingBillDiscount = false;
        }

        function calculateTotal() {
            const billDiscountPercent = getBillDiscountPercent();
            if (billDiscountPercent > 0) {
                applyBillDiscountPercent(billDiscountPercent);
            }

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

                // Update the row's total cell
                row.querySelector('.total-cell').textContent = finalSubtotal.toFixed(2);

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
                    quantity: parseInt(card.querySelector('.bg-green-100, .bg-red-100')?.textContent.match(
                        /\d+/)?.[0] || 0),
                    has_tags: card.dataset.has_tags === 'true'
                };

                addProductRow(product);
                showNotification(`{{ __('messages.Added {product} to bill') }}`.replace('{product}', product
                    .name), 'success');
                if (!isRestaurant) {
                    document.getElementById('barcode_input').focus();
                }
            }
        });

        document.addEventListener('input', e => {
            if (e.target && e.target.id === 'bill_discount_percent') {
                calculateTotal();
                return;
            }

            if (['quantity', 'discount', 'selling-price'].some(cls => e.target.classList.contains(cls))) {
                if (e.target.classList.contains('discount') && !isApplyingBillDiscount) {
                    const billDiscountInput = document.getElementById('bill_discount_percent');
                    if (billDiscountInput && parseFloat(billDiscountInput.value || 0) > 0) {
                        billDiscountInput.value = '0';
                    }
                }
                calculateTotal();
            }
        });

        // ENHANCED AUTO-SAVE PRINT SYSTEM

        // Save bill immediately when print is clicked
        async function saveBillBeforePrint() {
            console.log('=== SAVING BILL BEFORE PRINT ===');

            const form = document.getElementById('create-bill');
            if (!form) {
                console.error('Form not found');
                return false;
            }

            const formData = new FormData(form);
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            if (!csrfToken) {
                console.error('CSRF token not found');
                return false;
            }

            try {
                console.log('Sending save request...');
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    console.log('Bill saved successfully:', result);

                    // Store bill data in sessionStorage as backup
                    const billData = {
                        id: result.bill?.id,
                        products: collectPrintData().products,
                        total: collectPrintData().total,
                        timestamp: Date.now()
                    };
                    sessionStorage.setItem('lastSavedBill', JSON.stringify(billData));

                    if (result.bill && result.bill.id) {
                        currentBillId = result.bill.id;
                    }

                    showNotification('{{ __('messages.Bill saved successfully!') }}', 'success');
                    return true;
                } else {
                    const errorData = await response.json();
                    console.error('Save failed:', errorData);
                    showNotification(errorData.message || '{{ __('messages.Failed to save bill') }}', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Save error:', error);
                showNotification('{{ __('messages.Failed to save bill - network error') }}', 'error');
                return false;
            }
        }

        // Standard Print Button
        document.getElementById('print-button').addEventListener('click', async () => {
            // Check customer selection for restaurant role
            if (isRestaurant) {
                const customerSelect = document.getElementById('customer_id');
                if (!customerSelect || !customerSelect.value || customerSelect.value === '') {
                    showNoCustomerModal(async () => {
                        // Save bill first
                        const saved = await saveBillBeforePrint();
                        if (!saved) {
                            alert('Failed to save bill. Please try again.');
                            return;
                        }

                        const printData = collectPrintData();
                        openStandardPrintTab(printData);

                        // Set up redirect after print window closes
                        setupPrintWindowRedirect();
                    });
                    return;
                }
            }

            // Save bill first
            const saved = await saveBillBeforePrint();
            if (!saved) {
                alert('Failed to save bill. Please try again.');
                return;
            }

            const printData = collectPrintData();
            openStandardPrintTab(printData);

            // Set up redirect after print window closes
            setupPrintWindowRedirect();
        });

        // Receipt Print Button
        document.getElementById('print-receipt-button').addEventListener('click', async () => {
            // Check customer selection for restaurant role
            if (isRestaurant) {
                const customerSelect = document.getElementById('customer_id');
                if (!customerSelect || !customerSelect.value || customerSelect.value === '') {
                    showNoCustomerModal(async () => {
                        // Save bill first
                        const saved = await saveBillBeforePrint();
                        if (!saved) {
                            alert('Failed to save bill. Please try again.');
                            return;
                        }

                        const printData = collectPrintData();
                        openReceiptPrintTab(printData);

                        // Set up redirect after print window closes
                        setupPrintWindowRedirect();
                    });
                    return;
                }
            }

            // Save bill first
            const saved = await saveBillBeforePrint();
            if (!saved) {
                alert('Failed to save bill. Please try again.');
                return;
            }

            const printData = collectPrintData();
            openReceiptPrintTab(printData);

            // Set up redirect after print window closes
            setupPrintWindowRedirect();
        });

        // Function to handle redirect after print window closes
        function setupPrintWindowRedirect() {
            // Check if print window is closed every second
            const checkPrintWindowClosed = setInterval(() => {
                if (window.printWindowRef && window.printWindowRef.closed) {
                    clearInterval(checkPrintWindowClosed);
                    // Redirect to dashboard after print window closes
                    setTimeout(() => {
                        window.location.href = '{{ route('dashboard') }}';
                    }, 500); // Small delay
                }
            }, 1000);

            // Also listen for explicit close messages
            const handlePrintWindowClose = (event) => {
                if (event.data.source === 'printWindow' && event.data.action === 'windowClosed') {
                    window.removeEventListener('message', handlePrintWindowClose);
                    clearInterval(checkPrintWindowClosed);
                    setTimeout(() => {
                        window.location.href = '{{ route('dashboard') }}';
                    }, 500);
                }
            };
            window.addEventListener('message', handlePrintWindowClose);
        }

        // Collect print data from current form
        function collectPrintData() {
            const rows = document.querySelectorAll('.product-row');
            const products = [];
            let total = 0,
                totalDiscount = 0,
                subtotal = 0;

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

            const userDetails = {!! json_encode(auth()->user()->details ?? '') !!}.replace(/\\n/g, '\n');
            const shopOwnerName =
                @if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id)
                    '{{ auth()->user()->shopOwner->name ?? 'Shop Owner' }}'
                @else
                    '{{ auth()->user()->name ?? 'Shop Owner' }}'
                @endif ;

            const notes = document.getElementById('note').value.trim();

            return {
                products: products,
                subtotal: subtotal,
                totalDiscount: totalDiscount,
                total: total,
                customerName: customerName,
                customerPhone: customerPhone,
                notes: notes,
                userDetails: userDetails,
                shopName: shopName,
                shopOwnerName: shopOwnerName,
                userName: '{{ auth()->user()->name }}',
                currentDate: new Date().toLocaleDateString('en-GB'),
                currentTime: new Date().toLocaleTimeString('en-GB', {
                    hour12: false
                }),
                currentDateTime: new Date().toLocaleString('en-GB'),
                billId: currentBillId || '-'
            };
        }

        // Enhanced print functions with postMessage communication
        function openStandardPrintTab(data) {
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            if (!printWindow) {
                showNotification('{{ __('messages.Please allow popups for printing') }}', 'error');
                return;
            }

            // Store print window reference for communication
            window.printWindowRef = printWindow;

            const standardHtml = generateStandardPrintHtml(data);
            printWindow.document.write(standardHtml);
            printWindow.document.close();

            printWindow.onload = function() {
                // Create button container
                const buttonContainer = printWindow.document.createElement('div');
                buttonContainer.style.cssText = `
                   position: fixed;
                   top: 10px;
                   right: 10px;
                   z-index: 9999;
                   display: flex;
                   flex-direction: column;
                   gap: 5px;
               `;

                // Add print button
                const printButton = printWindow.document.createElement('button');
                printButton.innerHTML = '🖨️ Print';
                printButton.style.cssText = `
                   padding: 8px 16px;
                   background-color: #2563eb;
                   color: white;
                   border: none;
                   border-radius: 4px;
                   cursor: pointer;
                   font-size: 14px;
                   font-weight: bold;
                   box-shadow: 0 2px 4px rgba(0,0,0,0.3);
               `;

                printButton.onclick = () => {
                    printWindow.print();
                };

                // Add close button that won't be printed
                const closeButton = printWindow.document.createElement('button');
                closeButton.innerHTML = '💾 Save & Close';
                closeButton.style.cssText = `
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

                // Hide buttons when printing
                const style = printWindow.document.createElement('style');
                style.textContent = '@media print { .print-btn, .close-btn { display: none !important; } }';
                printWindow.document.head.appendChild(style);
                printButton.className = 'print-btn';
                closeButton.className = 'close-btn';

                closeButton.onclick = () => {
                    // Send message to parent window to save
                    if (window.opener) {
                        window.opener.postMessage({
                            action: 'saveBill',
                            source: 'printWindow'
                        }, '*');
                    }
                    printWindow.close();
                };

                // Append buttons to container
                buttonContainer.appendChild(printButton);
                buttonContainer.appendChild(closeButton);
                printWindow.document.body.appendChild(buttonContainer);

                // Listen for messages from parent
                window.addEventListener('message', (event) => {
                    if (event.data.action === 'billSaved') {
                        console.log('Bill saved confirmation received');
                    }
                });

                // Save when window is closed by other means
                printWindow.addEventListener('beforeunload', () => {
                    if (window.opener) {
                        window.opener.postMessage({
                            action: 'saveBill',
                            source: 'printWindow'
                        }, '*');
                        // Also notify that window is closing
                        setTimeout(() => {
                            window.opener.postMessage({
                                action: 'windowClosed',
                                source: 'printWindow'
                            }, '*');
                        }, 100);
                    }
                });

                // Show print dialog
                setTimeout(() => {
                    printWindow.print();
                }, 500);
            };
        }

        function openReceiptPrintTab(data) {
            const printWindow = window.open('', '_blank', 'width=400,height=600');
            if (!printWindow) {
                showNotification('{{ __('messages.Please allow popups for printing') }}', 'error');
                return;
            }

            // Store print window reference for communication
            window.printWindowRef = printWindow;

            const receiptHtml = generateReceiptPrintHtml(data);
            printWindow.document.write(receiptHtml);
            printWindow.document.close();

            printWindow.onload = function() {
                // Create button container
                const buttonContainer = printWindow.document.createElement('div');
                buttonContainer.style.cssText = `
                    position: fixed;
                    top: 5px;
                    right: 5px;
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    gap: 3px;
                `;

                // Add print button
                const printButton = printWindow.document.createElement('button');
                printButton.innerHTML = '🖨️ Print';
                printButton.style.cssText = `
                    padding: 4px 8px;
                    background-color: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 3px;
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: bold;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                `;

                printButton.onclick = () => {
                    printWindow.print();
                };

                // Add close button that won't be printed
                const closeButton = printWindow.document.createElement('button');
                closeButton.innerHTML = '✕ Close & Save Bill';
                closeButton.style.cssText = `
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

                // Hide buttons when printing
                const style = printWindow.document.createElement('style');
                style.textContent = '@media print { .print-btn, .close-btn { display: none !important; } }';
                printWindow.document.head.appendChild(style);
                printButton.className = 'print-btn';
                closeButton.className = 'close-btn';

                closeButton.onclick = () => {
                    // Send message to parent window to save
                    if (window.opener) {
                        window.opener.postMessage({
                            action: 'saveBill',
                            source: 'printWindow'
                        }, '*');
                    }
                    printWindow.close();
                };

                // Append buttons to container
                buttonContainer.appendChild(printButton);
                buttonContainer.appendChild(closeButton);
                printWindow.document.body.appendChild(buttonContainer);

                // Listen for messages from parent
                window.addEventListener('message', (event) => {
                    if (event.data.action === 'billSaved') {
                        console.log('Bill saved confirmation received');
                    }
                });

                // Save when window is closed by other means
                printWindow.addEventListener('beforeunload', () => {
                    if (window.opener) {
                        window.opener.postMessage({
                            action: 'saveBill',
                            source: 'printWindow'
                        }, '*');
                        // Also notify that window is closing
                        setTimeout(() => {
                            window.opener.postMessage({
                                action: 'windowClosed',
                                source: 'printWindow'
                            }, '*');
                        }, 100);
                    }
                });

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
                            ${data.notes ? `<div>{{ __('messages.Notes') }}: ${data.notes.replace(/\n/g, '<br>')}</div>` : ''}
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
                            ${tagsDisplayArabic ? `<div class="text-xs">{{ __('messages.Tags') }}: ${tagsDisplayArabic}</div>` : ''}
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
                                <div class="font-bold">{{ __('messages.Date') }}: ${data.currentDate}</div>
                                <div class="font-bold">{{ __('messages.Time') }}: ${data.currentTime}</div>
                            </div>
                            <div class="info-left">
                                <div class="font-bold">{{ __('messages.Bill number') }}: ${data.billId}</div>
                                ${data.customerName ? `<div class="font-bold">${data.customerName}</div>` : ''}
                            </div>
                        </div>

                        <!-- Creator Info -->
                        <div class="mb-2">
                            <div class="font-bold text-sm">{{ __('messages.Created By') }}: ${data.userName}</div>
                            ${data.userDetails ? `<div class="text-sm">${data.userDetails.replace(/\n/g, '<br>')}</div>` : ''}
                            ${data.notes ? `<div class="text-sm"><strong>{{ __('messages.Notes') }}:</strong> ${data.notes.replace(/\n/g, '<br>')}</div>` : ''}
                        </div>

                        <!-- Products Table -->
                        <table>
                            <thead>
                                <tr class="bg-gray-200">
                                    <th class="col-product">{{ __('messages.Product') }}</th>
                                    <th class="col-qty">{{ __('messages.Qty') }}</th>
                                    <th class="col-price">{{ __('messages.Unit Price') }}</th>
                                    <th class="col-total">{{ __('messages.Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${productsHtml}
                            </tbody>
                        </table>

                        <!-- Totals Section -->
                        <div class="totals-section">
                            <div class="totals-row">
                                <div>{{ __('messages.Subtotal') }}:</div>
                                <div>${data.subtotal.toFixed(1)}</div>
                            </div>
                            <div class="totals-row">
                                <div>{{ __('messages.Total discount') }}:</div>
                                <div>${data.totalDiscount.toFixed(1)}</div>
                            </div>
                            <div class="totals-row">
                                <div>{{ __('messages.Final Total') }}:</div>
                                <div>${data.total.toFixed(1)}</div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-3 text-sm">
                            <div class="mb-1">{{ __('messages.Thank you for your business!') }}</div>
                            <hr>
                            <div>HawiTech</div>
                            <div>WhatsApp: +(970) 599647713</div>
                        </div>
                    </div>
                </body>
                </html>
            `;
        }


        // Notification system
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

        // Form validation and AJAX submission
        document.getElementById('create-bill').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (isProcessingNoCustomerAction) {
                isProcessingNoCustomerAction = false;
                return;
            }

            const rows = document.querySelectorAll('.product-row');
            if (rows.length === 0) {
                showNotification('{{ __('messages.Please add at least one product to the bill') }}',
                    'warning');
                return;
            }

            // Check customer selection for restaurant role
            if (isRestaurant) {
                const customerSelect = document.getElementById('customer_id');
                if (!customerSelect || !customerSelect.value || customerSelect.value === '') {
                    showNoCustomerModal(() => {
                        isProcessingNoCustomerAction = true;
                        submitBillForm();
                    });
                    return;
                }
            }

            await submitBillForm();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', async e => {
            if (e.key === 'F2') {
                e.preventDefault();

                // Check customer selection for restaurant role before submitting
                if (isRestaurant) {
                    const customerSelect = document.getElementById('customer_id');
                    if (!customerSelect || !customerSelect.value || customerSelect.value === '') {
                        showNoCustomerModal(async () => {
                            isProcessingNoCustomerAction = true;
                            await submitBillForm();
                        });
                        return;
                    }
                }

                await submitBillForm();
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
                const isSunmi = /sunmi/i.test(userAgent);

                if (isSunmi) return 'sunmi';
                if (isWebView || (isAndroid && window.Android)) return 'android-webview';
                if (isAndroid) return 'android-browser';
                if (isWindows) return 'windows';
                return 'other';
            }

            determineBestMethod() {
                if (navigator.serial) return 'webserial';
                if (navigator.usb) return 'webusb';
                switch (this.platform) {
                    case 'sunmi':
                    case 'android-webview':
                    case 'android-browser':
                        return 'sunmi-sdk';
                    case 'windows':
                        return 'network-bridge';
                    default:
                        return 'network-bridge';
                }
            }

            async openDrawer() {
                try {
                    switch (this.method) {
                        case 'sunmi-sdk':
                            return await this.openViaSunmiSDK();
                        case 'native-bridge':
                            return await this.openViaAndroidBridge();
                        case 'web-intent':
                            return await this.openViaWebIntent();
                        case 'webserial':
                            return await this.openViaWebSerial();
                        case 'webusb':
                            return await this.openViaWebUSB();
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

            // Sunmi SDK for Sunmi POS devices and Android bridge fallback
            async openViaSunmiSDK() {
                if (typeof window.sunmiPrinter !== 'undefined' && window.sunmiPrinter.sendRAWData) {
                    const command = new Uint8Array([0x1B, 0x70, 0x00, 0x19, 0xFA]); // ESC/POS drawer open command
                    window.sunmiPrinter.sendRAWData(command);
                    return {
                        success: true,
                        method: 'Sunmi SDK'
                    };
                } else if (typeof window.Android !== 'undefined' && window.Android.openCashDrawer) {
                    window.Android.openCashDrawer();
                    return {
                        success: true,
                        method: 'Android Native Bridge'
                    };
                }
                throw new Error('Neither Sunmi nor Android bridge API available');
            }

            // Android WebView with native bridge
            async openViaAndroidBridge() {
                if (typeof window.Android !== 'undefined' && window.Android.openCashDrawer) {
                    window.Android.openCashDrawer();
                    return {
                        success: true,
                        method: 'Android Native Bridge'
                    };
                }
                throw new Error('Android bridge not available');
            }

            // Android browser with web intent
            async openViaWebIntent() {
                const intentUrl = `intent://drawer/open#Intent;scheme=cashpos;package=com.yourapp.pos;end`;
                window.location.href = intentUrl;
                return {
                    success: true,
                    method: 'Android Web Intent'
                };
            }

            // Windows/Chrome with WebSerial API
            async openViaWebSerial() {
                if (!navigator.serial) {
                    throw new Error('WebSerial not supported');
                }

                try {
                    const port = await navigator.serial.requestPort();
                    await port.open({
                        baudRate: 9600
                    });

                    const writer = port.writable.getWriter();
                    const data = new TextEncoder().encode(this.escPosCommand);
                    await writer.write(data);
                    writer.releaseLock();
                    await port.close();

                    return {
                        success: true,
                        method: 'WebSerial API'
                    };
                } catch (error) {
                    throw new Error(`WebSerial failed: ${error.message}`);
                }
            }

            // WebUSB for USB connected printers/drawers
            async openViaWebUSB() {
                if (!navigator.usb) {
                    throw new Error('WebUSB not supported');
                }

                try {
                    // Request device - user will select
                    const device = await navigator.usb.requestDevice({
                        filters: []
                    });

                    await device.open();
                    await device.selectConfiguration(1);
                    await device.claimInterface(0);

                    // ESC/POS drawer open command
                    const command = new Uint8Array([0x1B, 0x70, 0x00, 0x19, 0xFA]);

                    // Send to endpoint 1 (bulk out, common for printers)
                    await device.transferOut(1, command);

                    await device.close();

                    return {
                        success: true,
                        method: 'WebUSB'
                    };
                } catch (error) {
                    throw new Error(`WebUSB failed: ${error.message}`);
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
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                command: 'open_drawer'
                            }),
                            signal: AbortSignal.timeout(2000)
                        });

                        if (response.ok) {
                            return {
                                success: true,
                                method: `Network Bridge (${endpoint})`
                            };
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
                    printDoc.write(
                        '<!DOCTYPE html><html><head><title>Print</title></head><body></body></html>');
                    printDoc.close();

                    // Add event listener for the message
                    const messageHandler = (e) => {
                        if (e.data === 'drawer-attempted') {
                            cleanup();
                            resolve({
                                success: true,
                                method: 'Web Fallback (Print Dialog)'
                            });
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
                            resolve({
                                success: true,
                                method: 'Web Fallback (Timeout)'
                            });
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
                showNotification(`{{ __('dashboard.Cash drawer opened successfully') }} (${result.method})`,
                    'success');
            } catch (error) {
                showNotification(error.message, 'error');
            } finally {
                this.disabled = false;
                this.innerHTML = originalContent;
            }
        });
    </script>
</x-app-layout>
