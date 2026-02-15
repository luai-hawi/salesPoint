@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <h2 class="font-bold text-xl lg:text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-6 h-6 lg:w-8 lg:h-8 mr-2 lg:mr-3 text-blue-600" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 00-2 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                {{ __('messages.Financial Dashboard') }}
            </h2>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                <div class="text-xs sm:text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
                    {{ __('messages.Period:') }} <span
                        class="font-bold text-blue-600">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} -
                        {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                </div>
                <div class="text-xs sm:text-sm text-gray-600 bg-blue-100 px-3 py-2 rounded-full">
                    {{ __('messages.Net Income:') }} <span
                        class="font-bold {{ $summaryData['netIncome'] >= 0 ? 'text-green-600' : 'text-red-600' }}">₪{{ number_format($summaryData['netIncome'], 0) }}</span>
                </div>
                <!-- Export Button -->
                <a href="{{ route('dashboard.export-data') }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    {{ __('messages.Export Data') }}
                </a>

                <!-- Print Report Button -->
                <a href="{{ route('dashboard.financial.print-report', ['start_date' => $startDate, 'end_date' => $endDate, 'toDate' => $endDate]) }}"
                    target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                        </path>
                    </svg>
                    {{ __('messages.Print Report') }}
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Full Width Content Area -->
    <div class="w-full min-h-screen bg-gray-50">
        <div class="w-full px-3 sm:px-4 lg:px-6 py-4 lg:py-6">

            <!-- Date Filter Widget - Responsive -->
            <div class="bg-white p-4 shadow-md rounded-lg mb-4 lg:mb-6 border border-gray-200 w-full">
                <form method="GET" action="{{ route('dashboard.financial') }}"
                    class="flex flex-col sm:flex-row items-start sm:items-end gap-3 sm:gap-4">
                    <div class="w-full sm:w-auto">
                        <label class="block text-sm font-medium mb-1 text-gray-700">📅
                            {{ __('messages.Start Date') }}</label>
                        <input type="date" name="start_date" value="{{ $startDate }}"
                            class="w-full sm:w-auto border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div class="w-full sm:w-auto">
                        <label class="block text-sm font-medium mb-1 text-gray-700">📅
                            {{ __('messages.End Date') }}</label>
                        <input type="date" name="end_date" value="{{ $endDate }}"
                            class="w-full sm:w-auto border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 text-sm font-medium">
                        📊 {{ __('messages.Apply Filter') }}
                    </button>
                </form>
            </div>

            <!-- NEW: Daily Cash Flow Section -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-4 lg:p-6 rounded-lg shadow-lg text-white">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 gap-3">
                        <h3 class="text-lg lg:text-xl font-bold flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                </path>
                            </svg>
                            {{ __('messages.Daily Cash Flow') }}
                        </h3>
                        <form method="GET" action="{{ route('dashboard.financial') }}"
                            class="flex items-center gap-2">
                            <div class="flex items-center gap-2">
                                <label
                                    class="text-white text-xs opacity-80">{{ __('messages.Cash Flow Start Date') }}</label>
                                <input type="date" name="cash_flow_start_date" value="{{ $cashFlowStartDate }}"
                                    class="bg-white bg-opacity-20 border border-white border-opacity-30 text-white text-sm rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent"
                                    placeholder="{{ __('messages.Cash Flow Start Date') }}">
                                <span class="text-white opacity-70">-</span>
                                <label
                                    class="text-white text-xs opacity-80">{{ __('messages.Cash Flow End Date') }}</label>
                                <input type="date" name="cash_flow_end_date" value="{{ $cashFlowEndDate }}"
                                    class="bg-white bg-opacity-20 border border-white border-opacity-30 text-white text-sm rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-white focus:border-transparent"
                                    placeholder="{{ __('messages.Cash Flow End Date') }}">
                            </div>
                            <button type="submit"
                                class="px-3 py-2 bg-white text-emerald-700 font-semibold rounded text-sm hover:bg-opacity-90 transition duration-200">
                                {{ __('messages.View') }}
                            </button>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Cash In Section -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <h4 class="text-sm font-semibold opacity-90 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ __('messages.Cash In') }}
                            </h4>
                            <p class="text-2xl lg:text-3xl font-bold text-green-300 mb-2">
                                ₪{{ number_format($dailyCashFlowData['cashIn']['total'], 0) }}</p>
                            <div class="text-xs opacity-75 space-y-1">
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Sales') }}</span><span>₪{{ number_format($dailyCashFlowData['cashIn']['sales'], 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Customer Payments') }}</span><span>₪{{ number_format($dailyCashFlowData['cashIn']['customerPayments'], 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Capital') }}</span><span>₪{{ number_format($dailyCashFlowData['cashIn']['capital'], 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cash Out Section -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <h4 class="text-sm font-semibold opacity-90 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 12H4">
                                    </path>
                                </svg>
                                {{ __('messages.Cash Out') }}
                            </h4>
                            <p class="text-2xl lg:text-3xl font-bold text-red-300 mb-2">
                                ₪{{ number_format($dailyCashFlowData['cashOut']['total'], 0) }}</p>
                            <div class="text-xs opacity-75 space-y-1">
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Supplier Payments') }}</span><span>₪{{ number_format($dailyCashFlowData['cashOut']['supplierPayments'], 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Employee Payments') }}</span><span>₪{{ number_format($dailyCashFlowData['cashOut']['employeePayments'], 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Expenses') }}</span><span>₪{{ number_format($dailyCashFlowData['cashOut']['expenses'], 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('messages.Customer Debt') }}</span><span>₪{{ number_format($dailyCashFlowData['cashOut']['minusPayments'], 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Net Cash Flow -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <h4 class="text-sm font-semibold opacity-90 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                    </path>
                                </svg>
                                {{ __('messages.Net Cash Flow') }}
                            </h4>
                            <p
                                class="text-2xl lg:text-3xl font-bold {{ $dailyCashFlowData['netCashFlow'] >= 0 ? 'text-green-300' : 'text-red-300' }} mb-2">
                                {{ $dailyCashFlowData['netCashFlow'] >= 0 ? '+' : '' }}₪{{ number_format($dailyCashFlowData['netCashFlow'], 0) }}
                            </p>
                            <p class="text-xs opacity-75">
                                {{ $dailyCashFlowData['netCashFlow'] >= 0 ? __('messages.Positive cash flow') : __('messages.Negative cash flow') }}
                            </p>
                        </div>

                        <!-- Cash Flow Summary -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <h4 class="text-sm font-semibold opacity-90 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 00-2 2h2a2 2 0 002-2z">
                                    </path>
                                </svg>
                                {{ __('messages.Summary') }}
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center"><span
                                        class="text-xs opacity-75">{{ __('messages.Total In') }}</span><span
                                        class="text-sm font-semibold text-green-300">₪{{ number_format($dailyCashFlowData['cashIn']['total'], 0) }}</span>
                                </div>
                                <div class="flex justify-between items-center"><span
                                        class="text-xs opacity-75">{{ __('messages.Total Out') }}</span><span
                                        class="text-sm font-semibold text-red-300">₪{{ number_format($dailyCashFlowData['cashOut']['total'], 0) }}</span>
                                </div>
                                <hr class="border-white border-opacity-20">
                                <div class="flex justify-between items-center"><span
                                        class="text-xs opacity-75">{{ __('messages.Balance') }}</span><span
                                        class="text-sm font-bold {{ $dailyCashFlowData['netCashFlow'] >= 0 ? 'text-green-300' : 'text-red-300' }}">{{ $dailyCashFlowData['netCashFlow'] >= 0 ? '+' : '' }}₪{{ number_format($dailyCashFlowData['netCashFlow'], 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Summary Cards - Including Supplier Data -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 lg:gap-4">
                    <div
                        class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">💰
                                    {{ __('messages.Revenue') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">
                                    ₪{{ number_format($summaryData['totalRevenue'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-green-500">💰</div>
                        </div>
                    </div>

                    <div
                        class="bg-white p-4 rounded-lg shadow-md border-l-4 border-blue-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">📈
                                    {{ __('messages.Profit') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">
                                    ₪{{ number_format($summaryData['totalProfit'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-blue-500">📈</div>
                        </div>
                    </div>

                    <div
                        class="bg-white p-4 rounded-lg shadow-md border-l-4 border-red-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">💸
                                    {{ __('messages.Expenses') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">
                                    ₪{{ number_format($summaryData['totalExpenses'] + $summaryData['totalEmployeePayments'], 0) }}
                                </p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-red-500">💸</div>
                        </div>
                    </div>

                    <!-- NEW: Purchases Card -->
                    <div
                        class="bg-white p-4 rounded-lg shadow-md border-l-4 border-orange-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">🛒
                                    {{ __('messages.Purchases') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">
                                    ₪{{ number_format($summaryData['totalPurchases'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-orange-500">🛒</div>
                        </div>
                    </div>

                    <!-- NEW: Supplier Payments Card -->
                    <div
                        class="bg-white p-4 rounded-lg shadow-md border-l-4 border-indigo-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">🏢
                                    {{ __('messages.Supplier Payments') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">
                                    ₪{{ number_format($summaryData['totalSupplierPayments'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-indigo-500">🏢</div>
                        </div>
                    </div>

                    <div
                        class="bg-white p-4 rounded-lg shadow-md border-l-4 border-purple-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">💎
                                    {{ __('messages.Net Income') }}</p>
                                <p
                                    class="text-xl lg:text-2xl font-bold {{ $summaryData['netIncome'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    ₪{{ number_format($summaryData['netIncome'], 0) }}
                                </p>
                            </div>
                            <div
                                class="text-2xl lg:text-3xl {{ $summaryData['netIncome'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $summaryData['netIncome'] >= 0 ? '✅' : '⚠️' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NEW: Store Value Section -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="bg-gradient-to-br from-blue-600 to-purple-700 p-4 lg:p-6 rounded-lg shadow-lg text-white">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4">
                        <h3 class="text-lg lg:text-xl font-bold flex items-center mb-2 lg:mb-0">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v16zM8 9h8M8 13h8">
                                </path>
                            </svg>
                            {{ __('messages.Store Inventory Value') }}
                        </h3>
                        <div class="text-sm bg-white bg-opacity-20 px-3 py-1 rounded-full">
                            {{ __('messages.Current Stock Analysis') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">💰 {{ __('messages.Cost Value') }}
                                </h4>
                                <span class="text-xl">📦</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold">
                                ₪{{ number_format($storeValueData['totalCostValue'], 0) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.Total investment') }}</p>
                        </div>

                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">💎 {{ __('messages.Selling Value') }}
                                </h4>
                                <span class="text-xl">💰</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold">
                                ₪{{ number_format($storeValueData['totalSellingValue'], 0) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.Potential revenue') }}</p>
                        </div>

                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">🎯 {{ __('messages.Potential Profit') }}
                                </h4>
                                <span class="text-xl">📈</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold text-yellow-200">
                                ₪{{ number_format($storeValueData['potentialProfit'], 0) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.If all sold') }}</p>
                        </div>

                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">📊 {{ __('messages.Total Items') }}</h4>
                                <span class="text-xl">🔢</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold">
                                {{ number_format($storeValueData['totalItems']) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.Units in stock') }}</p>
                        </div>

                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">🛍️ {{ __('messages.Products') }}</h4>
                                <span class="text-xl">📋</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold">
                                {{ number_format($storeValueData['totalProducts']) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.Different products') }}</p>
                        </div>
                    </div>

                    <!-- Profit Margin Indicator -->
                    <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="text-sm opacity-90">
                            {{ __('messages.Profit Margin:') }}
                            <span class="font-bold text-yellow-200">
                                {{ $storeValueData['totalCostValue'] > 0 ? number_format(($storeValueData['potentialProfit'] / $storeValueData['totalCostValue']) * 100, 1) : 0 }}%
                            </span>
                        </div>
                        <div class="text-xs opacity-75">
                            {{ __('messages.Based on current inventory at cost vs selling prices') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- NEW: Capital Section -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="bg-gradient-to-br from-emerald-600 to-teal-700 p-4 lg:p-6 rounded-lg shadow-lg text-white">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4">
                        <h3 class="text-lg lg:text-xl font-bold flex items-center mb-2 lg:mb-0">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                </path>
                            </svg>
                            {{ __('messages.Capital') }}
                        </h3>
                        <div class="text-sm bg-white bg-opacity-20 px-3 py-1 rounded-full">
                            {{ __('messages.Outside Investment') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Total Capital -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">{{ __('messages.Total Capital') }}</h4>
                                <span class="text-xl">🏦</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold">
                                ₪{{ number_format($capitalData['total'], 0) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.From outside sources') }}</p>
                        </div>

                        <!-- Products Cost Value -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">{{ __('messages.Products Cost') }}</h4>
                                <span class="text-xl">🛍️</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold">
                                ₪{{ number_format($storeValueData['totalCostValue'], 0) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.Inventory value') }}</p>
                        </div>

                        <!-- Total Capital + Products -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold opacity-90">
                                    {{ __('messages.Total Business Capital') }}</h4>
                                <span class="text-xl">📊</span>
                            </div>
                            <p class="text-xl lg:text-2xl font-bold text-yellow-200">
                                ₪{{ number_format($capitalData['total'] + $storeValueData['totalCostValue'], 0) }}</p>
                            <p class="text-xs opacity-75 mt-1">{{ __('messages.Capital + Inventory') }}</p>
                        </div>

                        <!-- Add Capital Form -->
                        <div class="bg-white bg-opacity-10 p-4 rounded-lg backdrop-blur-sm">
                            <h4 class="text-sm font-semibold opacity-90 mb-3">{{ __('messages.Capital') }}</h4>
                            <p class="text-xl lg:text-2xl font-bold text-white mb-2">
                                ₪{{ number_format($capitalData['total'], 0) }}
                            </p>
                            <button onclick="openCapitalModal()"
                                class="w-full px-3 py-2 bg-white text-emerald-700 font-semibold rounded text-sm hover:bg-opacity-90 transition duration-200">
                                {{ __('messages.Manage Capital') }}
                            </button>
                        </div>
                    </div>

                    <!-- Capital Entries Button -->
                    <div class="mt-4">
                        <button onclick="openCapitalModal()"
                            class="text-sm font-semibold opacity-90 mb-3 flex items-center hover:underline">
                            {{ __('messages.Recent Capital Entries') }}
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Growth Stats - Including Purchase Growth -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-6">
                    <!-- Revenue Chart - Full Width on Mobile -->
                    <div class="xl:col-span-2 bg-white p-4 lg:p-6 rounded-lg shadow-md border border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                            <h3 class="text-base lg:text-lg font-semibold text-gray-800">📈
                                {{ __('messages.Daily Revenue Trend') }}</h3>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                ₪{{ number_format($revenueData['total'], 0) }} {{ __('messages.Total') }}
                            </span>
                        </div>
                        <div class="h-64 lg:h-80">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Enhanced Growth Stats - Responsive Stack -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-3 lg:gap-4">
                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">💰
                                    {{ __('messages.Revenue Growth') }}
                                </h4>
                                <span
                                    class="text-lg">{{ $growthData['revenue']['growth'] >= 0 ? '📈' : '📉' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">
                                ₪{{ number_format($growthData['revenue']['current'], 0) }}</p>
                            <p
                                class="text-sm {{ $growthData['revenue']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['revenue']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['revenue']['growth'], 1) }}%
                                {{ __('messages.vs previous') }}
                            </p>
                        </div>

                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">💎 {{ __('messages.Profit Growth') }}
                                </h4>
                                <span class="text-lg">{{ $growthData['profit']['growth'] >= 0 ? '💰' : '💸' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">
                                ₪{{ number_format($growthData['profit']['current'], 0) }}</p>
                            <p
                                class="text-sm {{ $growthData['profit']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['profit']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['profit']['growth'], 1) }}%
                                {{ __('messages.vs previous') }}
                            </p>
                        </div>

                        <!-- NEW: Purchase Growth -->
                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">🛒
                                    {{ __('messages.Purchase Growth') }}</h4>
                                <span
                                    class="text-lg">{{ $growthData['purchases']['growth'] >= 0 ? '📈' : '📉' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">
                                ₪{{ number_format($growthData['purchases']['current'], 0) }}</p>
                            <p
                                class="text-sm {{ $growthData['purchases']['growth'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['purchases']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['purchases']['growth'], 1) }}%
                                {{ __('messages.vs previous') }}
                            </p>
                        </div>

                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">🎯
                                    {{ __('messages.Expense Control') }}</h4>
                                <span
                                    class="text-lg">{{ $growthData['expenses']['growth'] <= 0 ? '🏆' : '⚠️' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">
                                ₪{{ number_format($growthData['expenses']['current'], 0) }}</p>
                            <p
                                class="text-sm {{ $growthData['expenses']['growth'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['expenses']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['expenses']['growth'], 1) }}%
                                {{ __('messages.vs previous') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Charts Row - Including Supplier Charts -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-6">
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">💎
                                {{ __('messages.Daily Profit') }}</h3>
                            <span class="text-sm text-gray-600">₪{{ number_format($profitData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="profitChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">💸
                                {{ __('messages.Expenses') }}</h3>
                            <span class="text-sm text-gray-600">₪{{ number_format($expenseData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>

                    <!-- NEW: Daily Purchases Chart -->
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">🛒
                                {{ __('messages.Daily Purchases') }}</h3>
                            <span
                                class="text-sm text-gray-600">₪{{ number_format($purchaseData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="purchaseChart"></canvas>
                        </div>
                    </div>

                    <!-- NEW: Supplier Payments Chart -->
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">🏢
                                {{ __('messages.Supplier Payments') }}</h3>
                            <span
                                class="text-sm text-gray-600">₪{{ number_format($supplierPaymentData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="supplierPaymentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer and Employee Payment Charts -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">👥
                                {{ __('messages.Customer Payments') }}</h3>
                            <span
                                class="text-sm text-gray-600">₪{{ number_format($customerPaymentData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="customerPaymentChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">👷
                                {{ __('messages.Employee Payments') }}</h3>
                            <span
                                class="text-sm text-gray-600">₪{{ number_format($employeePaymentData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="employeePaymentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Damaged Products & Customer Balance Charts -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <!-- NEW: Damaged Products Chart -->
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">⚠️
                                {{ __('messages.Damaged Products') }}</h3>
                            <span class="text-sm text-gray-600">₪{{ number_format($damagedData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="damagedChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">👥
                                {{ __('messages.Customer Balance') }}</h3>
                            <span
                                class="text-sm {{ $customerBalanceData['totalOwing'] > $customerBalanceData['totalOwed'] ? 'text-red-600' : 'text-green-600' }}">
                                {{ __('messages.Customers owe you:') }}
                                ₪{{ number_format($customerBalanceData['totalOwing'], 0) }}
                            </span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="customerBalanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products Section -->
            @if (isset($topProducts) && count($topProducts) > 0)
                <div class="w-full mb-4 lg:mb-6">
                    <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md border border-gray-200">
                        <h3 class="text-base lg:text-lg font-semibold text-gray-800 mb-4">🏆
                            {{ __('messages.Top Products') }}
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">#</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                            {{ __('messages.Product') }}
                                        </th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">
                                            {{ __('messages.Quantity Sold') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">
                                            {{ __('messages.Revenue') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">
                                            {{ __('messages.Profit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($topProducts as $index => $product)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                                            <td class="px-3 py-2 text-gray-600">{{ $index + 1 }}</td>
                                            <td class="px-3 py-2 font-medium text-gray-800">{{ $product->name }}</td>
                                            <td class="px-3 py-2 text-right text-gray-600">
                                                {{ number_format($product->total_quantity) }}</td>
                                            <td class="px-3 py-2 text-right font-medium text-green-600">
                                                ₪{{ number_format($product->total_revenue, 0) }}</td>
                                            <td class="px-3 py-2 text-right font-medium text-blue-600">
                                                ₪{{ number_format($product->total_profit, 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- NEW: Top Suppliers Section -->
            @if (isset($topSuppliers) && count($topSuppliers) > 0)
                <div class="w-full mb-4 lg:mb-6">
                    <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md border border-gray-200">
                        <h3 class="text-base lg:text-lg font-semibold text-gray-800 mb-4">🏢
                            {{ __('messages.Top Suppliers') }}
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">#</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                            {{ __('messages.Supplier') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">
                                            {{ __('messages.Bills Count') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">
                                            {{ __('messages.Total Purchases') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($topSuppliers as $index => $supplier)
                                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                                            <td class="px-3 py-2 text-gray-600">{{ $index + 1 }}</td>
                                            <td class="px-3 py-2 font-medium text-gray-800">{{ $supplier->name }}
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-600">
                                                {{ number_format($supplier->total_bills) }}</td>
                                            <td class="px-3 py-2 text-right font-medium text-orange-600">
                                                ₪{{ number_format($supplier->total_purchases, 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Customer and Supplier Balance Sections -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <!-- NEW: Supplier Balance Section -->
                    <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md border border-gray-200">
                        <h3 class="text-base lg:text-lg font-semibold text-gray-800 mb-4">🏢
                            {{ __('messages.Supplier Balance') }}
                        </h3>
                        <div class="mb-4">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-red-600 font-medium">{{ __('messages.We owe suppliers:') }}</span>
                                <span
                                    class="font-bold text-red-600">₪{{ number_format($supplierBalanceData['totalOwing'], 0) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span
                                    class="text-green-600 font-medium">{{ __('messages.Suppliers owe us:') }}</span>
                                <span
                                    class="font-bold text-green-600">₪{{ number_format($supplierBalanceData['totalOwed'], 0) }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Top Suppliers Owed') }}</h4>
                                <div class="h-40">
                                    <canvas id="supplierOwedChart"></canvas>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Top Suppliers Owe Us') }}</h4>
                                <div class="h-40">
                                    <canvas id="supplierOweUsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md border border-gray-200">
                        <h3 class="text-base lg:text-lg font-semibold text-gray-800 mb-4">👥
                            {{ __('messages.Customer Balance') }}
                        </h3>
                        <div class="mb-4">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-red-600 font-medium">{{ __('messages.Customers owe us:') }}</span>
                                <span
                                    class="font-bold text-red-600">₪{{ number_format($customerBalanceData['totalOwing'], 0) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span
                                    class="text-green-600 font-medium">{{ __('messages.We owe customers:') }}</span>
                                <span
                                    class="font-bold text-green-600">₪{{ number_format($customerBalanceData['totalOwed'], 0) }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Top Customers Owed') }}</h4>
                                <div class="h-40">
                                    <canvas id="customerOwedChart"></canvas>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Top Customers Owe Us') }}</h4>
                                <div class="h-40">
                                    <canvas id="customerOweUsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Common chart options
            Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
            Chart.defaults.color = '#666';

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($revenueData['labels']) !!},
                    datasets: [{
                        label: 'Revenue',
                        data: {!! json_encode($revenueData['data']) !!},
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Profit Chart
            const profitCtx = document.getElementById('profitChart').getContext('2d');
            new Chart(profitCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($profitData['labels']) !!},
                    datasets: [{
                        label: 'Profit',
                        data: {!! json_encode($profitData['data']) !!},
                        backgroundColor: '#3B82F6',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Expense Chart
            const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Expenses', 'Remaining'],
                    datasets: [{
                        data: [{{ $expenseData['total'] }}, 100],
                        backgroundColor: ['#EF4444', '#E5E7EB'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Purchase Chart
            const purchaseCtx = document.getElementById('purchaseChart').getContext('2d');
            new Chart(purchaseCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($purchaseData['labels']) !!},
                    datasets: [{
                        label: 'Purchases',
                        data: {!! json_encode($purchaseData['data']) !!},
                        backgroundColor: '#F97316',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Supplier Payment Chart
            const supplierPaymentCtx = document.getElementById('supplierPaymentChart').getContext('2d');
            new Chart(supplierPaymentCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($supplierPaymentData['labels']) !!},
                    datasets: [{
                        label: 'Supplier Payments',
                        data: {!! json_encode($supplierPaymentData['data']) !!},
                        borderColor: '#6366F1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Customer Payment Chart
            const customerPaymentCtx = document.getElementById('customerPaymentChart').getContext('2d');
            new Chart(customerPaymentCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($customerPaymentData['labels']) !!},
                    datasets: [{
                        label: 'Received',
                        data: {!! json_encode($customerPaymentData['received']) !!},
                        backgroundColor: '#10B981',
                        borderRadius: 4
                    }, {
                        label: 'Paid',
                        data: {!! json_encode($customerPaymentData['paid']) !!},
                        backgroundColor: '#EF4444',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            stacked: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Employee Payment Chart
            const employeePaymentCtx = document.getElementById('employeePaymentChart').getContext('2d');
            new Chart(employeePaymentCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($employeePaymentData['byEmployee']['labels']) !!},
                    datasets: [{
                        label: 'Employee Payments',
                        data: {!! json_encode($employeePaymentData['byEmployee']['data']) !!},
                        borderColor: '#EC4899',
                        backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Damaged Chart
            const damagedCtx = document.getElementById('damagedChart').getContext('2d');
            new Chart(damagedCtx, {
                type: 'pie',
                data: {
                    labels: ['Damaged', 'Good'],
                    datasets: [{
                        data: [{{ $damagedData['total'] }}, 100],
                        backgroundColor: ['#EF4444', '#10B981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Customer Balance Chart
            const customerBalanceCtx = document.getElementById('customerBalanceChart').getContext('2d');
            new Chart(customerBalanceCtx, {
                type: 'bar',
                data: {
                    labels: ['Owed to Us', 'We Owe'],
                    datasets: [{
                        label: 'Balance',
                        data: [{{ $customerBalanceData['totalOwing'] }},
                            {{ $customerBalanceData['totalOwed'] }}
                        ],
                        backgroundColor: ['#EF4444', '#10B981'],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Supplier Owed Chart
            const supplierOwedCtx = document.getElementById('supplierOwedChart').getContext('2d');
            new Chart(supplierOwedCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($supplierBalanceData['topOwing']['labels']) !!},
                    datasets: [{
                        label: 'Owed',
                        data: {!! json_encode($supplierBalanceData['topOwing']['data']) !!},
                        backgroundColor: '#EF4444',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Supplier Owe Us Chart
            const supplierOweUsCtx = document.getElementById('supplierOweUsChart').getContext('2d');
            new Chart(supplierOweUsCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($supplierBalanceData['topOwed']['labels']) !!},
                    datasets: [{
                        label: 'Owe Us',
                        data: {!! json_encode($supplierBalanceData['topOwed']['data']) !!},
                        backgroundColor: '#10B981',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Customer Owed Chart
            const customerOwedCtx = document.getElementById('customerOwedChart').getContext('2d');
            new Chart(customerOwedCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($customerBalanceData['topOwing']['labels']) !!},
                    datasets: [{
                        label: 'Owed',
                        data: {!! json_encode($customerBalanceData['topOwing']['data']) !!},
                        backgroundColor: '#EF4444',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Customer Owe Us Chart
            const customerOweUsCtx = document.getElementById('customerOweUsChart').getContext('2d');
            new Chart(customerOweUsCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($customerBalanceData['topOwed']['labels']) !!},
                    datasets: [{
                        label: 'Owe Us',
                        data: {!! json_encode($customerBalanceData['topOwed']['data']) !!},
                        backgroundColor: '#10B981',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        </script>

        <!-- Capital Entries Modal -->
        <div id="capitalModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
            onclick="closeCapitalModal(event)">
            <div class="flex items-center justify-center min-h-screen p-4" onclick="event.stopPropagation()">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">{{ __('messages.Capital') }}</h3>
                        <button onclick="closeCapitalModal()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Add Capital Form -->
                    <div class="p-4 bg-gray-50 border-b">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('messages.Add Capital') }}</h4>
                        <form id="capitalForm" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 gap-3">
                                <input type="number" name="amount" placeholder="{{ __('messages.Amount') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    required min="0.01" step="0.01">
                                <input type="date" name="entry_date" value="{{ date('Y-m-d') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <input type="text" name="note" placeholder="{{ __('messages.Note') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <button type="submit"
                                class="w-full px-3 py-2 bg-emerald-600 text-white font-semibold rounded text-sm hover:bg-emerald-700 transition duration-200">
                                {{ __('messages.Add') }}
                            </button>
                        </form>
                        <div id="capitalMessage" class="mt-2 text-sm text-center hidden"></div>
                    </div>

                    <!-- Date Filter -->
                    <div class="p-4 border-b flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">{{ __('messages.Date') }}:</label>
                        <input type="date" id="capitalDateFilter"
                            class="px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            onchange="filterCapitalEntries()">
                        <button onclick="clearCapitalFilter()"
                            class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">
                            {{ __('messages.Clear') }}
                        </button>
                    </div>

                    <!-- Capital Entries List -->
                    <div class="p-4 overflow-y-auto max-h-[50vh]">
                        @if ($capitalData['entries']->count() > 0)
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                            {{ __('messages.Date') }}</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                            {{ __('messages.Amount') }}</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                            {{ __('messages.Note') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">
                                            {{ __('messages.Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200" id="capitalEntriesBody">
                                    @foreach ($capitalData['entries'] as $entry)
                                        <tr class="hover:bg-gray-50 capital-entry" data-date="{{ $entry->entry_date }}">
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($entry->entry_date)->format('M d, Y') }}
                                            </td>
                                            <td class="px-3 py-2 font-semibold text-green-600">
                                                ₪{{ number_format($entry->amount, 2) }}</td>
                                            <td class="px-3 py-2 text-gray-600 truncate max-w-xs">
                                                {{ $entry->note ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right">
                                                <button onclick="deleteCapital({{ $entry->id }})"
                                                    class="text-red-500 hover:text-red-700 text-xs">
                                                    {{ __('messages.Delete') }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-center text-gray-500 py-8">{{ __('messages.No capital entries found') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Capital Modal Functions
            function openCapitalModal() {
                document.getElementById('capitalModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeCapitalModal(event) {
                if (!event || event.target === document.getElementById('capitalModal')) {
                    document.getElementById('capitalModal').classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeCapitalModal();
                }
            });

            // Filter Capital Entries by Date
            function filterCapitalEntries() {
                const dateFilter = document.getElementById('capitalDateFilter').value;
                const entries = document.querySelectorAll('.capital-entry');

                entries.forEach(entry => {
                    const entryDate = entry.getAttribute('data-date');
                    if (!dateFilter || entryDate === dateFilter) {
                        entry.style.display = '';
                    } else {
                        entry.style.display = 'none';
                    }
                });
            }

            // Clear Capital Filter
            function clearCapitalFilter() {
                document.getElementById('capitalDateFilter').value = '';
                filterCapitalEntries();
            }

            // AJAX Capital Form Submission
            document.getElementById('capitalForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const form = this;
                const submitBtn = form.querySelector('button[type="submit"]');
                const messageDiv = document.getElementById('capitalMessage');

                submitBtn.disabled = true;
                submitBtn.textContent = '{{ __('messages.Adding...') }}';
                messageDiv.classList.add('hidden');

                const formData = new FormData(form);

                fetch('{{ route('capital.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            messageDiv.textContent = data.message;
                            messageDiv.className = 'mt-2 text-sm text-center text-green-600';
                            messageDiv.classList.remove('hidden');
                            form.reset();
                            form.querySelector('input[name="entry_date"]').value = '{{ date('Y-m-d') }}';
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            messageDiv.textContent = data.message || '{{ __('messages.Error adding capital') }}';
                            messageDiv.className = 'mt-2 text-sm text-center text-red-600';
                            messageDiv.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        messageDiv.textContent = '{{ __('messages.An error occurred') }}';
                        messageDiv.className = 'mt-2 text-sm text-center text-red-600';
                        messageDiv.classList.remove('hidden');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '{{ __('messages.Add') }}';
                    });
            });

            // Delete Capital Entry via AJAX
            function deleteCapital(id) {
                if (confirm('{{ __('messages.Are you sure?') }}')) {
                    fetch('/dashboard/capital/' + id, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            }
                        });
                }
            }
        </script>


    @endpush
</x-app-layout>
