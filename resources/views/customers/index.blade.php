<x-app-layout>
    {{-- Customer Index Header --}}
<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
            <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
            </svg>
            {{ __('Customer Management') }}
        </h2>
        <div class="flex items-center space-x-4">
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                Total Customers: <span class="font-bold text-blue-600">{{ $customers->total() }}</span>
            </div>
            <a href="{{ route('customers.create') }}"
                class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-2 rounded-lg shadow-md transition-all duration-200 transform hover:scale-105 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Customer
            </a>
        </div>
    </div>
</x-slot>

    <div class="py-8 mx-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Customers</p>
                        <p class="text-2xl font-bold">{{ $customers->total() ?? 0 }}</p>
                    </div>
                    <div class="bg-blue-500 bg-opacity-30 rounded-full p-3">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-red-400 to-red-600 rounded-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">Total Debt</p>
                        <p class="text-2xl font-bold">
                            ${{ number_format($customers->where('balance', '<', 0)->sum('balance') * -1, 2) }}
                        </p>
                    </div>
                    <div class="bg-red-500 bg-opacity-30 rounded-full p-3">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-400 to-green-600 rounded-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Active Today</p>
                        <p class="text-2xl font-bold">{{ $customers->where('updated_at', '>=', now()->startOfDay())->count() }}</p>
                    </div>
                    <div class="bg-green-500 bg-opacity-30 rounded-full p-3">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Search Input -->
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" id="customer-search" 
                           placeholder="Search by name, phone, or ID..." 
                           class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                           value="{{ request('search') }}">
                </div>

                <!-- Filter Buttons -->
                <div class="flex space-x-2">
                    <button id="filter-all" class="filter-btn active px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        All Customers
                    </button>
                    <button id="filter-debt" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        With Debt
                    </button>
                    <button id="filter-credit" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        With Credit
                    </button>
                </div>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Customers List</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customers-table-body" class="bg-white divide-y divide-gray-200">
                        @forelse ($customers as $customer)
                            <tr class="hover:bg-gray-50 transition-colors duration-150" data-balance="{{ $customer->balance }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold">
                                                {{ strtoupper(substr($customer->name, 0, 1)) }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $customer->name }}</div>
                                            <div class="text-sm text-gray-500">ID: #{{ $customer->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        @if($customer->phone)
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                {{ $customer->phone }}
                                            </div>
                                        @else
                                            <span class="text-gray-400 italic">No phone</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($customer->balance < 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            ${{ number_format(abs($customer->balance), 2) }} Debt
                                        </span>
                                    @elseif($customer->balance > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            ${{ number_format($customer->balance, 2) }} Credit
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            $0.00 Balanced
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ $customer->updated_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $customer->updated_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('customers.edit', $customer) }}" 
                                           class="inline-flex items-center px-3 py-1.5 bg-yellow-100 text-yellow-700 text-xs font-medium rounded-md hover:bg-yellow-200 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </a>
                                        <a href="{{ route('customers.payments', $customer) }}" 
                                           class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-200 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            Payments
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1">No customers found</h3>
                                        <p class="text-gray-500">Start by adding your first customer.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($customers->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50" id="pagination-links">
                    {{ $customers->links('vendor.pagination.custom-light') }}
                </div>
            @endif
        </div>
    </div>

    <style>
        .filter-btn {
            @apply bg-gray-100 text-gray-600 hover:bg-gray-200;
        }
        .filter-btn.active {
            @apply bg-blue-500 text-white hover:bg-blue-600;
        }
    </style>

    <script>
        const typingDelay = 500;
        let typingTimer;
        const searchInput = document.getElementById('customer-search');
        const tableBody = document.getElementById('customers-table-body');
        const paginationLinks = document.getElementById('pagination-links');

        // Load customers via AJAX
        function loadCustomers(url) {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const newTbody = doc.querySelector('#customers-table-body');
                    const newPagination = doc.querySelector('#pagination-links');

                    if (newTbody) tableBody.innerHTML = newTbody.innerHTML;
                    if (newPagination && paginationLinks) paginationLinks.innerHTML = newPagination.innerHTML;

                    attachPaginationLinks();
                });
        }

        // Debounced search
        searchInput.addEventListener('input', () => {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const query = searchInput.value.trim();
                const url = new URL('{{ route('customers.index') }}', window.location.origin);
                if (query.length > 0) {
                    url.searchParams.set('search', query);
                }
                loadCustomers(url.toString());
            }, typingDelay);
        });

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Filter table rows
                const rows = document.querySelectorAll('#customers-table-body tr[data-balance]');
                const filter = this.id;

                rows.forEach(row => {
                    const balance = parseFloat(row.dataset.balance);
                    let show = true;

                    switch(filter) {
                        case 'filter-debt':
                            show = balance < 0;
                            break;
                        case 'filter-credit':
                            show = balance > 0;
                            break;
                        case 'filter-all':
                        default:
                            show = true;
                            break;
                    }

                    row.style.display = show ? '' : 'none';
                });
            });
        });

        // AJAX pagination
        function attachPaginationLinks() {
            document.querySelectorAll('#pagination-links a').forEach(link => {
                link.onclick = function(e) {
                    e.preventDefault();
                    loadCustomers(this.href);
                }
            });
        }

        // Initial bind
        attachPaginationLinks();

        // Success message auto-hide
        setTimeout(() => {
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</x-app-layout>