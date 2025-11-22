@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                {{ __('messages.Out of Stock Products') }}
            </h2>
            <div class="text-sm text-gray-600 bg-orange-100 px-4 py-2 rounded-full">
                {{ __('messages.Total Products:') }} <span class="font-bold text-orange-600">{{ $products->total() }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 bg-gradient-to-br from-gray-50 to-orange-50 min-h-screen">
        <div class="w-full px-4 sm:px-6 lg:px-8 max-w-none">

            @if (session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-green-800">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <!-- Settings Panel -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-4">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('messages.Deactivation Settings') }}</h3>
                </div>

                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Warning Period (Months)') }}</label>
                        <input type="number" name="warning_months" value="{{ $warningMonths }}" min="1" max="24" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Deactivation Period (Months)') }}</label>
                        <input type="number" name="deactivation_months" value="{{ $deactivationMonths }}" min="1" max="24" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Filter Status') }}</label>
                        <select name="filter" class="w-full px-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">{{ __('messages.All Out of Stock') }}</option>
                            <option value="warning" {{ request('filter') === 'warning' ? 'selected' : '' }}>{{ __('messages.Warning Period') }}</option>
                            <option value="deactivation" {{ request('filter') === 'deactivation' ? 'selected' : '' }}>{{ __('messages.Deactivation Period') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            {{ __('messages.Apply Settings') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <form method="POST" action="{{ route('products.out-of-stock.bulk') }}" id="bulk-actions-form" class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                @csrf
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 p-6">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="select-all" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="mx-2 text-sm font-medium text-gray-700">{{ __('messages.Select All') }}</span>
                        </label>
                        <span class="text-sm text-gray-500">{{ __('messages.selected:') }} <span id="selected-count">0</span></span>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" name="action" value="extend" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center disabled:opacity-50" id="extend-btn" disabled>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('messages.Extend Period') }}
                        </button>
                        <button type="submit" name="action" value="deactivate" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center disabled:opacity-50" id="deactivate-btn" disabled>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            {{ __('messages.Deactivate Now') }}
                        </button>
                    </div>
                </div>

                <!-- Extend Period Modal -->
                <div id="extend-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('messages.Extend Deactivation Period') }}</h3>
                                        <div class="mt-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Extend by (Months)') }}</label>
                                            <input type="number" id="extend-months" name="extend_months" min="1" max="24" value="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" id="confirm-extend" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    {{ __('messages.Extend') }}
                                </button>
                                <button type="button" id="cancel-extend" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    {{ __('messages.Cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        {{ __('messages.Out of Stock Products') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">{{ __('messages.Products that haven\'t been sold recently and may need deactivation.') }}</p>
                </div>

                <div class="p-6">
                    @if($products->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('messages.No out of stock products found') }}</h3>
                            <p class="mt-2 text-gray-500">{{ __('messages.All products are either in stock or recently sold.') }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($products as $product)
                                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="product-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($product->status === 'extended')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ __('messages.Extended') }}
                                                </span>
                                            @elseif($product->status === 'warning')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    {{ __('messages.Warning') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    {{ __('messages.Deactivation') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <h4 class="font-semibold text-gray-900 mb-1">{{ $product->name }}</h4>
                                        <p class="text-sm text-gray-600">{{ $product->category }}</p>
                                        <p class="text-xs text-gray-500">{{ __('messages.Barcode:') }} {{ $product->barcode ?: __('messages.N/A') }}</p>
                                    </div>

                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('messages.Last Sale:') }}</span>
                                            <span class="font-medium">{{ $product->last_sale_date ? \Carbon\Carbon::parse($product->last_sale_date)->format('M d, Y') : __('messages.Never') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('messages.Months Since Sale:') }}</span>
                                            <span class="font-medium">{{ $product->months_since_sale }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('messages.Current Stock:') }}</span>
                                            <span class="font-medium text-red-600">{{ $product->quantity }}</span>
                                        </div>
                                        @if($product->extended_until)
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">{{ __('messages.Extended Until:') }}</span>
                                                <span class="font-medium text-blue-600">{{ \Carbon\Carbon::parse($product->extended_until)->format('M d, Y') }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    @if($product->pictures)
                                        <div class="mt-3">
                                            <p class="text-xs text-gray-500 mb-1">{{ __('messages.Has Images:') }} {{ __('messages.Yes') }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        @if($products->hasPages())
                            <div class="mt-6">
                                {{ $products->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const selectedCount = document.getElementById('selected-count');
            const extendBtn = document.getElementById('extend-btn');
            const deactivateBtn = document.getElementById('deactivate-btn');
            const extendModal = document.getElementById('extend-modal');
            const confirmExtendBtn = document.getElementById('confirm-extend');
            const cancelExtendBtn = document.getElementById('cancel-extend');
            const bulkActionsForm = document.getElementById('bulk-actions-form');

            function updateSelectedCount() {
                const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
                const count = checkedBoxes.length;
                selectedCount.textContent = count;

                extendBtn.disabled = count === 0;
                deactivateBtn.disabled = count === 0;

                extendBtn.classList.toggle('opacity-50', count === 0);
                deactivateBtn.classList.toggle('opacity-50', count === 0);
            }

            selectAllCheckbox.addEventListener('change', function() {
                productCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedCount();
            });

            productCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(productCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(productCheckboxes).some(cb => cb.checked);

                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;

                    updateSelectedCount();
                });
            });

            extendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                extendModal.classList.remove('hidden');
            });

            cancelExtendBtn.addEventListener('click', function() {
                extendModal.classList.add('hidden');
            });

            confirmExtendBtn.addEventListener('click', function() {
                const extendMonths = document.getElementById('extend-months').value;
                const extendMonthsInput = document.createElement('input');
                extendMonthsInput.type = 'hidden';
                extendMonthsInput.name = 'extend_months';
                extendMonthsInput.value = extendMonths;
                bulkActionsForm.appendChild(extendMonthsInput);

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'extend';
                bulkActionsForm.appendChild(actionInput);

                console.log('Submitting extend form with months:', extendMonths);
                bulkActionsForm.submit();
            });

            // Debug form submission
            bulkActionsForm.addEventListener('submit', function(e) {
                const formData = new FormData(this);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    if (key === 'product_ids[]') {
                        if (!data.product_ids) data.product_ids = [];
                        data.product_ids.push(value);
                    } else {
                        data[key] = value;
                    }
                }
                console.log('Form submission data:', data);
            });

            // Close modal when clicking outside
            extendModal.addEventListener('click', function(e) {
                if (e.target === extendModal) {
                    extendModal.classList.add('hidden');
                }
            });

            updateSelectedCount();
        });
    </script>
</x-app-layout>