@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    
    // Get shop name based on user role
    $shopName = 'Shop'; // Default fallback
    if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id) {
        $shopName = auth()->user()->shopOwner->name ?? 'Shop';
    } elseif (auth()->user()->role !== 'employee') {
        $shopName = auth()->user()->name ?? 'Shop';
    }
@endphp
<x-app-layout>
    {{-- Edit Bill Header --}}
<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
            <svg class="w-8 h-8 mr-3 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            {{ __('bills.Edit Bill #') . $bill->id }}
        </h2>
        <div class="flex items-center space-x-4">
            <div class="text-sm text-gray-600 bg-gray-100 px-4 py-2 rounded-full">
                {{ __('bills.Status') }}: <span class="font-bold text-orange-600">{{ ucfirst($bill->status ?? __('bills.Draft')) }}</span>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('bills.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('bills.Back to Bills') }}
                </a>
                <button id="print-button" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    {{ __('bills.Print Bill') }}
                </button>
                <button id="print-receipt-button" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('bills.Print Receipt') }}
                </button>
            </div>
        </div>
    </div>
</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Bill Information Card -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-500 rounded-full p-2 mr-3">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('bills.Customer') }}</p>
                            <p class="font-semibold text-gray-900">{{ $bill->customer->name ?? __('bills.Walk-in Customer') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="bg-green-500 rounded-full p-2 mr-3">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('bills.Total Amount') }}</p>
                            <p class="font-semibold text-gray-900">${{ number_format($bill->total_price, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-purple-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-500 rounded-full p-2 mr-3">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('bills.Created') }}</p>
                            <p class="font-semibold text-gray-900">{{ $bill->created_at->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-yellow-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-500 rounded-full p-2 mr-3">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('bills.Created By') }}</p>
                            <p class="font-semibold text-gray-900">{{ $bill->creator->name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <form id="form" action="{{ route('bills.update', $bill->id) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <!-- Note Section -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    {{ __('bills.Bill Note') }}
                </h3>
                <textarea name="note" id="note" rows="3" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                          placeholder="{{ __('bills.Add a note to this bill...') }}">{{ old('note', $bill->note) }}</textarea>
            </div>

            <!-- Products Section -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        {{ __('bills.Bill Products') }}
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table id="products-table" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Product') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Quantity') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Unit Price') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Discount') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Total') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('bills.Remove') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($bill->products as $product)
                                <tr data-product-id="{{ $product->id }}" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <svg class="h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $product->barcode ?? __('bills.No barcode') }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" name="quantities[{{ $product->id }}]" 
                                               value="{{ old("quantities.$product->id", $product->pivot->quantity) }}" 
                                               min="1" 
                                               class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent quantity" 
                                               required>
                                        <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        ${{ number_format($product->pivot->selling_price, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" name="discounts[{{ $product->id }}]" 
                                               value="{{ old("discounts.$product->id", $product->pivot->discount ?? 0) }}" 
                                               min="0" 
                                               class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent discount" 
                                               step="0.01" 
                                               required>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 total-cell">
                                        ${{ number_format($product->pivot->quantity * $product->pivot->selling_price - ($product->pivot->discount ?? 0), 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="remove_products[]" value="{{ $product->id }}" 
                                                   class="rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-red-600">{{ __('bills.Remove') }}</span>
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            
        </form>
        <!-- Add Products Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    {{ __('bills.Add Products') }}
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Add by Barcode -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-medium text-gray-800 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                            </svg>
                            {{ __('bills.Scan Barcode') }}
                        </h4>
                        <div class="flex space-x-3">
                            <input type="text" id="barcode_input" 
                                   placeholder="{{ __('messages.Scan or enter barcode...') }}"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                            <button type="button" id="add_barcode_product" 
                                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('bills.Add') }}
                            </button>
                        </div>
                    </div>

                    <!-- Add by Selection -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-medium text-gray-800 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            {{ __('bills.Select Product') }}
                        </h4>
                        <div class="flex space-x-3">
                            <select id="product_select" name="new_product_id" 
                                    class="flex-1 px-8 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                                <option value="">{{ __('bills.Choose Product') }}</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->name }} (${{ number_format($prod->selling_price, 2) }})</option>
                                @endforeach
                            </select>
                            <input type="number" id="new_quantity" name="new_quantity" 
                                   class="w-20 px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   min="1" placeholder="{{ __('messages.Enter quantity') }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between items-center mt-8">
                <div class="flex space-x-4">
                    <button id="save" type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors flex items-center font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {{ __('bills.Save Changes') }}
                    </button>
                    <a href="{{ route('bills.index') }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded-lg transition-colors flex items-center font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        {{ __('bills.Cancel') }}
                    </a>
                </div>
                
                <div class="text-right">
                    <p class="text-sm text-gray-600">{{ __('bills.Grand Total') }}</p>
                    <p class="text-2xl font-bold text-gray-900" id="grand-total">${{ number_format($bill->total_price, 2) }}</p>
                </div>
            </div>
    </div>

    {{-- Standard Printable Invoice --}}
    <div id="print-area" class="print-hidden p-6 text-sm">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">{{ $shopName }}</h1>
            <p>{{ now()->format('Y-m-d H:i') }}</p>
            <p class="text-sm font-medium">Bill ID: #{{ $bill->id }}</p>
            <hr class="my-2">
        </div>
        <div id="print-customer" class="font-semibold text-left">
            {{ $bill->customer ? __('messages.Customer') . ': ' . $bill->customer->name : '' }}
        </div>
        <div id="print-customer-phone" class="font-semibold text-left">
            {{ $bill->customer && $bill->customer->phone ? __('messages.Phone') . ': ' . $bill->customer->phone : '' }}
        </div>

        <table class="w-full border border-gray-400 text-sm">
            <thead>
                <tr>
                    <th class="border px-2 py-1">{{ __('bills.Product') }}</th>
                    <th class="border px-2 py-1 text-right">{{ __('bills.Qty') }}</th>
                    <th class="border px-2 py-1 text-right">{{ __('bills.Unit Price') }}</th>
                    <th class="border px-2 py-1 text-right">{{ __('bills.Discount') }}</th>
                    <th class="border px-2 py-1 text-right">{{ __('bills.Total') }}</th>
                </tr>
            </thead>
            <tbody id="print-products-list">
                @foreach($bill->products as $product)
                    <tr>
                        <td class="border px-2 py-1">{{ $product->name }}</td>
                        <td class="border px-2 py-1 text-right">{{ $product->pivot->quantity }}</td>
                        <td class="border px-2 py-1 text-right">${{ number_format($product->pivot->selling_price, 2) }}</td>
                        <td class="border px-2 py-1 text-right">${{ number_format($product->pivot->discount ?? 0, 2) }}</td>
                        <td class="border px-2 py-1 text-right">${{ number_format($product->pivot->quantity * $product->pivot->selling_price - ($product->pivot->discount ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="border px-2 py-1 text-right font-bold">{{ __('bills.Totals') }}</td>
                    <td id="print-total-discount" class="border px-2 py-1 text-right">${{ number_format($bill->products->sum('pivot.discount'), 2) }}</td>
                    <td id="print-total-price" class="border px-2 py-1 text-right">${{ number_format($bill->total_price, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Roll Paper Receipt --}}
    <div id="receipt-area" class="print-hidden">
        <div class="receipt-content">
            <div class="text-center mb-2">
                <div class="text-lg font-bold">{{ $shopName }}</div>
                <div class="text-xs">{{ now()->format('Y-m-d H:i:s') }}</div>
                <div class="text-xs font-medium">Bill: #{{ $bill->id }}</div>
                <div class="border-t border-dashed my-2"></div>
            </div>
            
            <div id="receipt-customer" class="text-xs mb-2">
                {{ $bill->customer ? __('messages.Customer') . ': ' . $bill->customer->name : '' }}
            </div>
            
            <div id="receipt-products-list" class="text-xs">
                @foreach($bill->products as $product)
                    <div class="receipt-product-row">
                        <div class="receipt-product-name">{{ $product->name }}</div>
                        <div class="receipt-product-details">
                            <span>{{ $product->pivot->quantity }} x ${{ number_format($product->pivot->selling_price, 2) }}</span>
                            <span>${{ number_format($product->pivot->quantity * $product->pivot->selling_price - ($product->pivot->discount ?? 0), 2) }}</span>
                        </div>
                        @if($product->pivot->discount > 0)
                            <div class="text-center text-xs">Discount: -${{ number_format($product->pivot->discount, 2) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            
            <div class="border-t border-dashed my-2"></div>
            
        </div>
    </div>

    {{-- Enhanced Performance Styles --}}
    <style>
        /* Receipt styles for roll paper */
        #receipt-area {
            display: none;
            font-family: 'Courier New', monospace;
        }

        .receipt-content {
            width: 58mm;
            padding: 2mm;
            font-size: 8pt;
            line-height: 1.2;
        }

        .receipt-product-row {
            margin-bottom: 1mm;
        }

        .receipt-product-name {
            font-weight: bold;
        }

        .receipt-product-details {
            display: flex;
            justify-content: space-between;
            font-size: 7pt;
        }

        /* Standard print styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            body * {
                visibility: hidden !important;
                height: 0 !important;
                overflow: hidden !important;
            }

            #print-area, #print-area * {
                visibility: visible !important;
                height: auto !important;
                overflow: visible !important;
            }

            #print-area {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding: 0.5cm !important;
                background: white;
            }

            html, body {
                height: auto !important;
                overflow: visible !important;
            }

            #print-area table {
                page-break-inside: auto;
            }

            #print-area tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .product-row, .x-block, .py-12, .flex, form {
                display: none !important;
            }
        }

        /* Receipt print styles */
        @media print {
            .print-receipt #print-area {
                display: none !important;
            }

            .print-receipt #receipt-area, .print-receipt #receipt-area * {
                visibility: visible !important;
                height: auto !important;
                overflow: visible !important;
            }

            .print-receipt #receipt-area {
                display: block !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 58mm !important;
                padding: 0 !important;
                background: white;
                font-size: 8pt !important;
            }
        }

        #print-area {
            display: none;
        }

        @media print {
            #print-area {
                display: block !important;
            }
        }
    </style>

    <script>

const products = @json($products);
const barcodeInput = document.getElementById('barcode_input');
const addBarcodeBtn = document.getElementById('add_barcode_product');
const productSelect = document.getElementById('product_select');
const newQuantityInput = document.getElementById('new_quantity');
const productsTableBody = document.querySelector('#products-table tbody');
const form = document.getElementById('form');
const saveButton = document.getElementById('save');
const shopName = '{{ $shopName }}';

function formatPrice(num) {
    return parseFloat(num).toFixed(2);
}

function isProductInTable(productId) {
    return !!productsTableBody.querySelector(`tr[data-product-id="${productId}"]`);
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('#products-table tbody tr').forEach(row => {
        const totalCell = row.querySelector('.total-cell');
        if (totalCell) {
            const amount = parseFloat(totalCell.textContent.replace('$', '').replace(',', '')) || 0;
            total += amount;
        }
    });
    document.getElementById('grand-total').textContent ='$' + formatPrice(total);
}

function addProductRow(product) {
    console.log("Adding product:", product);
    if (isProductInTable(product.id)) {
        alert('{{ __('bills.Product already added to this bill') }}');
        return;
    }

    const tr = document.createElement('tr');
    tr.setAttribute('data-product-id', product.id);
    tr.className = 'hover:bg-gray-50';

    tr.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-8 w-8">
                    <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                        <svg class="h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">${product.name}</div>
                    <div class="text-sm text-gray-500">${product.barcode || '{{__('messages.No barcode')}}'}</div>
                </div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <input type="number" name="quantities[${product.id}]" value="1" min="1" 
                   class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent quantity" required>
            <input type="hidden" name="product_ids[]" value="${product.id}">
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
            ${formatPrice(product.price || product.selling_price)}
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <input type="number" name="discounts[${product.id}]" value="0" min="0" 
                   class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent discount" 
                   step="0.01" required>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 total-cell">
            ${formatPrice(product.price || product.selling_price)}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-center">
            <label class="inline-flex items-center">
                <input type="checkbox" name="remove_products[]" value="${product.id}" 
                       class="rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                <span class="ml-2 text-sm text-red-600">Remove</span>
            </label>
        </td>
    `;

    productsTableBody.appendChild(tr);
    updateGrandTotal();
    console.log("Product row added successfully");
}

function addDynamicProductsToForm() {
    // Remove any existing dynamic product inputs to avoid duplicates
    form.querySelectorAll('input[name^="dynamic_"]').forEach(input => input.remove());
    
    // Collect all products currently in the table
    document.querySelectorAll('#products-table tbody tr').forEach(function(row) {
        const productIdInput = row.querySelector('input[name="product_ids[]"]');
        const quantityInput = row.querySelector('input.quantity');
        const discountInput = row.querySelector('input.discount');
        
        if (productIdInput && quantityInput && discountInput) {
            const productId = productIdInput.value;
            
            // Add hidden inputs for this product
            const hiddenProductId = document.createElement('input');
            hiddenProductId.type = 'hidden';
            hiddenProductId.name = 'dynamic_product_ids[]';
            hiddenProductId.value = productId;
            form.appendChild(hiddenProductId);
            
            const hiddenQuantity = document.createElement('input');
            hiddenQuantity.type = 'hidden';
            hiddenQuantity.name = `dynamic_quantities[${productId}]`;
            hiddenQuantity.value = quantityInput.value;
            form.appendChild(hiddenQuantity);
            
            const hiddenDiscount = document.createElement('input');
            hiddenDiscount.type = 'hidden';
            hiddenDiscount.name = `dynamic_discounts[${productId}]`;
            hiddenDiscount.value = discountInput.value;
            form.appendChild(hiddenDiscount);
        }
    });
    
    console.log('Dynamic products added to form');
}

function handleBarcodeAdd() {
    console.log("handleBarcodeAdd called");
    const code = barcodeInput.value.trim();
    console.log("Barcode entered:", code);
    
    if (!code) {
        console.log("No barcode entered");
        alert('{{ __('bills.Please enter a barcode') }}');
        return;
    }

    console.log("Available products:", products);
    const product = products.find(p => p.barcode === code);
    console.log("Product found:", product);
    
    if (!product) {
        alert('{{ __('bills.Product not found for barcode: ') }}' + code);
        return;
    }

    addProductRow(product);
    barcodeInput.value = '';
    console.log("Barcode cleared, product should be added");
}

// Event Listeners
addBarcodeBtn.addEventListener('click', function(e) {
    e.preventDefault();
    handleBarcodeAdd();
});

barcodeInput.addEventListener('keydown', function(e) {
    console.log("Key pressed:", e.key);
    if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        console.log("Enter detected, calling handleBarcodeAdd");
        handleBarcodeAdd();
    }
});

productSelect.addEventListener('change', function() {
    const productId = this.value;
    if (!productId) return;
    
    const product = products.find(p => p.id == productId);
    if (product) {
        addProductRow(product);
        this.value = '';
        if (newQuantityInput) {
            newQuantityInput.value = '';
        }
    }
});

// Save button click handler
saveButton.addEventListener('click', function(e) {
    e.preventDefault();
    console.log('Save button clicked - preparing form submission');
    
    // Add dynamic products to form before submission
    addDynamicProductsToForm();
    
    // Submit the form
    console.log('Submitting form with dynamic products');
    form.submit();
});

// Backup: Form submit handler
form.addEventListener('submit', function(e) {
    console.log('Form submit event triggered');
    addDynamicProductsToForm();
});

// Update totals when quantities or discounts change
document.querySelector('#products-table').addEventListener('input', function(e) {
    if (e.target.classList.contains('quantity') || e.target.classList.contains('discount')) {
        const tr = e.target.closest('tr');
        const qtyInput = tr.querySelector('input.quantity');
        const discountInput = tr.querySelector('input.discount');
        
        const qty = parseInt(qtyInput ? qtyInput.value : 0) || 0;
        const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;
        
        // Get price from the price cell
        const priceCell = tr.children[2];
        const priceText = priceCell.textContent.replace('$', '').replace(',', '');
        const price = parseFloat(priceText) || 0;
        
        const totalCell = tr.querySelector('.total-cell');
        if (totalCell) {
            let total = (qty * price) - discount;
            totalCell.textContent = '$' + formatPrice(Math.max(0, total));
        }
        
        updateGrandTotal();
    }
});

// Enhanced print functionality
document.getElementById('print-button').addEventListener('click', function() {
    updatePrintAreas();
    document.body.classList.remove('print-receipt');
    window.print();
});

// Receipt print functionality
document.getElementById('print-receipt-button').addEventListener('click', function() {
    updatePrintAreas();
    document.body.classList.add('print-receipt');
    window.print();
    document.body.classList.remove('print-receipt');
});

function updatePrintAreas() {
    const printList = document.getElementById('print-products-list');
    const receiptList = document.getElementById('receipt-products-list');
    
    printList.innerHTML = '';
    receiptList.innerHTML = '';

    let totalPrice = 0;
    let totalDiscount = 0;

    document.querySelectorAll('#products-table tbody tr').forEach(function(row) {
        const nameElement = row.querySelector('.text-sm.font-medium.text-gray-900');
        const name = nameElement ? nameElement.textContent.trim() : '{{ __('messages.Unknown Product') }}';
        
        const qtyInput = row.querySelector('input.quantity');
        const quantity = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
        
        const unitPriceText = row.children[2].textContent.replace('$', '').replace(',', '');
        const unitPrice = parseFloat(unitPriceText) || 0;
        
        const discountInput = row.querySelector('input.discount');
        const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;
        
        const lineTotal = Math.max(0, (unitPrice * quantity) - discount);
        
        totalDiscount += discount;
        totalPrice += lineTotal;

        // Standard print table row
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="border px-2 py-1">${name}</td>
            <td class="border px-2 py-1 text-right">${quantity}</td>
            <td class="border px-2 py-1 text-right">${unitPrice.toFixed(2)}</td>
            <td class="border px-2 py-1 text-right">${discount.toFixed(2)}</td>
            <td class="border px-2 py-1 text-right">${lineTotal.toFixed(2)}</td>
        `;
        printList.appendChild(tr);

        // Receipt format
        const receiptDiv = document.createElement('div');
        receiptDiv.className = 'receipt-product-row';
        receiptDiv.innerHTML = `
            <div class="receipt-product-name">${name}</div>
            <div class="receipt-product-details">
                <span>${quantity} x ${unitPrice.toFixed(2)}</span>
                <span>${lineTotal.toFixed(2)}</span>
            </div>
            ${discount > 0 ? `<div class="text-center text-xs">{{__('messages.Discount')}}: -${discount.toFixed(2)}</div>` : ''}
        `;
        receiptList.appendChild(receiptDiv);
    });

    // Add receipt totals
    const receiptTotalsDiv = document.createElement('div');
    receiptTotalsDiv.innerHTML = `
        <div class="border-t border-dashed my-2"></div>
        <div class="text-center text-xs">{{__('messages.Total')}}: ${totalPrice.toFixed(2)}</div>
        ${totalDiscount > 0 ? `<div class="text-center text-xs">{{__('messages.Total Discount')}}: -${totalDiscount.toFixed(2)}</div>` : ''}
        <div class="text-center text-xs mt-3">
            <div>{{__('messages.Thank you for your business!')}}</div>
            <div class="border-t border-dashed mt-2"></div>
        </div>
    `;
    receiptList.appendChild(receiptTotalsDiv);

    // Update print totals
    document.getElementById('print-total-discount').textContent = '$' + totalDiscount.toFixed(2);
    document.getElementById('print-total-price').textContent = '$' + totalPrice.toFixed(2);

    // Update customer information for both print formats
    const customerName = '{{ $bill->customer->name ?? "" }}';
    const customerPhone = '{{ $bill->customer->phone ?? "" }}';

    document.getElementById('print-customer').textContent = customerName ? `{{ __('messages.Customer') }}: ${customerName}` : '';
    document.getElementById('print-customer-phone').textContent = customerPhone ? `{{ __('messages.Phone') }}: ${customerPhone}` : '';
    document.getElementById('receipt-customer').textContent = customerName ? `{{ __('messages.Customer') }}: ${customerName}` : '';
}

// Initialize grand total when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateGrandTotal();
    console.log("Page loaded, products available:", products.length);
});

// Initialize if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        updateGrandTotal();
    });
} else {
    updateGrandTotal();
}

    </script>
</x-app-layout>