@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    @endphp
<x-app-layout>
    {{-- Employee Payment History Header --}}
<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
            <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
            {{ __('employees.Payment History') }}
        </h2>
        <div class="flex items-center space-x-4">
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                {{ __('employees.Employee') }}: <span class="font-bold text-green-600">{{ $employee->name }}</span>
            </div>
            <div class="text-sm text-gray-600 bg-blue-100 px-4 py-2 rounded-full">
                {{ __('employees.Job Title') }}: <span class="font-bold text-blue-600">{{ $employee->job_title }}</span>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('shopowner.employees.edit', $employee->id) }}" 
                   class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    {{ __('employees.Edit Employee') }}
                </a>
                <a href="{{ route('shopowner.employees.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('employees.Back to Employees') }}
                </a>
            </div>
        </div>
    </div>
</x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Employee Info Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow-lg rounded-xl p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                                <span class="text-lg font-bold text-white">{{ substr($employee->name, 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">${{ number_format($employee->monthly_salary, 2) }}</p>
                            <p class="text-sm text-gray-600">{{ __('employees.Monthly Salary') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-lg rounded-xl p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">${{ number_format($employee->paidThisMonth(), 2) }}</p>
                            <p class="text-sm text-gray-600">{{ __('employees.Paid This Month') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-lg rounded-xl p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">${{ number_format($employee->remainingThisMonth(), 2) }}</p>
                            <p class="text-sm text-gray-600">{{ __('employees.Remaining to Pay') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-lg rounded-xl p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            @php
                                $percentage = $employee->monthly_salary > 0 ? ($employee->paidThisMonth() / $employee->monthly_salary) * 100 : 0;
                            @endphp
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($percentage, 1) }}%</p>
                            <p class="text-sm text-gray-600">{{ __('employees.Payment Progress') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Payment Form -->
                <div class="lg:col-span-1">
                    <div class="bg-white shadow-xl rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-500 to-emerald-600">
                            <h3 class="text-lg font-semibold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                {{ __('employees.Record New Payment') }}
                            </h3>
                        </div>
                        
                        <div class="p-6">
                            <form action="{{ route('shopowner.employees.storePayment', $employee->id) }}" method="POST" class="space-y-6">
                                @csrf
                                <div>
                                    <label for="amount" class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('employees.Payment Amount') }}
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 text-lg">$</span>
                                        </div>
                                        <input type="number" step="0.01" name="amount" id="amount" required 
                                               class="block w-full pl-8 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-gray-900"
                                               placeholder="{{ __('messages.Enter amount') }}">
                                    </div>
                                    @if($employee->remainingThisMonth() > 0)
                                        <p class="mt-1 text-sm text-gray-600">
                                            {{ __('employees.Suggested') }}: ${{ number_format($employee->remainingThisMonth(), 2) }}
                                        </p>
                                    @endif
                                    @error('amount')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label for="payment_date" class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('employees.Payment Date') }}
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <input type="date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" required 
                                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-gray-900">
                                    </div>
                                    @error('payment_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <button type="submit" 
                                        class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:from-green-600 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transform hover:scale-105 transition-all duration-200 shadow-lg">
                                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    {{ __('employees.Record Payment') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Payments History -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-xl rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('employees.Payment History') }}</h3>
                                
                                <!-- Date Filter -->
                                <form id="filter-form" class="flex space-x-3">
                                    <div>
                                        <input type="date" name="from" value="{{ request('from') }}" 
                                               class="text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="{{ __('employees.From') }}">
                                    </div>
                                    <div>
                                        <input type="date" name="to" value="{{ request('to') }}" 
                                               class="text-sm border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="{{ __('employees.To') }}">
                                    </div>
                                    <button type="submit" 
                                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"/>
                                        </svg>
                                        {{ __('messages.Filter') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Payments Container -->
                        <div id="payments-container">
                            @include('shopowner.employees.partials.payments_table', ['payments' => $payments])
                        </div>

                        @if ($payments->hasMorePages())
                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-center">
                                <button id="load-more" 
                                        class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    {{ __('employees.Load More Payments') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced AJAX Script with Loading States -->
    <script>
        let page = 1;
        let isLoading = false;

        // Date filter with loading state
        document.getElementById('filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (isLoading) return;
            
            page = 1;
            showLoadingState();
            fetchPayments(true);
        });

        // Load More with loading state
        document.getElementById('load-more')?.addEventListener('click', function() {
            if (isLoading) return;
            
            page++;
            this.disabled = true;
            this.innerHTML = `
                <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading...
            `;
            fetchPayments();
        });

        function showLoadingState() {
            const container = document.getElementById('payments-container');
            container.innerHTML = `
                <div class="flex items-center justify-center py-12">
                    <div class="flex flex-col items-center">
                        <svg class="animate-spin w-8 h-8 text-blue-600 mb-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-600">{{ __('employees.Loading payments...') }}</p>
                    </div>
                </div>
            `;
        }

        function fetchPayments(reset = false) {
            isLoading = true;
            let formData = new FormData(document.getElementById('filter-form'));
            let params = new URLSearchParams(formData);
            params.append('page', page);

            fetch("{{ route('shopowner.employees.payments', $employee->id) }}?" + params.toString(), {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                const loadMoreBtn = document.getElementById('load-more');
                
                if (reset) {
                    document.getElementById('payments-container').innerHTML = html;
                } else {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newRows = doc.querySelector('tbody');
                    
                    if (newRows) {
                        document.querySelector('#payments-container tbody').insertAdjacentHTML('beforeend', newRows.innerHTML);
                    }
                    
                    // Re-enable load more button
                    if (loadMoreBtn) {
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.innerHTML = `
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Load More Payments
                        `;
                    }
                }
                
                isLoading = false;
            })
            .catch(error => {
                console.error('Error fetching payments:', error);
                
                const container = document.getElementById('payments-container');
                if (reset) {
                    container.innerHTML = `
                        <div class="flex items-center justify-center py-12">
                            <div class="flex flex-col items-center text-center">
                                <svg class="w-12 h-12 text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L5.232 6.268c-.77.833-.207 2.5 1.732 2.5z"/>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('employees.Error Loading Payments') }}</h3>
                                <p class="text-gray-600 mb-4">{{ __('employees.Unable to load payment history. Please try again.') }}</p>
                                <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                    {{ __('employees.Retry') }}
                                </button>
                            </div>
                        </div>
                    `;
                }
                
                const loadMoreBtn = document.getElementById('load-more');
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.innerHTML = `
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Load More Payments
                    `;
                }
                
                isLoading = false;
            });
        }

        // Auto-fill remaining amount when clicking on payment amount input
        document.getElementById('amount').addEventListener('focus', function() {
            const remainingAmount = {{ $employee->remainingThisMonth() }};
            if (remainingAmount > 0 && this.value === '') {
                this.value = remainingAmount.toFixed(2);
            }
        });

        // Form validation
        document.querySelector('form[action*="storePayment"]').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const maxAmount = {{ $employee->monthly_salary }};
            
            if (amount <= 0) {
                e.preventDefault();
                alert('{{ __('employees.Payment amount must be greater than 0') }}');
                return;
            }
            
            if (amount > maxAmount) {
                if (!confirm(`{{ __('employees.Payment amount exceeds monthly salary. Are you sure you want to continue?') }}`)) {
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</x-app-layout>