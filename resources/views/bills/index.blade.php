@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    {{-- Bills Index Header --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                {{ __('messages.Bills Management') }}
            </h2>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                    {{ __('bills.Total Bills') }}: <span class="font-bold text-blue-600">{{ $bills->total() }}</span>
                </div>
                <!-- <a href="{{ route('bills.create') }}" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-2 rounded-lg shadow-md transition-all duration-200 transform hover:scale-105 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                {{ __('messages.New Bill') }}
            </a> -->
            </div>
        </div>
    </x-slot>

    <div class="py-8 mx-6">
        <!-- Filter and Stats Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <!-- Date Filter -->
            <div class="flex flex-wrap items-center justify-between mb-6">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center bg-gray-50 rounded-lg p-2">
                        <svg class="w-5 h-5 text-gray-500 mr-2" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <form method="GET" action="{{ route('bills.index') }}" class="flex items-center"
                            id="dateFilterForm">
                            <input type="date" name="date" value="{{ $selectedDate }}"
                                class="border-0 bg-transparent focus:ring-0 text-gray-700" id="dateFilterInput" />
                        </form>
                    </div>
                    @if ($selectedDate)
                        <a href="{{ route('bills.index') }}"
                            class="text-sm text-red-500 hover:text-red-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            {{ __('messages.Clear') }}
                        </a>
                    @endif
                </div>

                <!-- Search Box -->
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="{{ __('messages.Search...') }}"
                        class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        value="{{ request('search') }}">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <!-- Camera Scanner Icon -->
                    <button type="button" id="scan-barcode-btn"
                        class="absolute right-3 top-2.5 h-5 w-5 text-gray-400 hover:text-blue-500 transition-colors cursor-pointer"
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

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @if (auth()->user()->getVisibilitySetting('show_bills_total_sales'))
                    <div class="bg-gradient-to-br from-green-400 to-green-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm font-medium">{{ __('bills.Total Sales') }}</p>
                                <p class="text-2xl font-bold">₪{{ number_format($totalSales, 2) }}</p>
                            </div>
                            <div class="bg-green-500 bg-opacity-30 rounded-full p-3">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                    </div>
                @endif

                @if (auth()->user()->getVisibilitySetting('show_bills_total_profit'))
                    <div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm font-medium">{{ __('bills.Total Profit') }}</p>
                                <p class="text-2xl font-bold">₪{{ number_format($totalProfit, 2) }}</p>
                            </div>
                            <div class="bg-blue-500 bg-opacity-30 rounded-full p-3">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                @endif

                @if (auth()->user()->getVisibilitySetting('show_bills_count'))
                    <div class="bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-sm font-medium">{{ __('bills.Total Bills') }}</p>
                                <p class="text-2xl font-bold">{{ $bills->total() }}</p>
                                <p class="text-purple-100 text-xs">
                                    {{ $selectedDate ? __('bills.Today') : __('bills.All time') }}</p>
                            </div>
                            <div class="bg-purple-500 bg-opacity-30 rounded-full p-3">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                    <path fill-rule="evenodd"
                                        d="M4 5a2 2 0 012-2v1a2 2 0 002 2h6a2 2 0 002-2V3a2 2 0 012 2v6.5a1.5 1.5 0 01-1.5 1.5H9.207a1 1 0 00-.707.293L6 17.586 3.5 15.086A1 1 0 002.793 15H1.5A1.5 1.5 0 010 13.5V5a2 2 0 012-2h2zm6 9a1 1 0 100-2 1 1 0 000 2z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Bills Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('bills.Bills List') }}</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="billsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('bills.Bill Info') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('bills.Customer') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('bills.Created By') }}</th>
                            @if (auth()->user()->getVisibilitySetting('show_bill_total_value') ||
                                    auth()->user()->getVisibilitySetting('show_bill_profit_column'))
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('messages.Amount') }}</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('bills.Note') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('bills.Date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('bills.Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($bills as $bill)
                            @php
                                $costTotal = 0;
                                foreach ($bill->products as $product) {
                                    $costTotal += $product->pivot->quantity * $product->pivot->cost_price;
                                }
                                $profit = $bill->total_price - $costTotal;
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div
                                                class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                <svg class="h-6 w-6 text-gray-500" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                    </path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">#{{ $bill->id }}</div>
                                            <div class="text-sm text-gray-500">{{ $bill->products->count() }}
                                                {{ __('bills.items') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-800">
                                        {{ $bill->customer->name ?? __('bills.Walk-in') }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        {{ $bill->creator->name ?? '—' }}
                                    </div>
                                </td>
                                @if (auth()->user()->getVisibilitySetting('show_bill_total_value') ||
                                        auth()->user()->getVisibilitySetting('show_bill_profit_column'))
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if (auth()->user()->getVisibilitySetting('show_bill_total_value'))
                                            <div class="text-sm font-semibold text-gray-900">
                                                ₪{{ number_format($bill->total_price, 2) }}</div>
                                        @endif
                                        @if (auth()->user()->getVisibilitySetting('show_bill_profit_column'))
                                            <div class="text-sm text-gray-500">
                                                {{ __('bills.Profit') }}: <span
                                                    class="{{ $profit >= 0 ? 'text-green-600' : 'text-red-600' }}">₪{{ number_format($profit, 2) }}</span>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate" title="{{ $bill->note }}">
                                        {{ $bill->note ?: __('bills.No note') }}
                                    </div>
                                    @if (str_contains($bill->note, 'Damaged'))
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            {{ __('bills.Damaged') }}
                                        </span>
                                    @endif
                                    @if ($bill->is_returned)
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ __('messages.Returned') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ $bill->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $bill->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button type="button" onclick="viewBillDetails({{ $bill->id }})"
                                            class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-md hover:bg-purple-200 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            {{ __('messages.View') }}
                                        </button>
                                        @if (!$bill->is_returned)
                                            <a href="{{ route('bills.show', $bill->id) }}"
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200 transition-colors">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                                    </path>
                                                </svg>
                                                {{ __('bills.Edit') }}
                                            </a>
                                        @else
                                            <button type="button" disabled
                                                class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-400 text-xs font-medium rounded-md cursor-not-allowed"
                                                title="{{ __('messages.Returned bills cannot be edited') }}">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                                    </path>
                                                </svg>
                                                {{ __('bills.Edit') }}
                                            </button>
                                        @endif
                                        <form action="{{ route('bills.destroy', $bill->id) }}" method="POST"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                onclick="return confirm('{{ __('bills.Are you sure you want to delete this bill? This will restore product quantities.') }}')"
                                                class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-md hover:bg-red-200 transition-colors">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                    </path>
                                                </svg>
                                                {{ __('messages.Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                            </path>
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">
                                            {{ __('bills.No bills found') }}</h3>
                                        <p class="text-gray-500">
                                            {{ $selectedDate ? __('bills.No bills found for this date.') : __('bills.Start by creating your first bill.') }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($bills->hasPages())
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 overflow-x-auto scrollbar-thin"
                    id="pagination-links">
                    {{ $bills->appends(['date' => $selectedDate, 'search' => request('search')])->links('vendor.pagination.custom-light') }}
                </div>
            @endif
        </div>
    </div>

    <script>
        // Auto-submit date filter
        document.getElementById('dateFilterInput').addEventListener('change', function() {
            document.getElementById('dateFilterForm').submit();
        });

        // Debounced search functionality
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();

            searchTimeout = setTimeout(() => {
                performSearch(searchTerm);
            }, 300); // 300ms debounce
        });

        // Preserve search on pagination click
        document.getElementById('pagination-links')?.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            e.preventDefault();
            const searchTerm = document.getElementById('searchInput').value.trim();
            let url = link.href;

            if (searchTerm) {
                url = new URL(link.href);
                url.searchParams.set('search', searchTerm);
                const dateInput = document.getElementById('dateFilterInput');
                if (dateInput && dateInput.value) {
                    url.searchParams.set('date', dateInput.value);
                }
                url = url.toString();
            }

            window.location.href = url;
        });

        function performSearch(searchTerm) {
            const url = new URL(window.location);

            if (searchTerm) {
                url.searchParams.set('search', searchTerm);
            } else {
                url.searchParams.delete('search');
            }

            const dateInput = document.getElementById('dateFilterInput');
            if (dateInput && dateInput.value) {
                url.searchParams.set('date', dateInput.value);
            }

            fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    updateBillsTable(data.bills, data.pagination);
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }

        function updateBillsTable(bills, pagination) {
            const tbody = document.querySelector('#billsTable tbody');
            tbody.innerHTML = '';

            if (bills.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('bills.No bills found') }}</h3>
                                <p class="text-gray-500">No bills match your search.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            bills.forEach(bill => {
                const products = bill.products || [];
                const costTotal = products.reduce((sum, product) => {
                    return sum + (product.pivot.quantity * product.pivot.cost_price);
                }, 0);
                const profit = bill.total_price - costTotal;

                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 transition-colors duration-150';
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                    <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">#${bill.id}</div>
                                <div class="text-sm text-gray-500">${products.length} items</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">₪${parseFloat(bill.total_price).toFixed(2)}</div>
                        <div class="text-sm text-gray-500">
                            Profit: <span class="${profit >= 0 ? 'text-green-600' : 'text-red-600'}">₪${parseFloat(profit).toFixed(2)}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 max-w-xs truncate" title="${bill.note || ''}">
                            ${bill.note || 'No note'}
                        </div>
                        ${bill.note && bill.note.includes('Damaged') ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Damaged</span>' : ''}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div>${new Date(bill.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                        <div class="text-xs text-gray-400">${new Date(bill.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <button type="button"
                                onclick="viewBillDetails(${bill.id})"
                                class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-md hover:bg-purple-200 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ __('bills.View') }}
                            </button>
                            <a href="/bills/${bill.id}"
                               class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                                {{ __('bills.Edit') }}
                            </a>
                            <form action="/bills/${bill.id}" method="POST" class="inline">
                                <input type="hidden" name="_method" value="DELETE">
                                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
                                <button type="submit"
                                        onclick="return confirm('{{ __('bills.Are you sure you want to delete this bill? This will restore product quantities.') }}')"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-md hover:bg-red-200 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    {{ __('bills.Delete') }}
                                </button>
                            </form>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Update pagination
            updatePagination(pagination);
        }

        function updatePagination(pagination) {
            const paginationContainer = document.querySelector('.pagination-container');
            if (!paginationContainer) return;

            // Simple pagination update - you might need to implement full pagination links
            // For now, just show current page info
            paginationContainer.innerHTML =
                `Page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total bills)`;
        }

        // Success message auto-hide
        setTimeout(() => {
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

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

                if (typeof Html5Qrcode === 'undefined') {
                    showNotification('Barcode scanner unavailable', 'warning');
                    return;
                }
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
                            // Trigger search
                            const event = new Event('input');
                            inputElement.dispatchEvent(event);
                        }).catch(err => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                            const event = new Event('input');
                            inputElement.dispatchEvent(event);
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
                    initBarcodeScanner('searchInput');
                });
            }
        });
    </script>

    <!-- Bill Details Modal -->
    <div id="bill-details-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeBillModal()"></div>

            <!-- Modal Panel -->
            <div
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ __('messages.Bill Details') }}
                            </h3>
                            <div class="mt-4">
                                <!-- Bill Info Header -->
                                <div class="flex justify-between mb-4 pb-4 border-b">
                                    <div>
                                        <p class="text-sm text-gray-500">{{ __('messages.Bill ID') }}: <span
                                                id="bill-id" class="font-medium text-gray-900"></span></p>
                                        <p class="text-sm text-gray-500">{{ __('messages.Date') }}: <span
                                                id="bill-date" class="font-medium text-gray-900"></span></p>
                                        <p class="text-sm text-gray-500">{{ __('bills.Customer') }}: <span
                                                id="bill-customer" class="font-medium text-gray-900"></span></p>
                                        <p class="text-sm text-gray-500">{{ __('bills.Created By') }}: <span
                                                id="bill-creator" class="font-medium text-gray-900"></span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500">{{ __('messages.Total') }}:</p>
                                        <p class="text-xl font-bold text-green-600" id="bill-total"></p>
                                    </div>
                                </div>

                                <!-- Products Table -->
                                <div class="max-h-64 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Product') }}</th>
                                                <th
                                                    class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Quantity') }}</th>
                                                <th
                                                    class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Price') }}</th>
                                                <th
                                                    class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Discount') }}</th>
                                                <th
                                                    class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Subtotal') }}</th>
                                                <th
                                                    class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                    IMEI</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bill-products-body" class="divide-y divide-gray-200">
                                            <!-- Products will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Note -->
                                <div class="mt-4 pt-4 border-t" id="bill-note-container">
                                    <p class="text-sm text-gray-500">{{ __('messages.Note') }}: <span id="bill-note"
                                            class="text-gray-900"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeBillModal()"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        {{ __('messages.Close') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View Bill Details Function
        function viewBillDetails(billId) {
            // Find the bill in the current data
            const bills = @json($bills->items());
            const bill = bills.find(b => b.id === billId);

            if (!bill) {
                // If not found in current page, fetch from server
                fetch(`/bills/${billId}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        showBillModal(data.bill);
                    })
                    .catch(error => {
                        console.error('Error fetching bill:', error);
                        alert('Error loading bill details');
                    });
            } else {
                showBillModal(bill);
            }
        }

        function showBillModal(bill) {
            // Set basic info
            document.getElementById('bill-id').textContent = '#' + bill.id;
            document.getElementById('bill-date').textContent = new Date(bill.created_at).toLocaleDateString();
            document.getElementById('bill-total').textContent = '₪' + parseFloat(bill.total_price || 0).toFixed(2);
            document.getElementById('bill-note').textContent = bill.note || '-';
            document.getElementById('bill-customer').textContent = bill.customer?.name ?? '{{ __('bills.Walk-in') }}';
            document.getElementById('bill-creator').textContent = bill.creator?.name ?? '—';

            // Build products table
            const tbody = document.getElementById('bill-products-body');
            tbody.innerHTML = '';

            if (bill.products && bill.products.length > 0) {
                bill.products.forEach(product => {
                    const quantity = parseFloat(product.pivot?.quantity || product.pivot?.qty || 0);
                    const unitPrice = parseFloat(product.pivot?.selling_price || 0);
                    const discount = parseFloat(product.pivot?.discount || 0);
                    const subtotal = (quantity * unitPrice) - discount;

                    // Parse IMEIs
                    let imeis = product.pivot?.imeis;
                    let imeiHtml = '';
                    if (imeis) {
                        try {
                            const imeiArr = typeof imeis === 'string' ? JSON.parse(imeis) : imeis;
                            if (imeiArr && imeiArr.length > 0) {
                                imeiHtml = imeiArr.map(i =>
                                    `<span class="inline-block bg-indigo-50 text-indigo-700 text-xs px-1.5 py-0.5 rounded font-mono">${i}</span>`
                                    ).join(' ');
                            }
                        } catch (e) {}
                    }

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-3 py-2">
                            <div class="text-sm font-medium text-gray-900">${product.name}</div>
                            <div class="text-xs text-gray-500">${product.barcode || ''}</div>
                        </td>
                        <td class="px-3 py-2 text-right text-sm text-gray-900">${quantity}</td>
                        <td class="px-3 py-2 text-right text-sm text-gray-900">₪${unitPrice.toFixed(2)}</td>
                        <td class="px-3 py-2 text-right text-sm text-gray-900">₪${discount.toFixed(2)}</td>
                        <td class="px-3 py-2 text-right text-sm font-medium text-gray-900">₪${subtotal.toFixed(2)}</td>
                        <td class="px-3 py-2 text-sm">${imeiHtml || '<span class="text-gray-400">—</span>'}</td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">No products found</td></tr>';
            }

            // Show modal
            document.getElementById('bill-details-modal').classList.remove('hidden');
        }

        function closeBillModal() {
            document.getElementById('bill-details-modal').classList.add('hidden');
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBillModal();
            }
        });
    </script>
</x-app-layout>
