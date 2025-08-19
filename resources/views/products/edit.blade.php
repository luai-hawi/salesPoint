<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                {{ __('Edit Product: ') . $product->name }}
            </h2>
            <a href="{{ route('products.index') }}" class="text-gray-600 hover:text-gray-800 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Products
            </a>
        </div>
    </x-slot>

    <!-- Add CSRF token to meta for JavaScript access -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                <!-- Left Column - Product Form -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-yellow-50 to-orange-50">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                Product Information
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Update product details and manage inventory.</p>
                        </div>

                        <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="p-6">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Product Name -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name</label>
                                    <input type="text" name="name" value="{{ old('name', $product->name) }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors" required>
                                </div>

                                <!-- Barcode -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Barcode</label>
                                    <input type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors">
                                </div>

                                <!-- Current Quantity (Read-only) -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Current Stock</label>
                                    <div class="relative">
                                        <input type="number" value="{{ $product->quantity }}" readonly
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <small class="text-gray-500">Managed automatically through batches</small>
                                </div>

                                <!-- Cost Price -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Average Cost Price</label>
                                    <div class="relative">
                                        <input type="number" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}"
                                               class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors" step="0.01" required>
                                        <span class="absolute left-3 top-3.5 text-gray-400">$</span>
                                    </div>
                                </div>

                                <!-- Selling Price -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Selling Price</label>
                                    <div class="relative">
                                        <input type="number" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}"
                                               class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors" step="0.01" required>
                                        <span class="absolute left-3 top-3.5 text-gray-400">$</span>
                                    </div>
                                </div>

                                <!-- Product Pictures -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Pictures</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                                        <div class="space-y-2 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="pictures" class="relative cursor-pointer bg-white rounded-md font-medium text-yellow-600 hover:text-yellow-500">
                                                    <span>Upload new images</span>
                                                    <input id="pictures" name="pictures[]" type="file" class="sr-only" multiple accept="image/*">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB each</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end">
                                <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Update Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Batch Management -->
                <div class="space-y-6">
                    
                    <!-- Product Stats Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Quick Stats
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-700">Total Stock:</span>
                                    <span class="font-bold text-green-800" id="total-stock">{{ $product->quantity }}</span>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-700">Profit Margin:</span>
                                    @php
                                        $margin = $product->selling_price > 0 ? (($product->selling_price - $product->cost_price) / $product->selling_price) * 100 : 0;
                                    @endphp
                                    <span class="font-bold text-blue-800">{{ number_format($margin, 1) }}%</span>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-purple-700">Batches:</span>
                                    <span class="font-bold text-purple-800" id="batch-count">{{ $product->batches->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add New Batch -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Batch
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                <input type="number" id="new-batch-qty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Enter quantity" min="1">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cost Price per Unit</label>
                                <div class="relative">
                                    <input type="number" id="new-batch-cost" step="0.01" class="w-full px-3 py-2 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="0.00" min="0">
                                    <span class="absolute left-3 top-2.5 text-gray-400">$</span>
                                </div>
                            </div>
                            
                            <button type="button" id="add-batch" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Batch
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Batch Management Section -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        Product Batches
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Manage individual stock batches with different cost prices.</p>
                </div>

                <div id="batches-container">
                    @if($product->batches->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Value</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($product->batches as $batch)
                                        <tr data-id="{{ $batch->id }}" class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    #{{ $batch->id }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="number" class="batch-qty w-20 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                       value="{{ $batch->quantity }}" min="0">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="relative">
                                                    <input type="number" step="0.01" class="batch-cost w-24 px-2 py-1 pl-6 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                           value="{{ $batch->cost_price }}" min="0">
                                                    <span class="absolute left-2 top-1.5 text-gray-400 text-sm">$</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 total-value">
                                                ${{ number_format($batch->quantity * $batch->cost_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $batch->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button type="button" class="save-batch bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                                        Save
                                                    </button>
                                                    <button type="button" class="delete-batch bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-12 text-center">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">No batches yet</h3>
                            <p class="mt-2 text-gray-500">Get started by adding your first batch above.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Show notifications
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Update total value cell
        function updateTotalValue(row) {
            const qty = parseFloat(row.querySelector('.batch-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.batch-cost').value) || 0;
            const totalValue = qty * cost;
            row.querySelector('.total-value').textContent = '$' + totalValue.toFixed(2);
        }

        // Update live total value when inputs change
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('batch-qty') || e.target.classList.contains('batch-cost')) {
                const row = e.target.closest('tr');
                updateTotalValue(row);
            }
        });

        // Add new batch functionality
        document.getElementById('add-batch').addEventListener('click', function () {
            const qtyInput = document.getElementById('new-batch-qty');
            const costInput = document.getElementById('new-batch-cost');
            const qty = parseInt(qtyInput.value, 10);
            const cost = parseFloat(costInput.value);

            if (!qty || qty <= 0) {
                showNotification('Please enter a valid quantity.', 'error');
                return;
            }

            if (!cost || cost < 0) {
                showNotification('Please enter a valid cost price.', 'error');
                return;
            }

            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Adding...';

            // Check for existing batch with same cost price
            let updatedExisting = false;
            const existingRows = document.querySelectorAll('tbody tr[data-id]');
            
            for (const row of existingRows) {
                const batchCostInput = row.querySelector('.batch-cost');
                const batchQtyInput = row.querySelector('.batch-qty');

                if (batchCostInput && parseFloat(batchCostInput.value) === cost) {
                    const currentQty = parseInt(batchQtyInput.value, 10);
                    batchQtyInput.value = currentQty + qty;
                    updateTotalValue(row);
                    
                    // Save the updated batch
                    row.querySelector('.save-batch').click();
                    updatedExisting = true;
                    break;
                }
            }

            if (!updatedExisting) {
                // Create new batch
                fetch('/batches', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        product_id: {{ $product->id }},
                        quantity: qty,
                        cost_price: cost
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showNotification('Batch added successfully!');
                        // Clear inputs
                        qtyInput.value = '';
                        costInput.value = '';
                        // Reload page to show new batch
                        location.reload();
                    } else {
                        throw new Error('Server returned success: false');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to add batch. Please try again.', 'error');
                })
                .finally(() => {
                    // Reset button
                    this.disabled = false;
                    this.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>Add Batch';
                });
            } else {
                // Reset button if we updated existing
                this.disabled = false;
                this.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>Add Batch';
                
                // Clear inputs
                qtyInput.value = '';
                costInput.value = '';
                
                showNotification('Existing batch updated with new quantity!');
            }
        });

        // Save batch functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('save-batch')) {
                const btn = e.target;
                const row = btn.closest('tr');
                const id = row.dataset.id;
                const qty = row.querySelector('.batch-qty').value;
                const cost = row.querySelector('.batch-cost').value;

                if (!qty || qty < 0) {
                    showNotification('Please enter a valid quantity.', 'error');
                    return;
                }

                if (!cost || cost < 0) {
                    showNotification('Please enter a valid cost price.', 'error');
                    return;
                }

                // Add loading state
                const originalText = btn.textContent;
                btn.textContent = 'Saving...';
                btn.disabled = true;

                fetch(`/batches/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ 
                        quantity: parseInt(qty, 10), 
                        cost_price: parseFloat(cost) 
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Flash success
                        row.classList.add('bg-green-50');
                        setTimeout(() => row.classList.remove('bg-green-50'), 1000);
                        
                        // Update total value
                        updateTotalValue(row);
                        
                        showNotification('Batch updated successfully!');
                    } else {
                        throw new Error('Server returned success: false');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to save batch. Please try again.', 'error');
                })
                .finally(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
            }
        });

        // Delete batch functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-batch')) {
                if (!confirm('Are you sure you want to delete this batch?')) return;
                
                const btn = e.target;
                const row = btn.closest('tr');
                const id = row.dataset.id;

                // Add loading state
                const originalText = btn.textContent;
                btn.textContent = 'Deleting...';
                btn.disabled = true;

                fetch(`/batches/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        row.remove();
                        showNotification('Batch deleted successfully!');
                        
                        // Update batch count
                        const batchCount = document.getElementById('batch-count');
                        if (batchCount) {
                            const currentCount = parseInt(batchCount.textContent);
                            batchCount.textContent = currentCount - 1;
                        }
                        
                        // Check if no batches left and show empty state
                        const tbody = document.querySelector('tbody');
                        if (tbody && tbody.children.length === 0) {
                            const batchesContainer = document.getElementById('batches-container');
                            batchesContainer.innerHTML = `
                                <div class="p-12 text-center">
                                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900">No batches yet</h3>
                                    <p class="mt-2 text-gray-500">Get started by adding your first batch above.</p>
                                </div>
                            `;
                        }
                    } else {
                        throw new Error('Server returned success: false');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to delete batch. Please try again.', 'error');
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
            }
        });

        // Image compression function
        async function compressImage(file, maxWidth = 800, maxHeight = 800, quality = 0.7) {
            return new Promise((resolve) => {
                const img = new Image();
                const reader = new FileReader();

                reader.onload = function (e) {
                    img.src = e.target.result;
                };

                img.onload = function () {
                    let width = img.width;
                    let height = img.height;

                    // Keep aspect ratio while resizing
                    if (width > maxWidth || height > maxHeight) {
                        if (width > height) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        } else {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    const canvas = document.createElement("canvas");
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(
                        (blob) => {
                            const compressedFile = new File([blob], file.name, {
                                type: file.type,
                                lastModified: Date.now(),
                            });
                            resolve(compressedFile);
                        },
                        file.type,
                        quality
                    );
                };

                reader.readAsDataURL(file);
            });
        }

        async function handleCompressedUpload(input) {
            const dt = new DataTransfer();

            for (let file of input.files) {
                if (file.type.startsWith("image/")) {
                    const compressed = await compressImage(file, 800, 800, 0.7);
                    dt.items.add(compressed);
                } else {
                    dt.items.add(file);
                }
            }

            input.files = dt.files; // Replace with compressed versions
        }

// Attach handler to your input
document.getElementById("pictures").addEventListener("change", async function (e) {
    await handleCompressedUpload(e.target);
});




        // Auto-save on Enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && (e.target.classList.contains('batch-qty') || e.target.classList.contains('batch-cost'))) {
                const saveBtn = e.target.closest('tr').querySelector('.save-batch');
                if (saveBtn) saveBtn.click();
            }
        });
    </script>
</x-app-layout>