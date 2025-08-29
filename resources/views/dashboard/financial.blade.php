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
                <svg class="w-6 h-6 lg:w-8 lg:h-8 mr-2 lg:mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 00-2 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                {{ __('messages.Financial Dashboard') }}
            </h2>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                <div class="text-xs sm:text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
                    {{ __('messages.Period:') }} <span class="font-bold text-blue-600">{{ \Carbon\Carbon::parse($startDate)->format('M d') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                </div>
                <div class="text-xs sm:text-sm text-gray-600 bg-blue-100 px-3 py-2 rounded-full">
                    {{ __('messages.Net Income:') }} <span class="font-bold {{ $summaryData['netIncome'] >= 0 ? 'text-green-600' : 'text-red-600' }}">₪{{ number_format($summaryData['netIncome'], 0) }}</span>
                </div>
            </div>
        </div>
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mt-4">
            <h2 class="font-bold text-xl lg:text-2xl text-gray-800 leading-tight flex items-center">
                <!-- existing header content -->
            </h2>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                <!-- existing period and net income divs -->
                
                <!-- Add this new export button -->
                <a href="{{ route('dashboard.export-data') }}" 
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('messages.Export Data') }}
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Full Width Content Area -->
    <div class="w-full min-h-screen bg-gray-50">
        <div class="w-full px-3 sm:px-4 lg:px-6 py-4 lg:py-6">
            
            <!-- Date Filter Widget - Responsive -->
            <div class="bg-white p-4 shadow-md rounded-lg mb-4 lg:mb-6 border border-gray-200 w-full">
                <form method="GET" action="{{ route('dashboard.financial') }}" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 sm:gap-4">
                    <div class="w-full sm:w-auto">
                        <label class="block text-sm font-medium mb-1 text-gray-700">📅 {{ __('messages.Start Date') }}</label>
                        <input type="date" name="start_date" value="{{ $startDate }}"
                               class="w-full sm:w-auto border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div class="w-full sm:w-auto">
                        <label class="block text-sm font-medium mb-1 text-gray-700">📅 {{ __('messages.End Date') }}</label>
                        <input type="date" name="end_date" value="{{ $endDate }}"
                               class="w-full sm:w-auto border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 text-sm font-medium">
                        📊 {{ __('messages.Apply Filter') }}
                    </button>
                </form>
            </div>

            <!-- Summary Cards - Responsive Grid -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                    <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">💰 {{ __('messages.Revenue') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">₪{{ number_format($summaryData['totalRevenue'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-green-500">💰</div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-blue-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">📈 {{ __('messages.Profit') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">₪{{ number_format($summaryData['totalProfit'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-blue-500">📈</div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-red-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">💸 {{ __('messages.Expenses') }}</p>
                                <p class="text-xl lg:text-2xl font-bold text-gray-900">₪{{ number_format($summaryData['totalExpenses'] + $summaryData['totalEmployeePayments'], 0) }}</p>
                            </div>
                            <div class="text-2xl lg:text-3xl text-red-500">💸</div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-purple-500 hover:shadow-lg transition duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">💎 {{ __('messages.Net Income') }}</p>
                                <p class="text-xl lg:text-2xl font-bold {{ $summaryData['netIncome'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    ₪{{ number_format($summaryData['netIncome'], 0) }}
                                </p>
                            </div>
                            <div class="text-2xl lg:text-3xl {{ $summaryData['netIncome'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $summaryData['netIncome'] >= 0 ? '✅' : '⚠️' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Chart + Growth Stats Row - Responsive -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-6">
                    <!-- Revenue Chart - Full Width on Mobile -->
                    <div class="xl:col-span-2 bg-white p-4 lg:p-6 rounded-lg shadow-md border border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                            <h3 class="text-base lg:text-lg font-semibold text-gray-800">📈 {{ __('messages.Daily Revenue Trend') }}</h3>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                ₪{{ number_format($revenueData['total'], 0) }} {{ __('messages.Total') }}
                            </span>
                        </div>
                        <div class="h-64 lg:h-80">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Growth Stats - Responsive Stack -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-1 gap-3 lg:gap-4">
                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">💰 {{ __('messages.Revenue Growth') }}</h4>
                                <span class="text-lg">{{ $growthData['revenue']['growth'] >= 0 ? '📈' : '📉' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">₪{{ number_format($growthData['revenue']['current'], 0) }}</p>
                            <p class="text-sm {{ $growthData['revenue']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['revenue']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['revenue']['growth'], 1) }}% {{ __('messages.vs previous') }}
                            </p>
                        </div>

                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">💎 {{ __('messages.Profit Growth') }}</h4>
                                <span class="text-lg">{{ $growthData['profit']['growth'] >= 0 ? '💰' : '💸' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">₪{{ number_format($growthData['profit']['current'], 0) }}</p>
                            <p class="text-sm {{ $growthData['profit']['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['profit']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['profit']['growth'], 1) }}% {{ __('messages.vs previous') }}
                            </p>
                        </div>

                        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-800">🎯 {{ __('messages.Expense Control') }}</h4>
                                <span class="text-lg">{{ $growthData['expenses']['growth'] <= 0 ? '👍' : '⚠️' }}</span>
                            </div>
                            <p class="text-lg lg:text-xl font-bold text-gray-900">₪{{ number_format($growthData['expenses']['current'], 0) }}</p>
                            <p class="text-sm {{ $growthData['expenses']['growth'] <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthData['expenses']['growth'] >= 0 ? '+' : '' }}{{ number_format($growthData['expenses']['growth'], 1) }}% {{ __('messages.vs previous') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Three Medium Charts Row - Responsive -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">💎 {{ __('messages.Daily Profit') }}</h3>
                            <span class="text-sm text-gray-600">₪{{ number_format($profitData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="profitChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">💸 {{ __('messages.Expenses') }}</h3>
                            <span class="text-sm text-gray-600">₪{{ number_format($expenseData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex flex-col sm:flex-row lg:flex-col items-start sm:items-center lg:items-start justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">🤝 {{ __('messages.Customer Payments') }}</h3>
                            <div class="text-xs text-gray-600 mt-1 sm:mt-0 lg:mt-1">
                                ↗️ ₪{{ number_format($customerPaymentData['totalReceived'], 0) }} |
                                ↘️ ₪{{ number_format($customerPaymentData['totalPaid'], 0) }}
                            </div>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="customerPaymentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mixed Layout Row - Responsive -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-6">
                    <!-- Quick Stats -->
                    <div class="lg:col-span-1 xl:col-span-1 bg-gradient-to-br from-gray-800 to-gray-900 p-4 lg:p-5 rounded-lg shadow-md text-white">
                        <h3 class="text-sm lg:text-md font-semibold mb-4">📊 {{ __('messages.Quick Stats') }}</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-300">💹 {{ __('messages.Profit Margin') }}</span>
                                <span class="font-bold text-white">
                                    {{ $summaryData['totalRevenue'] > 0 ? number_format(($summaryData['totalProfit'] / $summaryData['totalRevenue']) * 100, 1) : 0 }}%
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-300">💚 {{ __('messages.Customer Credit') }}</span>
                                <span class="font-bold text-green-400">₪{{ number_format($customerBalanceData['totalOwing'], 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-300">💔 {{ __('messages.Our Debt') }}</span>
                                <span class="font-bold text-red-400">₪{{ number_format($customerBalanceData['totalOwed'], 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-300">⚠️ {{ __('messages.Damage Loss') }}</span>
                                <span class="font-bold text-orange-400">₪{{ number_format($damagedData['total'], 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Payments -->
                    <div class="lg:col-span-1 xl:col-span-2 bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">👥 {{ __('messages.Employee Payments') }}</h3>
                            <span class="text-sm text-gray-600">₪{{ number_format($employeePaymentData['total'], 0) }}</span>
                        </div>
                        <div class="h-48 lg:h-56">
                            <canvas id="employeePaymentChart"></canvas>
                        </div>
                    </div>

                    <!-- Damaged Products -->
                    <div class="lg:col-span-2 xl:col-span-1 bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">⚠️ {{ __('messages.Damaged Items') }}</h3>
                            <span class="text-lg">📦</span>
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <span class="block text-sm text-gray-600">{{ __('messages.Items:') }}</span>
                                <span class="block font-semibold text-gray-900 text-lg">{{ $damagedData['count'] }}</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-sm text-gray-600">{{ __('messages.Value:') }}</span>
                                <span class="block font-semibold text-red-600 text-lg">₪{{ number_format($damagedData['total'], 0) }}</span>
                            </div>
                        </div>
                        <div class="h-32 lg:h-40">
                            <canvas id="damagedChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Balance Row - Responsive -->
            <div class="w-full mb-4 lg:mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">💚 {{ __('messages.Customers Owing Us') }}</h3>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm font-medium">
                                ₪{{ number_format($customerBalanceData['totalOwing'], 0) }}
                            </span>
                        </div>
                        <div class="h-56 lg:h-64">
                            <canvas id="customerOwingChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white p-4 lg:p-5 rounded-lg shadow-md border border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                            <h3 class="text-sm lg:text-md font-semibold text-gray-800">💔 {{ __('messages.Customers We Owe') }}</h3>
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm font-medium">
                                ₪{{ number_format($customerBalanceData['totalOwed'], 0) }}
                            </span>
                        </div>
                        <div class="h-56 lg:h-64">
                            <canvas id="customerOwedChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products Table - Full Width Responsive -->
            <div class="w-full bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
                <div class="px-4 lg:px-5 py-3 border-b border-gray-200 bg-gray-50">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <h3 class="text-sm lg:text-md font-semibold text-gray-800">🏆 {{ __('messages.Top Performing Products') }}</h3>
                        <span class="text-sm text-gray-600">{{ __('messages.Performance Ranking') }}</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{__('messages.Rank')}}</th>
                                <th class="px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{__('messages.Product')}}</th>
                                <th class="px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{__('messages.Qty Sold')}}</th>
                                <th class="px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{__('messages.Revenue')}}</th>
                                <th class="px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{__('messages.Profit')}}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($topProducts as $index => $product)
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-3 lg:px-4 py-3 whitespace-nowrap">
                                    @if($index == 0)
                                        <span class="text-lg">🥇</span>
                                    @elseif($index == 1)
                                        <span class="text-lg">🥈</span>
                                    @elseif($index == 2)
                                        <span class="text-lg">🥉</span>
                                    @else
                                        <span class="text-sm text-gray-600 font-medium">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="px-3 lg:px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 truncate max-w-xs">{{ $product->name }}</div>
                                </td>
                                <td class="px-3 lg:px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ number_format($product->total_quantity) }}
                                    </span>
                                </td>
                                <td class="px-3 lg:px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                    ₪{{ number_format($product->total_revenue, 0) }}
                                </td>
                                <td class="px-3 lg:px-4 py-3 whitespace-nowrap text-sm text-green-600 font-semibold">
                                    ₪{{ number_format($product->total_profit, 0) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

   @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        window.addEventListener('load', function() {
            console.log('=== CREATING ALL CHARTS ===');

            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js failed to load!');
                return;
            }

            console.log('Chart.js loaded successfully, version:', Chart.version);

            // Get all data from PHP
            const revenueData = {
                labels: {!! json_encode($revenueData['labels'] ?? []) !!},
                data: {!! json_encode($revenueData['data'] ?? []) !!}
            };

            const profitData = {
                labels: {!! json_encode($profitData['labels'] ?? []) !!},
                data: {!! json_encode($profitData['data'] ?? []) !!}
            };

            const expenseData = {
                labels: {!! json_encode($expenseData['categories']['labels'] ?? []) !!},
                data: {!! json_encode($expenseData['categories']['data'] ?? []) !!}
            };

            const customerPaymentData = {
                labels: {!! json_encode($customerPaymentData['labels'] ?? []) !!},
                received: {!! json_encode($customerPaymentData['received'] ?? []) !!},
                paid: {!! json_encode($customerPaymentData['paid'] ?? []) !!}
            };

            const employeePaymentData = {
                labels: {!! json_encode($employeePaymentData['byEmployee']['labels'] ?? []) !!},
                data: {!! json_encode($employeePaymentData['byEmployee']['data'] ?? []) !!}
            };

            const damagedData = {
                labels: {!! json_encode($damagedData['products']['labels'] ?? []) !!},
                data: {!! json_encode($damagedData['products']['data'] ?? []) !!}
            };

            const customerOwingData = {
                labels: {!! json_encode($customerBalanceData['topOwing']['labels'] ?? []) !!},
                data: {!! json_encode($customerBalanceData['topOwing']['data'] ?? []) !!}
            };

            const customerOwedData = {
                labels: {!! json_encode($customerBalanceData['topOwed']['labels'] ?? []) !!},
                data: {!! json_encode($customerBalanceData['topOwed']['data'] ?? []) !!}
            };

            // Helper function to destroy existing chart
            function destroyExistingChart(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (canvas) {
                    const existingChart = Chart.getChart(canvas);
                    if (existingChart) {
                        existingChart.destroy();
                    }
                }
                return canvas;
            }

            // 1. REVENUE CHART (Line Chart)
            const revenueCanvas = destroyExistingChart('revenueChart');
            if (revenueCanvas) {
                new Chart(revenueCanvas, {
                    type: 'line',
                    data: {
                        labels: revenueData.labels.length > 0 ? revenueData.labels : ['messages.No Data'],
                        datasets: [{
                            label: '{{__('messages.Revenue')}}',
                            data: revenueData.data.length > 0 ? revenueData.data : [0],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 11 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 11 } }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: { usePointStyle: true, padding: 15, color: '#374151', font: { size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return 'Revenue: ₪' + context.parsed.y.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Revenue chart created');
            }

            // 2. PROFIT CHART (Line Chart)
            const profitCanvas = destroyExistingChart('profitChart');
            if (profitCanvas) {
                new Chart(profitCanvas, {
                    type: 'line',
                    data: {
                        labels: profitData.labels.length > 0 ? profitData.labels : ['{{__('messages.No Data')}}'],
                        datasets: [{
                            label: '{{__('messages.Profit')}}',
                            data: profitData.data.length > 0 ? profitData.data : [0],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 10 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 10 } }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: { usePointStyle: true, padding: 15, color: '#374151', font: { size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return 'Profit: ₪' + context.parsed.y.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Profit chart created');
            }

            // 3. EXPENSE CHART (Doughnut Chart)
            const expenseCanvas = destroyExistingChart('expenseChart');
            if (expenseCanvas && expenseData.labels.length > 0) {
                new Chart(expenseCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: expenseData.labels,
                        datasets: [{
                            data: expenseData.data,
                            backgroundColor: [
                                '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4',
                                '#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#84cc16'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 12, usePointStyle: true, color: '#374151', font: { size: 10 } }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return context.label + ': ₪' + context.parsed.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Expense chart created');
            }

            // 4. CUSTOMER PAYMENT CHART (Bar Chart)
            const customerPaymentCanvas = destroyExistingChart('customerPaymentChart');
            if (customerPaymentCanvas) {
                new Chart(customerPaymentCanvas, {
                    type: 'bar',
                    data: {
                        labels: customerPaymentData.labels.length > 0 ? customerPaymentData.labels : ['{{__('messages.No Data')}}'],
                        datasets: [{
                            label: '{{__('messages.Received')}}',
                            data: customerPaymentData.received.length > 0 ? customerPaymentData.received : [0],
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderColor: '#22c55e',
                            borderWidth: 1,
                            borderRadius: 4
                        }, {
                            label: '{{__('messages.Paid')}}',
                            data: customerPaymentData.paid.length > 0 ? customerPaymentData.paid : [0],
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: '#ef4444',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 10 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 10 } }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: { usePointStyle: true, padding: 15, color: '#374151', font: { size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return context.dataset.label + ': ₪' + context.parsed.y.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Customer Payment chart created');
            }

            // 5. EMPLOYEE PAYMENT CHART (Bar Chart)
            const employeePaymentCanvas = destroyExistingChart('employeePaymentChart');
            if (employeePaymentCanvas) {
                new Chart(employeePaymentCanvas, {
                    type: 'bar',
                    data: {
                        labels: employeePaymentData.labels.length > 0 ? employeePaymentData.labels : ['No Data'],
                        datasets: [{
                            label: 'Payment Amount',
                            data: employeePaymentData.data.length > 0 ? employeePaymentData.data : [0],
                            backgroundColor: [
                                'rgba(99, 102, 241, 0.8)', 'rgba(236, 72, 153, 0.8)', 
                                'rgba(34, 197, 94, 0.8)', 'rgba(251, 191, 36, 0.8)',
                                'rgba(239, 68, 68, 0.8)', 'rgba(6, 182, 212, 0.8)'
                            ],
                            borderColor: [
                                '#6366f1', '#ec4899', '#22c55e', '#fbbf24', '#ef4444', '#06b6d4'
                            ],
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 10 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 10 } }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return 'Payment: ₪' + context.parsed.y.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Employee Payment chart created');
            }

            // 6. DAMAGED PRODUCTS CHART (Horizontal Bar Chart)
            const damagedCanvas = destroyExistingChart('damagedChart');
            if (damagedCanvas && damagedData.labels.length > 0) {
                new Chart(damagedCanvas, {
                    type: 'bar',
                    data: {
                        labels: damagedData.labels,
                        datasets: [{
                            label: 'Damage Value',
                            data: damagedData.data,
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: '#ef4444',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 9 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            y: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 9 } }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return 'Damage: ₪' + context.parsed.x.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Damaged Products chart created');
            }

            // 7. CUSTOMER OWING CHART (Bar Chart)
            const customerOwingCanvas = destroyExistingChart('customerOwingChart');
            if (customerOwingCanvas) {
                new Chart(customerOwingCanvas, {
                    type: 'bar',
                    data: {
                        labels: customerOwingData.labels.length > 0 ? customerOwingData.labels : ['No Data'],
                        datasets: [{
                            label: 'Amount Owing',
                            data: customerOwingData.data.length > 0 ? customerOwingData.data : [0],
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderColor: '#22c55e',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 11 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 11 } }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return 'Owes: ₪' + context.parsed.y.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Customer Owing chart created');
            }

            // 8. CUSTOMER OWED CHART (Bar Chart)
            const customerOwedCanvas = destroyExistingChart('customerOwedChart');
            if (customerOwedCanvas) {
                new Chart(customerOwedCanvas, {
                    type: 'bar',
                    data: {
                        labels: customerOwedData.labels.length > 0 ? customerOwedData.labels : ['No Data'],
                        datasets: [{
                            label: 'Amount We Owe',
                            data: customerOwedData.data.length > 0 ? customerOwedData.data : [0],
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: '#ef4444',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(156, 163, 175, 0.2)' },
                                ticks: {
                                    color: '#6b7280',
                                    font: { size: 11 },
                                    callback: function(value) { return '₪' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 11 } }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) { return 'We owe: ₪' + context.parsed.y.toLocaleString(); }
                                }
                            }
                        }
                    }
                });
                console.log('✅ Customer Owed chart created');
            }

            console.log('🎉 ALL CHARTS CREATED SUCCESSFULLY!');
        });
    </script>
    @endpush
</x-app-layout>