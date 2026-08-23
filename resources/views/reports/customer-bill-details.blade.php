@php
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    $isRTL = app()->getLocale() === 'ar';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.index') }}"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-700 border border-gray-300 hover:border-indigo-400 bg-white hover:bg-indigo-50 px-3 py-2 rounded-lg transition-colors no-print">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('messages.Back to Reports') }}
                </a>
                <div class="hidden sm:block h-6 w-px bg-gray-300"></div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center gap-3">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ __('messages.Customer Bill Details Report') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div x-data="customerBillReport()" class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" dir="{{ $isRTL ? 'rtl' : 'ltr' }}">

        {{-- Filter Panel --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 no-print">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z" />
                </svg>
                <h3 class="text-sm font-semibold text-gray-700">{{ __('messages.Filters') }}</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.Date From') }}</label>
                        <input type="date" x-model="form.from"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.Date To') }}</label>
                        <input type="date" x-model="form.to"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-2 flex items-end gap-3">
                        <button @click="loadBills()" :disabled="loading"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                            <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <span
                                x-text="loading ? '{{ __('messages.Generating...') }}' : '{{ __('messages.Generate Report') }}'"></span>
                        </button>
                        <button x-show="selectedBills.length > 0" @click="printReport()"
                            class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            {{ __('messages.Print Report') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Error --}}
        <template x-if="error">
            <div
                class="bg-red-50 border border-red-200 rounded-xl px-5 py-4 text-red-700 text-sm flex items-center gap-2 mb-6 no-print">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span x-text="error"></span>
            </div>
        </template>

        {{-- Main Content --}}
        <template x-if="!loaded">
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-1">{{ __('messages.Select Date Range') }}</h3>
                <p class="text-gray-400 text-sm">
                    {{ __('messages.Select a date range and click generate to load bills') }}</p>
            </div>
        </template>

        <template x-if="loaded">
            <div class="space-y-6">
                {{-- Search bar --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 no-print">
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1">
                            <svg class="absolute start-3 top-2.5 h-5 w-5 text-gray-400 mx-4" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text" x-model="searchQuery"
                                :placeholder="'{{ __('messages.Search bills, products, customers...') }}'"
                                class="px-8 w-full border border-gray-300 rounded-lg px-10 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none transition">
                            <template x-if="searchQuery">
                                <button @click="searchQuery = ''"
                                    class="absolute end-3 top-2 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </template>
                        </div>
                        <button @click="toggleSelectAll()"
                            class="inline-flex items-center gap-1.5 border border-gray-300 hover:border-indigo-400 text-gray-700 hover:text-indigo-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
                            :class="allFilteredSelected ? 'bg-indigo-50 border-indigo-400 text-indigo-700' : 'bg-white'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <span
                                x-text="allFilteredSelected ? '{{ __('messages.Deselect All') }}' : '{{ __('messages.Select All') }}'"></span>
                        </button>
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-sm text-gray-500">
                        <span
                            x-text="`{{ __('messages.Showing') }} ${filteredBills.length} {{ __('messages.of') }} ${allBills.length} {{ __('messages.bills') }}`"></span>
                        <template x-if="selectedBills.length > 0">
                            <span class="text-indigo-600 font-medium"
                                x-text="`{{ __('messages.Selected') }}: ${selectedBills.length}`"></span>
                        </template>
                    </div>
                </div>

                {{-- Bills List --}}
                <template x-if="filteredBills.length > 0">
                    <div class="space-y-3 no-print" id="bills-list">
                        <template x-for="bill in filteredBills" :key="bill.id">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden cursor-pointer transition-all"
                                :class="selectedBills.includes(bill.id) ? 'ring-2 ring-indigo-500 border-indigo-500' :
                                    'hover:border-indigo-300'"
                                @click="toggleBill(bill.id)">
                                <div class="px-5 py-4 flex items-center gap-4">
                                    <div class="flex-shrink-0">
                                        <input type="checkbox" :value="bill.id" x-model="selectedBills"
                                            @click.stop
                                            class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span class="font-semibold text-gray-900" x-text="`#${bill.id}`"></span>
                                            <span class="text-sm text-gray-500"
                                                x-text="formatDate(bill.created_at)"></span>
                                            <template x-if="bill.is_damaged">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"
                                                    x-text="'{{ __('messages.Damaged') }}'"></span>
                                            </template>
                                            <template x-if="bill.is_returned">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700"
                                                    x-text="'{{ __('messages.Returned') }}'"></span>
                                            </template>
                                        </div>
                                        <div class="mt-1 flex items-center gap-4 text-sm text-gray-600 flex-wrap">
                                            <span x-text="bill.customer_name"></span>
                                            <template x-if="bill.customer_phone">
                                                <span x-text="`(${bill.customer_phone})`"></span>
                                            </template>
                                            <span x-text="`{{ __('messages.By') }}: ${bill.creator_name}`"></span>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500" x-show="bill.note">
                                            <span class="font-medium">{{ __('messages.Note') }}:</span>
                                            <span x-text="bill.note"></span>
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <p class="text-lg font-bold text-gray-900"
                                            x-text="formatCurrency(bill.total_price)"></p>
                                        <p class="text-xs text-gray-500"
                                            x-text="`${bill.products.length} {{ __('messages.products') }}`"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="filteredBills.length === 0">
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-1">{{ __('messages.No records found') }}
                        </h3>
                        <p class="text-gray-400 text-sm"
                            x-text="searchQuery ? '{{ __('messages.No results match your search') }}' : '{{ __('messages.No bills found in this date range') }}'">
                        </p>
                    </div>
                </template>

                {{-- Selected Bills Details --}}
                <template x-if="selectedBills.length > 0">
                    <div class="space-y-6 no-print" id="selected-bills-details">
                        <template x-for="billId in selectedBills" :key="'detail-' + billId">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                                    <div class="flex items-center justify-between flex-wrap gap-3">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900"
                                                x-text="`{{ __('messages.Bill') }} #${getBill(billId).id}`"></h3>
                                            <div class="mt-1 flex items-center gap-3 text-sm text-gray-600 flex-wrap">
                                                <span x-text="getBill(billId).customer_name"></span>
                                                <template x-if="getBill(billId).customer_phone">
                                                    <span x-text="`(${getBill(billId).customer_phone})`"></span>
                                                </template>
                                                <span x-text="formatDate(getBill(billId).created_at)"></span>
                                            </div>
                                            <div class="mt-1 text-sm text-gray-500" x-show="getBill(billId).note">
                                                <span class="font-medium">{{ __('messages.Note') }}:</span>
                                                <span x-text="getBill(billId).note"></span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <p class="text-sm text-gray-600">{{ __('messages.Total') }}</p>
                                            <p class="text-xl font-bold text-gray-900"
                                                x-text="formatCurrency(getBill(billId).total_price)"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 border-b border-gray-200">
                                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600"
                                                    x-text="'{{ __('messages.Product') }}'"></th>
                                                <th class="px-4 py-3 text-end text-xs font-semibold text-gray-600"
                                                    x-text="'{{ __('messages.Quantity') }}'"></th>
                                                <th class="px-4 py-3 text-end text-xs font-semibold text-gray-600"
                                                    x-text="'{{ __('messages.Unit Price') }}'"></th>
                                                <th class="px-4 py-3 text-end text-xs font-semibold text-gray-600"
                                                    x-text="'{{ __('messages.Discount') }}'"></th>
                                                <th class="px-4 py-3 text-end text-xs font-semibold text-gray-600"
                                                    x-text="'{{ __('messages.Subtotal') }}'"></th>
                                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-600"
                                                    x-text="'{{ __('messages.Tags') }}'"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="product in getBill(billId).products"
                                                :key="product.id">
                                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                    <td class="px-4 py-3">
                                                        <div>
                                                            <p class="font-medium text-gray-900"
                                                                x-text="product.name"></p>
                                                            <template x-if="product.barcode">
                                                                <p class="text-xs text-gray-500"
                                                                    x-text="product.barcode"></p>
                                                            </template>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 text-end text-gray-700"
                                                        x-text="product.quantity"></td>
                                                    <td class="px-4 py-3 text-end text-gray-700"
                                                        x-text="formatCurrency(product.selling_price)"></td>
                                                    <td class="px-4 py-3 text-end text-gray-700"
                                                        x-text="product.discount > 0 ? formatCurrency(product.discount) : '—'">
                                                    </td>
                                                    <td class="px-4 py-3 text-end font-semibold text-gray-900"
                                                        x-text="formatCurrency((product.selling_price * product.quantity) - product.discount)">
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <template x-if="product.tags">
                                                            <div class="flex flex-wrap gap-1">
                                                                <template x-for="tag in parseTags(product.tags)"
                                                                    :key="tag.name">
                                                                    <span
                                                                        class="inline-block bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full text-xs"
                                                                        x-text="`${tag.name} (+${formatCurrency(tag.price)})`"></span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="!product.tags">
                                                            <span class="text-gray-400 text-xs">—</span>
                                                        </template>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Print-only clean report --}}
                <div id="print-report-area" class="print-only hidden print:block">
                    <div class="report-header">
                        <img src="{{ asset('images/logo4.png') }}" alt="{{ config('app.name', 'SalesPoint') }}">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                {{ __('messages.Customer Bill Details Report') }}</h1>
                            <p class="text-gray-500 text-sm mt-1" x-text="printSubtitle"></p>
                            <p class="text-gray-400 text-xs mt-1">{{ __('messages.Generated at') }}:
                                {{ now()->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>
                    <template x-for="billId in selectedBills" :key="'print-detail-' + billId">
                        <div class="mb-6">
                            <div class="mb-3 pb-2 border-b border-gray-200">
                                <h2 class="text-lg font-bold text-gray-800"
                                    x-text="`{{ __('messages.Bill') }} #${getBill(billId).id}`"></h2>
                                <div class="mt-1 text-sm text-gray-600">
                                    <span x-text="getBill(billId).customer_name"></span>
                                    <template x-if="getBill(billId).customer_phone">
                                        <span x-text="`, ${getBill(billId).customer_phone}`"></span>
                                    </template>
                                    <span x-text="`, ${formatDate(getBill(billId).created_at)}`"></span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500" x-show="getBill(billId).note">
                                    <span class="font-medium">{{ __('messages.Note') }}:</span>
                                    <span x-text="getBill(billId).note"></span>
                                </div>
                                <div class="mt-2 text-base font-bold text-gray-900">
                                    {{ __('messages.Total') }}: <span
                                        x-text="formatCurrency(getBill(billId).total_price)"></span>
                                </div>
                            </div>
                            <table class="w-full text-sm border-collapse border border-gray-300">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 px-3 py-2 text-start text-xs font-bold text-gray-700"
                                            x-text="'{{ __('messages.Product') }}'"></th>
                                        <th class="border border-gray-300 px-3 py-2 text-end text-xs font-bold text-gray-700"
                                            x-text="'{{ __('messages.Quantity') }}'"></th>
                                        <th class="border border-gray-300 px-3 py-2 text-end text-xs font-bold text-gray-700"
                                            x-text="'{{ __('messages.Unit Price') }}'"></th>
                                        <th class="border border-gray-300 px-3 py-2 text-end text-xs font-bold text-gray-700"
                                            x-text="'{{ __('messages.Discount') }}'"></th>
                                        <th class="border border-gray-300 px-3 py-2 text-end text-xs font-bold text-gray-700"
                                            x-text="'{{ __('messages.Subtotal') }}'"></th>
                                        <th class="border border-gray-300 px-3 py-2 text-start text-xs font-bold text-gray-700"
                                            x-text="'{{ __('messages.Tags') }}'"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="product in getBill(billId).products"
                                        :key="'prod-' + billId + '-' + product.id">
                                        <tr>
                                            <td class="border border-gray-300 px-3 py-2 text-gray-900">
                                                <p class="font-medium" x-text="product.name"></p>
                                                <template x-if="product.barcode">
                                                    <p class="text-xs text-gray-500" x-text="product.barcode"></p>
                                                </template>
                                            </td>
                                            <td class="border border-gray-300 px-3 py-2 text-end text-gray-700"
                                                x-text="product.quantity"></td>
                                            <td class="border border-gray-300 px-3 py-2 text-end text-gray-700"
                                                x-text="formatCurrency(product.selling_price)"></td>
                                            <td class="border border-gray-300 px-3 py-2 text-end text-gray-700"
                                                x-text="product.discount > 0 ? formatCurrency(product.discount) : '—'">
                                            </td>
                                            <td class="border border-gray-300 px-3 py-2 text-end font-bold text-gray-900"
                                                x-text="formatCurrency((product.selling_price * product.quantity) - product.discount)">
                                            </td>
                                            <td class="border border-gray-300 px-3 py-2">
                                                <template x-if="product.tags">
                                                    <div class="flex flex-wrap gap-1">
                                                        <template x-for="tag in parseTags(product.tags)"
                                                            :key="tag.name">
                                                            <span
                                                                class="inline-block bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full text-xs"
                                                                x-text="`${tag.name} (+${formatCurrency(tag.price)})`"></span>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="!product.tags">
                                                    <span class="text-gray-400 text-xs">—</span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <style>
        @media print {
            @page {
                margin: 1.5cm;
            }

            html,
            body {
                background: white !important;
            }

            .no-print,
            nav,
            aside,
            header,
            #selected-bills-details,
            #mobile-nav-topbar,
            [x-cloak],
            .print-only:not(#print-report-area) {
                display: none !important;
            }

            .app-shell {
                padding-top: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                background: white !important;
            }

            #print-report-area {
                display: block !important;
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                position: static !important;
                visibility: visible !important;
            }

            #print-report-area .report-header {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                margin-bottom: 16px !important;
            }

            #print-report-area .report-header img {
                display: block !important;
                width: 48px !important;
                height: 48px !important;
            }

            #print-report-area table {
                border-collapse: collapse;
                width: 100%;
                font-size: 11px;
            }

            #print-report-area th,
            #print-report-area td {
                border: 1px solid #d1d5db;
                padding: 5px 8px;
                text-align: start;
            }

            #print-report-area thead tr {
                background: #f3f4f6 !important;
            }

            #print-report-area tbody tr:nth-child(even) {
                background: #f9fafb;
            }
        }

        @media screen {
            #print-report-area {
                display: none !important;
            }
        }
    </style>

    @push('scripts')
        <script>
            function customerBillReport() {
                return {
                    form: {
                        from: '{{ now()->subMonth()->format('Y-m-d') }}',
                        to: '{{ now()->format('Y-m-d') }}',
                    },
                    loading: false,
                    loaded: false,
                    error: null,
                    allBills: [],
                    filteredBills: [],
                    selectedBills: [],
                    searchQuery: '',
                    printSubtitle: '',

                    init() {
                        this.$watch('searchQuery', (value) => {
                            const query = value.toLowerCase().trim();
                            if (!query) {
                                this.filteredBills = this.allBills;
                                return;
                            }
                            this.filteredBills = this.allBills.filter(bill => {
                                const searchFields = [
                                    String(bill.id),
                                    bill.note || '',
                                    bill.customer_name || '',
                                    bill.customer_phone || '',
                                    bill.creator_name || '',
                                    ...bill.products.map(p => p.name),
                                    ...bill.products.map(p => p.barcode || ''),
                                ];
                                return searchFields.some(field => field.toLowerCase().includes(query));
                            });
                        });
                    },

                    toggleBill(id) {
                        const idx = this.selectedBills.indexOf(id);
                        if (idx === -1) {
                            this.selectedBills.push(id);
                        } else {
                            this.selectedBills.splice(idx, 1);
                        }
                    },

                    get allFilteredSelected() {
                        if (this.filteredBills.length === 0) return false;
                        return this.filteredBills.every(bill => this.selectedBills.includes(bill.id));
                    },

                    toggleSelectAll() {
                        if (this.allFilteredSelected) {
                            this.selectedBills = this.selectedBills.filter(id => !this.filteredBills.some(b => b.id === id));
                        } else {
                            const newIds = this.filteredBills.map(b => b.id);
                            this.selectedBills = [...new Set([...this.selectedBills, ...newIds])];
                        }
                    },

                    getBill(id) {
                        return this.allBills.find(b => b.id === id) || {};
                    },

                    async loadBills() {
                        this.loading = true;
                        this.error = null;
                        this.selectedBills = [];
                        this.searchQuery = '';
                        this.filteredBills = [];

                        const params = new URLSearchParams({
                            from: this.form.from,
                            to: this.form.to,
                        });

                        try {
                            const res = await fetch(`{{ route('reports.customer-bill-details.data') }}?${params}`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                }
                            });
                            const data = await res.json();

                            if (!res.ok || !data.success) {
                                this.error = data.message ?? '{{ __('messages.Error loading bills') }}';
                            } else {
                                this.allBills = data.bills;
                                this.filteredBills = data.bills;
                                this.loaded = true;
                                this.printSubtitle =
                                    `{{ __('messages.From') }}: ${this.form.from}   |   {{ __('messages.To') }}: ${this.form.to}`;
                            }
                        } catch (e) {
                            this.error = '{{ __('messages.Network error. Please try again.') }}';
                        } finally {
                            this.loading = false;
                        }
                    },

                    formatDate(dateStr) {
                        if (!dateStr) return '—';
                        const d = new Date(dateStr.replace(' ', 'T'));
                        if (isNaN(d)) return dateStr;
                        return d.toLocaleDateString('{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    },

                    formatCurrency(val) {
                        const n = parseFloat(val) || 0;
                        return n.toLocaleString('{{ app()->getLocale() === 'ar' ? 'ar-SA' : 'en-US' }}', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    },

                    parseTags(tagsStr) {
                        if (!tagsStr) return [];
                        return tagsStr.split('&').map(pair => {
                            const [name, price] = pair.split('@');
                            return {
                                name: name || '',
                                price: parseFloat(price) || 0
                            };
                        }).filter(t => t.name);
                    },

                    printReport() {
                        window.print();
                    },
                };
            }
        </script>
    @endpush

</x-app-layout>
