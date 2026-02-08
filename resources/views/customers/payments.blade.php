@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    {{-- Customer Payment History Header --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
                {{ __('messages.Payment History') }}
            </h2>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                    {{ __('messages.Customer') }}: <span class="font-bold text-green-600">{{ $customer->name }}</span>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('customers.index') }}"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        {{ __('messages.Back to Customers') }}
                    </a>
                    <a href="{{ route('customers.edit', $customer) }}"
                        class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                            </path>
                        </svg>
                        {{ __('messages.Edit Customer') }}
                    </a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Customer Summary Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Customer Info Card -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="text-center">
                        <div
                            class="mx-auto h-16 w-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xl mb-4">
                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $customer->name }}</h3>
                        @if ($customer->phone)
                            <p class="text-sm text-gray-500 flex items-center justify-center mt-1">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                    </path>
                                </svg>
                                {{ $customer->phone }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                            </path>
                        </svg>
                        {{ __('messages.Current Balance') }}
                    </h3>
                    <div class="text-center">
                        @if ($customer->balance < 0)
                            <div class="text-3xl font-bold text-red-600 mb-2" id="customer-balance">
                                ₪{{ number_format(abs($customer->balance), 2) }}
                            </div>
                            <div
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                {{ __('messages.Outstanding Debt') }}
                            </div>
                        @elseif($customer->balance > 0)
                            <div class="text-3xl font-bold text-green-600 mb-2" id="customer-balance">
                                ₪{{ number_format($customer->balance, 2) }}
                            </div>
                            <div
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                {{ __('messages.Credit Balance') }}
                            </div>
                        @else
                            <div class="text-3xl font-bold text-gray-600 mb-2" id="customer-balance">
                                ₪0.00
                            </div>
                            <div
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                {{ __('messages.Balanced Account') }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ __('messages.Payment Stats') }}</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">{{ __('messages.Total Payments') }}:</span>
                            <span class="text-sm font-medium text-gray-900">{{ $payments->count() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">{{ __('messages.Total Paid') }}:</span>
                            <span class="text-sm font-medium text-green-600">
                                ₪{{ number_format($payments->where('amount', '>', 0)->sum('amount'), 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">{{ __('messages.Total Debt') }}:</span>
                            <span class="text-sm font-medium text-red-600">
                                ₪{{ number_format(abs($payments->where('amount', '<', 0)->sum('amount')), 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Print Filter Card -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                            </path>
                        </svg>
                        {{ __('messages.Print Report') }}
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.From Date') }}</label>
                            <input type="date" id="print-from-date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.To Date') }}</label>
                            <input type="date" id="print-to-date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <button onclick="printPaymentReport()"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                                </path>
                            </svg>
                            {{ __('messages.Print Report') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-8">
                <!-- Add Payment Form -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        {{ __('messages.Add New Payment') }}
                    </h3>

                    <form method="POST" action="{{ route('customers.payments.store', $customer->id) }}"
                        class="space-y-4" id="payment-form">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('messages.Amount') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-500">₪</span>
                                    <input type="number" step="0.01" inputmode="decimal" pattern="[0-9.-]*"
                                        name="amount" id="amount"
                                        placeholder="{{ __('messages.Enter amount') }}"
                                        class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                        required>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('messages.Positive for payments, negative for new debt') }}
                                </p>
                            </div>
                            <div>
                                <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('messages.Date') }}
                                </label>
                                <input type="date" name="payment_date" id="payment_date"
                                    value="{{ date('Y-m-d') }}"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                            </div>
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('messages.Payment Type') }} <span class="text-red-500">*</span>
                                </label>
                                <select name="type" id="type"
                                    class="w-full px-8 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    required>
                                    <option value="cash">{{ __('messages.Cash') }}</option>
                                    <option value="card">{{ __('messages.Card') }}</option>
                                    <option value="transfer">{{ __('messages.Transfer') }}</option>
                                    <option value="check">{{ __('messages.Check') }}</option>
                                </select>
                            </div>

                            <div>
                                <label for="note" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('messages.Note (Optional)') }}
                                </label>
                                <input type="text" name="note" id="note"
                                    placeholder="{{ __('messages.Payment description...') }}"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                            </div>

                            <div class="flex items-end">
                                <button type="submit"
                                    class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center font-medium">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('messages.Add Payment') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Payments History -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                </path>
                            </svg>
                            {{ __('messages.Payment History') }}
                            <span
                                class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                {{ $payments->count() }} {{ __('messages.records') }}
                            </span>
                        </h3>
                    </div>

                    @if ($payments->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Payment ID') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Amount') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Type') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Note') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Date') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($payments as $payment)
                                        <tr data-id="{{ $payment->id }}"
                                            class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-8 w-8">
                                                        <div
                                                            class="h-8 w-8 rounded-full {{ $payment->amount >= 0 ? 'bg-green-100' : 'bg-red-100' }} flex items-center justify-center">
                                                            @if ($payment->amount >= 0)
                                                                <svg class="h-4 w-4 text-green-600"
                                                                    fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd"
                                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                        clip-rule="evenodd"></path>
                                                                </svg>
                                                            @else
                                                                <svg class="h-4 w-4 text-red-600" fill="currentColor"
                                                                    viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd"
                                                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                        clip-rule="evenodd"></path>
                                                                </svg>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            #{{ $payment->id }}</div>
                                                        <div class="text-sm text-gray-500">
                                                            {{ $payment->amount >= 0 ? __('messages.Payment') : __('messages.Debt') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="number" step="0.01" inputmode="decimal"
                                                    pattern="[0-9.-]*"
                                                    class="edit-amount w-24 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    value="{{ $payment->amount }}"
                                                    data-old="{{ $payment->amount }}">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <select
                                                    class="edit-type w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                    <option value="cash"
                                                        {{ $payment->type == 'cash' ? 'selected' : '' }}>
                                                        {{ __('messages.Cash') }}</option>
                                                    <option value="card"
                                                        {{ $payment->type == 'card' ? 'selected' : '' }}>
                                                        {{ __('messages.Card') }}</option>
                                                    <option value="transfer"
                                                        {{ $payment->type == 'transfer' ? 'selected' : '' }}>
                                                        {{ __('messages.Transfer') }}</option>
                                                    <option value="check"
                                                        {{ $payment->type == 'check' ? 'selected' : '' }}>
                                                        {{ __('messages.Check') }}</option>
                                                </select>
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text"
                                                    class="edit-note w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    value="{{ $payment->note }}"
                                                    placeholder="{{ __('messages.No note') }}">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div>{{ $payment->created_at->format('M d, Y') }}</div>
                                                <div class="text-xs text-gray-400">
                                                    {{ $payment->created_at->format('H:i') }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button type="button"
                                                        class="save-payment inline-flex items-center px-3 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-md hover:bg-green-200 transition-colors">
                                                        <svg class="w-4 h-4 mr-1" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                        {{ __('messages.Save') }}
                                                    </button>
                                                    <button type="button"
                                                        class="delete-payment inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 text-xs font-medium rounded-md hover:bg-red-200 transition-colors">
                                                        <svg class="w-4 h-4 mr-1" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                        {{ __('messages.Delete') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 mb-1">
                                    {{ __('messages.No payment history') }}</h3>
                                <p class="text-gray-500">
                                    {{ __('messages.Start by adding the first payment for this customer.') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentBalance = {{ $customer->balance }};

        function getBalance() {
            return currentBalance;
        }

        function setBalance(value) {
            currentBalance = value;
            const balanceElement = document.getElementById('customer-balance');
            const formattedValue = '₪' + Math.abs(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            balanceElement.textContent = formattedValue;

            // Find the badge element - it's in the same parent container as the balance
            // Looking at the HTML structure: <div class="text-center"> contains both balance and badge
            const textCenterDiv = balanceElement.closest('.text-center');
            const badge = textCenterDiv ? textCenterDiv.querySelector('.inline-flex') : null;

            // Check if badge exists before trying to modify it
            if (!badge) {
                console.error('Badge element not found in DOM structure');
                return;
            }

            // Reset classes
            balanceElement.className = 'text-3xl font-bold mb-2';

            if (value < 0) {
                balanceElement.classList.add('text-red-600');
                badge.className =
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800';
                badge.textContent = '{{ __('messages.Outstanding Debt') }}';
            } else if (value > 0) {
                balanceElement.classList.add('text-green-600');
                badge.className =
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800';
                badge.textContent = '{{ __('messages.Credit Balance') }}';
            } else {
                balanceElement.classList.add('text-gray-600');
                badge.className =
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800';
                badge.textContent = '{{ __('messages.Balanced Account') }}';
            }
        }

        // Print Payment Report Function
        function printPaymentReport() {
            const fromDate = document.getElementById('print-from-date').value;
            const toDate = document.getElementById('print-to-date').value;

            // Filter payments based on date range
            const allRows = document.querySelectorAll('tbody tr[data-id]');
            let filteredPayments = [];

            allRows.forEach(row => {
                const dateCell = row.querySelector('td:nth-child(4) div:first-child').textContent.trim();
                const paymentDate = new Date(dateCell);

                const isInRange = (!fromDate || paymentDate >= new Date(fromDate)) &&
                    (!toDate || paymentDate <= new Date(toDate));

                if (isInRange) {
                    const paymentId = row.querySelector('td:nth-child(1) .text-sm.font-medium').textContent.trim();
                    const amount = parseFloat(row.querySelector('.edit-amount').value);
                    const note = row.querySelector('.edit-note').value || '{{ __('messages.No note') }}';
                    const dateText = row.querySelector('td:nth-child(4) div:first-child').textContent.trim();
                    const timeText = row.querySelector('td:nth-child(4) div:last-child').textContent.trim();

                    filteredPayments.push({
                        id: paymentId,
                        amount: amount,
                        note: note,
                        date: dateText,
                        time: timeText
                    });
                }
            });

            if (filteredPayments.length === 0) {
                alert('{{ __('messages.No payments found in the selected date range') }}');
                return;
            }

            // Get shop name based on user role
            const user = @json(auth()->user());
            const shopOwner = @json(auth()->user()->role === 'employee' ? auth()->user()->shopOwner : null);
            const shopName = user.role === 'employee' ? (shopOwner ? shopOwner.name : user.name) : user.name;

            // Calculate totals
            const totalPayments = filteredPayments.filter(p => p.amount > 0).reduce((sum, p) => sum + p.amount, 0);
            const totalDebts = Math.abs(filteredPayments.filter(p => p.amount < 0).reduce((sum, p) => sum + p.amount, 0));
            const netAmount = totalPayments - totalDebts;

            // Get customer balance from current state (real-time balance)
            const customerBalance = getBalance();
            const customerPhone = '{{ $customer->phone ?? '' }}';

            // Create print content
            const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>{{ __('messages.Payment Report') }} - {{ $customer->name }}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .shop-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2563eb;
                    margin-bottom: 5px;
                }
                .report-title {
                    font-size: 20px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .customer-info {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }
                .info-label {
                    font-weight: bold;
                    color: #666;
                }
                .balance-highlight {
                    background-color: #fff3cd;
                    border: 2px solid #ffc107;
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .balance-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #856404;
                    margin-bottom: 10px;
                }
                .balance-amount {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .balance-status {
                    font-size: 14px;
                    font-style: italic;
                    color: #666;
                }
                .date-range {
                    text-align: center;
                    margin-bottom: 20px;
                    font-style: italic;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 12px;
                    text-align: left;
                }
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                    color: #333;
                }
                .amount-positive {
                    color: #059669;
                    font-weight: bold;
                }
                .amount-negative {
                    color: #dc2626;
                    font-weight: bold;
                }
                .summary {
                    background-color: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin-top: 20px;
                }
                .summary-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    padding: 5px 0;
                }
                .summary-label {
                    font-weight: bold;
                }
                .summary-total {
                    border-top: 2px solid #333;
                    margin-top: 10px;
                    padding-top: 10px;
                    font-size: 18px;
                    font-weight: bold;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    color: #666;
                    font-size: 12px;
                }
                .close-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #dc2626;
                    color: white;
                    border: none;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    cursor: pointer;
                    font-size: 18px;
                    font-weight: bold;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                    z-index: 1000;
                }
                .close-button:hover {
                    background: #b91c1c;
                }
                .print-button {
                    position: fixed;
                    top: 20px;
                    right: 70px;
                    background: #2563eb;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    padding: 10px 20px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: bold;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                    z-index: 1000;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .print-button:hover {
                    background: #1d4ed8;
                }
                @media print {
                    body { margin: 0; }
                    .no-print, .close-button, .print-button {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <button class="print-button no-print" onclick="window.print()" title="{{ __('messages.Print Report') }}">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                </svg>
                {{ __('messages.Print') }}
            </button>
            <button class="close-button no-print" onclick="window.close()" title="{{ __('messages.Close Window') }}">&times;</button>

            <div class="header">
                <div class="shop-name">${shopName}</div>
                <div class="report-title">{{ __('messages.Payment Report') }}</div>
            </div>

            <div class="customer-info">
                <div class="info-row">
                    <span class="info-label">{{ __('messages.Customer') }}:</span>
                    <span>{{ $customer->name }}</span>
                </div>
                ${customerPhone ? `
                        <div class="info-row">
                            <span class="info-label">{{ __('messages.Phone') }}:</span>
                            <span>${customerPhone}</span>
                        </div>
                        ` : ''}
                <div class="info-row">
                    <span class="info-label">{{ __('messages.Report Generated') }}:</span>
                    <span>${new Date().toLocaleString()}</span>
                </div>
            </div>

            <div class="balance-highlight">
                <div class="balance-title">{{ __('messages.Current Customer Balance') }}</div>
                <div class="balance-amount ${customerBalance >= 0 ? 'amount-positive' : 'amount-negative'}">
                    ₪${Math.abs(customerBalance).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}
                </div>
                <div class="balance-status">
                    ${customerBalance > 0 ? '{{ __('messages.Customer has credit') }}' :
                      customerBalance < 0 ? '{{ __('messages.Customer owes money') }}' :
                      '{{ __('messages.Account is balanced') }}'}
                </div>
            </div>

            ${fromDate || toDate ? `
                    <div class="date-range">
                        {{ __('messages.Date Range') }}:
                        ${fromDate ? new Date(fromDate).toLocaleDateString() : '{{ __('messages.All dates') }}'}
                        {{ __('messages.to') }}
                        ${toDate ? new Date(toDate).toLocaleDateString() : '{{ __('messages.All dates') }}'}
                    </div>
                    ` : '<div class="date-range">{{ __('messages.All Payment Records') }}</div>'}

            <table>
                <thead>
                    <tr>
                        <th>{{ __('messages.Payment ID') }}</th>
                        <th>{{ __('messages.Date') }}</th>
                        <th>{{ __('messages.Time') }}</th>
                        <th>{{ __('messages.Amount') }}</th>
                        <th>{{ __('messages.Note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    ${filteredPayments.map(payment => `
                                <tr>
                                    <td>${payment.id}</td>
                                    <td>${payment.date}</td>
                                    <td>${payment.time}</td>
                                    <td class="${payment.amount >= 0 ? 'amount-positive' : 'amount-negative'}">
                                        ₪${Math.abs(payment.amount).toFixed(2)} ${payment.amount >= 0 ? '' : '({{ __('messages.Debt') }})'}
                                    </td>
                                    <td>${payment.note}</td>
                                </tr>
                            `).join('')}
                </tbody>
            </table>

            <div class="summary">
                <div class="summary-row">
                    <span class="summary-label">{{ __('messages.Total Records') }}:</span>
                    <span>${filteredPayments.length}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">{{ __('messages.Total Payments') }}:</span>
                    <span class="amount-positive">₪${totalPayments.toFixed(2)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">{{ __('messages.Total Debts') }}:</span>
                    <span class="amount-negative">₪${totalDebts.toFixed(2)}</span>
                </div>
                <div class="summary-row summary-total">
                    <span class="summary-label">{{ __('messages.Net Amount') }}:</span>
                    <span class="${netAmount >= 0 ? 'amount-positive' : 'amount-negative'}">
                        ₪${Math.abs(netAmount).toFixed(2)} ${netAmount >= 0 ? '({{ __('messages.Credit') }})' : '({{ __('messages.Debt') }})'}
                    </span>
                </div>
            </div>

            <div class="footer">
                <p>{{ __('messages.Generated by') }} ${shopName} | {{ __('messages.Date') }}: ${new Date().toLocaleDateString()}</p>
            </div>
        </body>
        </html>
    `;

            // Open print window
            const printWindow = window.open('', '_blank', 'width=900,height=700');

            if (!printWindow) {
                alert('{{ __('messages.Please allow popups for this site to print reports') }}');
                return;
            }

            try {
                printWindow.document.write(printContent);
                printWindow.document.close();

                // Wait for content to load then focus and auto-print
                setTimeout(() => {
                    printWindow.focus();
                    printWindow.print();
                }, 500);

            } catch (error) {
                console.error('Print error:', error);
                alert('{{ __('messages.An error occurred while generating the print report') }}');
                printWindow.close();
            }
        }

        // Set default dates (last 30 days)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);

            document.getElementById('print-to-date').value = today.toISOString().split('T')[0];
            document.getElementById('print-from-date').value = thirtyDaysAgo.toISOString().split('T')[0];
        });

        // Handle Save Payment (Update)
        document.querySelectorAll('.save-payment').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const id = row.dataset.id;
                const amountInput = row.querySelector('.edit-amount');
                const newAmount = parseFloat(amountInput.value);
                const oldAmount = parseFloat(amountInput.getAttribute('data-old') || newAmount);

                const diff = newAmount - oldAmount;
                amountInput.setAttribute('data-old', newAmount);

                // Update balance immediately
                setBalance(getBalance() + diff);

                const note = row.querySelector('.edit-note').value;

                // Store original button content
                const originalContent = this.innerHTML;
                this.innerHTML =
                    '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

                fetch(`/payments/${id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            amount: newAmount,
                            type: row.querySelector('.edit-type').value, // Add this line
                            note
                        })
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Network response was not ok');
                        return res.json();
                    })
                    .then(() => {
                        row.style.backgroundColor = '#d1fae5';
                        setTimeout(() => row.style.backgroundColor = '', 700);
                        this.innerHTML = originalContent;
                    })
                    .catch(err => {
                        console.error('Update failed:', err);
                        // Revert balance change on error
                        setBalance(getBalance() - diff);
                        amountInput.setAttribute('data-old', oldAmount);
                        amountInput.value = oldAmount;
                        this.innerHTML = originalContent;
                        alert('{{ __('messages.Failed to update payment. Please try again.') }}');
                    });
            });
        });

        // Handle Delete Payment
        document.querySelectorAll('.delete-payment').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('{{ __('messages.Are you sure you want to delete this payment?') }}'))
                    return;

                const row = this.closest('tr');
                const id = row.dataset.id;
                const amountInput = row.querySelector('.edit-amount');
                const amount = parseFloat(amountInput.value);

                // Store original state for potential rollback
                const originalBalance = getBalance();

                // Update balance immediately (subtracting payment)
                setBalance(getBalance() - amount);

                // Store original button content and show loading
                const originalContent = this.innerHTML;
                this.innerHTML =
                    '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

                fetch(`/payments/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '{{ csrf_token() }}',
                        }
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Network error while deleting');
                        return res.json();
                    })
                    .then(() => {
                        // Successful deletion - animate row removal
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-100%)';
                        setTimeout(() => {
                            row.remove();
                            // Update payment count in header if it exists
                            const countBadge = document.querySelector(
                                '.bg-blue-100.text-blue-800');
                            if (countBadge) {
                                const currentCount = parseInt(countBadge.textContent.match(
                                    /\d+/)?.[0] || '0');
                                countBadge.textContent =
                                    `${currentCount - 1} {{ __('messages.records') }}`;
                            }
                        }, 300);
                    })
                    .catch(err => {
                        console.error('Delete failed:', err);
                        // Revert balance change on error
                        setBalance(originalBalance);
                        this.innerHTML = originalContent;
                        alert('{{ __('messages.Failed to delete payment. Please try again.') }}');
                    });
            });
        });

        // Handle Add Payment Form Submit
        const addForm = document.getElementById('payment-form');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                const amountInput = this.querySelector('input[name="amount"]');
                const amount = parseFloat(amountInput.value);

                if (isNaN(amount) || amount === 0) {
                    e.preventDefault();
                    alert('{{ __('messages.Please enter a valid amount') }}');
                    amountInput.focus();
                    return;
                }

                // Update balance optimistically
                setBalance(getBalance() + amount);
            });
        }

        // Auto-focus amount input
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            if (amountInput) {
                amountInput.focus();
            }
        });
    </script>
</x-app-layout>
