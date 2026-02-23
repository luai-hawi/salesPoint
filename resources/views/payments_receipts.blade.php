@php
    // FORCE locale setting
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
                {{ __('messages.Payments and Receipts') }}
            </h2>
        </div>
    </x-slot>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <!-- Form Header -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        {{ __('messages.New Transaction') }}
                    </h3>
                </div>

                <form action="{{ route('payments-receipts.store') }}" method="POST" class="p-6 space-y-6"
                    id="payment-receipt-form">
                    @csrf

                    <!-- Transaction Type and Entity Type Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Transaction Type (Payment/Receipt) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                {{ __('messages.Transaction Type') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="transaction_type" value="payment" class="peer sr-only"
                                        checked onchange="updateFormLabels()">
                                    <div
                                        class="px-4 py-3 rounded-lg border-2 border-gray-200 text-center transition-all duration-200
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700
                                        hover:border-gray-300 hover:bg-gray-50">
                                        <svg class="w-6 h-6 mx-auto mb-1 text-red-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                            </path>
                                        </svg>
                                        <span class="text-sm font-medium">{{ __('messages.Payment') }}</span>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('messages.Money Out') }}</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="transaction_type" value="receipt" class="peer sr-only"
                                        onchange="updateFormLabels()">
                                    <div
                                        class="px-4 py-3 rounded-lg border-2 border-gray-200 text-center transition-all duration-200
                                        peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700
                                        hover:border-gray-300 hover:bg-gray-50">
                                        <svg class="w-6 h-6 mx-auto mb-1 text-green-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                            </path>
                                        </svg>
                                        <span class="text-sm font-medium">{{ __('messages.Receipt') }}</span>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('messages.Money In') }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Entity Type (Customer/Employee/Supplier) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                {{ __('messages.Process For') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="entity_type" value="customer" class="peer sr-only"
                                        checked onchange="updateEntityOptions()">
                                    <div
                                        class="px-3 py-2 rounded-lg border-2 border-gray-200 text-center transition-all duration-200 text-sm
                                        peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700
                                        hover:border-gray-300 hover:bg-gray-50">
                                        {{ __('messages.Customer') }}
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="entity_type" value="employee" class="peer sr-only"
                                        onchange="updateEntityOptions()">
                                    <div
                                        class="px-3 py-2 rounded-lg border-2 border-gray-200 text-center transition-all duration-200 text-sm
                                        peer-checked:border-yellow-500 peer-checked:bg-yellow-50 peer-checked:text-yellow-700
                                        hover:border-gray-300 hover:bg-gray-50">
                                        {{ __('messages.Employee') }}
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="entity_type" value="supplier" class="peer sr-only"
                                        onchange="updateEntityOptions()">
                                    <div
                                        class="px-3 py-2 rounded-lg border-2 border-gray-200 text-center transition-all duration-200 text-sm
                                        peer-checked:border-cyan-500 peer-checked:bg-cyan-50 peer-checked:text-cyan-700
                                        hover:border-gray-300 hover:bg-gray-50">
                                        {{ __('messages.Supplier') }}
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Entity Selection (Autocomplete) -->
                    <div>
                        <label for="entity_search" class="block text-sm font-medium text-gray-700 mb-2">
                            <span id="entity-label">{{ __('messages.Select Customer') }}</span> <span
                                class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="entity_search" autocomplete="off"
                                placeholder="{{ __('messages.Start typing to search...') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                required>
                            <input type="hidden" name="entity_id" id="entity_id" required>
                            <div id="entity-warning"
                                class="hidden mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-center text-yellow-800">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-sm font-medium" id="entity-warning-text"></span>
                                </div>
                            </div>
                            <!-- Dropdown suggestions -->
                            <div id="entity-dropdown"
                                class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            </div>
                        </div>
                        <!-- Selected entity info -->
                        <div id="selected-entity-info" class="hidden mt-3 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900" id="selected-entity-name"></p>
                                    <p class="text-sm text-gray-500" id="selected-entity-detail"></p>
                                </div>
                                <button type="button" onclick="clearSelection()"
                                    class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Amount and Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('messages.Amount') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-500">₪</span>
                                <input type="number" step="0.01" inputmode="decimal" pattern="[0-9.-]*"
                                    name="amount" id="amount" placeholder="{{ __('messages.Enter amount') }}"
                                    class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    required>
                            </div>
                        </div>
                        <div>
                            <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('messages.Date') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                required>
                        </div>
                    </div>

                    <!-- Payment Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('messages.Payment Type') }} <span class="text-red-500">*</span>
                        </label>
                        <select name="type" id="type"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                            required>
                            <option value="cash">{{ __('messages.Cash') }}</option>
                            <option value="card">{{ __('messages.Card') }}</option>
                            <option value="transfer">{{ __('messages.Transfer') }}</option>
                            <option value="check">{{ __('messages.Check') }}</option>
                        </select>
                    </div>

                    <!-- Note -->
                    <div>
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('messages.Note (Optional)') }}
                        </label>
                        <textarea name="note" id="note" rows="3" placeholder="{{ __('messages.Payment description...') }}"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end pt-4 border-t border-gray-200">
                        <button type="submit" id="submit-btn"
                            class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-8 py-3 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span id="submit-text">{{ __('messages.Submit Transaction') }}</span>
                            <svg id="loading-icon" class="animate-spin h-5 w-5 mr-2 hidden"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // All data loaded with page
        var customers = @json($customers);
        var employees = @json($employees);
        var suppliers = @json($suppliers);

        function getEntities() {
            var entityType = document.querySelector('input[name="entity_type"]:checked').value;
            if (entityType === 'customer') {
                return customers;
            } else if (entityType === 'employee') {
                return employees;
            } else if (entityType === 'supplier') {
                return suppliers;
            }
            return [];
        }

        function getEntityTypeLabel() {
            var entityType = document.querySelector('input[name="entity_type"]:checked').value;
            if (entityType === 'customer') {
                return '{{ __('messages.Select Customer') }}';
            } else if (entityType === 'employee') {
                return '{{ __('messages.Select Employee') }}';
            } else if (entityType === 'supplier') {
                return '{{ __('messages.Select Supplier') }}';
            }
            return '';
        }

        function updateFormLabels() {
            document.getElementById('entity-label').textContent = getEntityTypeLabel();
            clearSelection();
        }

        function updateEntityOptions() {
            document.getElementById('entity-label').textContent = getEntityTypeLabel();
            clearSelection();
        }

        function clearSelection() {
            document.getElementById('entity_search').value = '';
            document.getElementById('entity_id').value = '';
            document.getElementById('selected-entity-info').classList.add('hidden');
            document.getElementById('entity-warning').classList.add('hidden');
        }

        function showEntities(searchText) {
            var entities = getEntities();
            var search = searchText.toLowerCase();
            var entityType = document.querySelector('input[name="entity_type"]:checked').value;
            var dropdown = document.getElementById('entity-dropdown');

            var filtered = entities.filter(function(e) {
                return e.name.toLowerCase().includes(search);
            });

            if (filtered.length > 0) {
                dropdown.innerHTML = filtered.map(function(entity) {
                    var detail = '';
                    if (entityType === 'customer') {
                        detail = entity.phone ? entity.phone : '';
                        if (entity.balance !== undefined && entity.balance !== null) {
                            detail += detail ? ' - ' : '';
                            detail += '{{ __('messages.Balance') }}: ₪' + parseFloat(entity.balance).toFixed(2);
                        }
                    } else if (entityType === 'employee') {
                        detail = entity.job_title || '';
                    } else if (entityType === 'supplier') {
                        detail = entity.phone ? entity.phone : '';
                        if (entity.balance !== undefined && entity.balance !== null) {
                            detail += detail ? ' - ' : '';
                            detail += '{{ __('messages.Balance') }}: ₪' + parseFloat(entity.balance).toFixed(2);
                        }
                    }

                    return '<div class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0" ' +
                        'onclick="selectEntity(' + entity.id + ', \'' + entity.name.replace(/'/g, "\\'") +
                        '\', \'' + (detail || '').replace(/'/g, "\\'") + '\')">' +
                        '<div class="font-medium text-gray-900">' + entity.name + '</div>' +
                        (detail ? '<div class="text-sm text-gray-500">' + detail + '</div>' : '') +
                        '</div>';
                }).join('');
                dropdown.classList.remove('hidden');
            } else {
                dropdown.classList.add('hidden');
            }
        }

        function selectEntity(id, name, detail) {
            document.getElementById('entity_id').value = id;
            document.getElementById('entity_search').value = name;
            document.getElementById('selected-entity-name').textContent = name;
            document.getElementById('selected-entity-detail').textContent = detail || '';
            document.getElementById('selected-entity-info').classList.remove('hidden');
            document.getElementById('entity-dropdown').classList.add('hidden');
            document.getElementById('entity-warning').classList.add('hidden');
        }

        // Event listeners
        var entitySearch = document.getElementById('entity_search');
        var entityDropdown = document.getElementById('entity-dropdown');

        // Show dropdown on focus
        entitySearch.addEventListener('focus', function() {
            showEntities(this.value);
        });

        // Filter on input
        entitySearch.addEventListener('input', function() {
            showEntities(this.value);
        });

        // Close dropdown on outside click
        document.addEventListener('click', function(e) {
            if (!entitySearch.contains(e.target) && !entityDropdown.contains(e.target)) {
                entityDropdown.classList.add('hidden');
            }
        });

        // Toast notification function
        function showToast(message, type = 'success') {
            var container = document.getElementById('toast-container');
            var toast = document.createElement('div');

            if (type === 'success') {
                toast.className =
                    'bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center animate-slide-in-right';
                toast.innerHTML =
                    '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>' +
                    message;
            } else if (type === 'error') {
                toast.className =
                    'bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center animate-slide-in-right';
                toast.innerHTML =
                    '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>' +
                    message;
            }

            container.appendChild(toast);

            // Remove after 3 seconds
            setTimeout(function() {
                toast.classList.add('animate-fade-out');
                setTimeout(function() {
                    container.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // AJAX form submission
        document.getElementById('payment-receipt-form').addEventListener('submit', function(e) {
            e.preventDefault();

            var entityId = document.getElementById('entity_id').value;
            if (!entityId) {
                var entityType = document.querySelector('input[name="entity_type"]:checked').value;
                showToast('{{ __('messages Please select a') }} ' + entityType, 'error');
                return false;
            }

            // Show loading state
            var submitBtn = document.getElementById('submit-btn');
            var submitText = document.getElementById('submit-text');
            var loadingIcon = document.getElementById('loading-icon');

            submitBtn.disabled = true;
            submitText.textContent = '{{ __('messages.Saving...') }}';
            loadingIcon.classList.remove('hidden');

            var formData = new FormData(this);

            fetch('{{ route('payments-receipts.store') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    // Hide loading state
                    submitBtn.disabled = false;
                    submitText.textContent = '{{ __('messages.Submit Transaction') }}';
                    loadingIcon.classList.add('hidden');

                    if (data.success) {
                        showToast(data.message, 'success');
                        // Reset form fields but keep selected options
                        document.getElementById('amount').value = '';
                        document.getElementById('note').value = '';
                        document.getElementById('payment_date').value = '{{ date('Y-m-d') }}';
                        document.getElementById('type').value = 'cash';
                        clearSelection();
                    } else {
                        showToast(data.message || '{{ __('messages.An error occurred') }}', 'error');
                    }
                })
                .catch(function(error) {
                    // Hide loading state
                    submitBtn.disabled = false;
                    submitText.textContent = '{{ __('messages.Submit Transaction') }}';
                    loadingIcon.classList.add('hidden');

                    showToast('{{ __('messages.An error occurred') }}', 'error');
                });

            return false;
        });
    </script>

    <style>
        @keyframes slide-in-right {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fade-out {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        .animate-slide-in-right {
            animation: slide-in-right 0.3s ease-out;
        }

        .animate-fade-out {
            animation: fade-out 0.3s ease-out;
        }
    </style>
</x-app-layout>
