@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
    
    // Get shop name based on user role
    $shopName = __('messages.Shop'); // Default fallback
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
                                @php
                                    $tags = $product->pivot->tags ?? '';
                                    $uniqueKey = $product->id . '_' . $tags;
                                @endphp
                                <tr data-product-id="{{ $product->id }}" data-unique-key="{{ $uniqueKey }}" class="hover:bg-gray-50">
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
                                                @if($product->pivot->tags)
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        <span class="font-medium">{{ __('bills.Tags') }}:</span>
                                                        @php
                                                            $tagPairs = explode('&', $product->pivot->tags);
                                                        @endphp
                                                        @foreach($tagPairs as $tagPair)
                                                            @if(str_contains($tagPair, '@'))
                                                                @php
                                                                    [$tagName, $tagPrice] = explode('@', $tagPair);
                                                                @endphp
                                                                <span class="inline-block bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs mr-1 mt-0.5">
                                                                    {{ $tagName }} (+${{ number_format($tagPrice, 2) }})
                                                                </span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" name="quantities[{{ $uniqueKey }}]" 
                                            value="{{ old("quantities.$uniqueKey", $product->pivot->quantity) }}"
                                            min="1" 
                                            class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent quantity" 
                                            required>
                                        <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium">
                                            <div>${{ number_format($product->pivot->selling_price, 2) }}</div>
                                            @if($product->pivot->tags)
                                                @php
                                                    $tagPairs = explode('&', $product->pivot->tags);
                                                    $totalTagPrice = 0;
                                                    foreach($tagPairs as $tagPair) {
                                                        if(str_contains($tagPair, '@')) {
                                                            $totalTagPrice += floatval(explode('@', $tagPair)[1]);
                                                        }
                                                    }
                                                @endphp
                                                @if($totalTagPrice > 0)
                                                    <div class="text-xs text-blue-600">
                                                        {{ __('bills.Tags Price') }}: +${{ number_format($totalTagPrice, 2) }}
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" name="discounts[{{ $uniqueKey }}]" 
                                            value="{{ old("discounts.$uniqueKey", $product->pivot->discount ?? 0) }}" 
                                            min="0" 
                                            class="w-20 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent discount" 
                                            step="0.01" 
                                            required>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 total-cell">
                                        @php
                                            $basePrice = $product->pivot->selling_price;
                                            $tagPairs = explode('&', $product->pivot->tags ?? '');
                                            $totalTagPrice = 0;
                                            foreach($tagPairs as $tagPair) {
                                                if(str_contains($tagPair, '@')) {
                                                    $totalTagPrice += floatval(explode('@', $tagPair)[1]);
                                                }
                                            }
                                            $finalPrice = ($basePrice + $totalTagPrice) * $product->pivot->quantity - ($product->pivot->discount ?? 0);
                                        @endphp
                                        ${{ number_format($finalPrice, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="remove_products[]" value="{{ $uniqueKey }}" 
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
<div id="print-area" class="print-hidden p-4 text-xs">
    <!-- Shop Owner Name at Top Center -->
    <div class="text-center mb-3">
        <div class="text-xl font-bold">
            @php
                $shopOwnerName = '';
                if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id) {
                    $shopOwnerName = auth()->user()->shopOwner->name ?? 'Shop Owner';
                } elseif (auth()->user()->role !== 'employee') {
                    $shopOwnerName = auth()->user()->name ?? 'Shop Owner';
                }
            @endphp
            {{ $shopOwnerName }}
        </div>
    </div>

    <!-- Header Information Grid -->
    <div class="grid grid-cols-2 gap-4 mb-4 text-xs">
        <!-- Left Side Info -->
        <div class="text-left">
            <div class="font-semibold">{{ $shopName }}</div>
            <div class="font-medium">{{ __('messages.Bill ID') }}: #{{ $bill->id }}</div>
            <div>{{ __('messages.Printed by') }}: {{ auth()->user()->name }}</div>
            <div>{{ $bill->created_at->format('Y-m-d H:i:s') }}</div>
            <div class="mt-1">{{ auth()->user()->details ?? "" }}</div>
        </div>
        
        <!-- Right Side Info -->
        <div class="text-right">
            <div class="font-semibold">
                {{ $bill->customer ? __('messages.Customer') . ': ' . $bill->customer->name : '' }}
            </div>
            <div>
                {{ $bill->customer && $bill->customer->phone ? __('messages.Phone') . ': ' . $bill->customer->phone : '' }}
            </div>
        </div>
    </div>
    
    <!-- Products Table - Full Width -->
    <table class="w-full border-2 border-black text-xs mb-4" style="border-collapse: collapse;">
        <thead>
            <tr class="bg-gray-100">
                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Product') }}</th>
                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Qty') }}</th>
                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Unit Price') }}</th>
                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Discount') }}</th>
                <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Total') }}</th>
            </tr>
        </thead>
        <tbody id="print-products-list">
            @foreach($bill->products as $product)
                @php
                    $basePrice = $product->pivot->selling_price;
                    $tagPairs = explode('&', $product->pivot->tags ?? '');
                    $totalTagPrice = 0;
                    $tagsDisplay = '';
                    
                    foreach($tagPairs as $tagPair) {
                        if(str_contains($tagPair, '@')) {
                            [$name, $tagPrice] = explode('@', $tagPair);
                            $totalTagPrice += floatval($tagPrice);
                            $tagsDisplay .= $tagsDisplay ? ', ' . $name . ' (+$' . number_format($tagPrice, 2) . ')' : $name . ' (+$' . number_format($tagPrice, 2) . ')';
                        }
                    }
                    
                    $finalPrice = ($basePrice + $totalTagPrice) * $product->pivot->quantity - ($product->pivot->discount ?? 0);
                @endphp
                <tr>
                    <td class="border-2 border-black px-2 py-1 text-center">
                        <div class="font-semibold text-xs">{{ $product->name }}</div>
                        @if($tagsDisplay)
                            <div class="text-xs text-blue-600">{{__('messages.Tags')}}: {{ $tagsDisplay }}</div>
                        @endif
                    </td>
                    <td class="border-2 border-black px-2 py-1 text-center font-semibold">{{ $product->pivot->quantity }}</td>
                    <td class="border-2 border-black px-2 py-1 text-center font-semibold">
                        ${{ number_format($basePrice, 2) }}
                        @if($totalTagPrice > 0)
                            <br><small class="text-xs">+${{ number_format($totalTagPrice, 2) }}</small>
                        @endif
                    </td>
                    <td class="border-2 border-black px-2 py-1 text-center font-semibold">${{ number_format($product->pivot->discount ?? 0, 2) }}</td>
                    <td class="border-2 border-black px-2 py-1 text-center font-semibold">${{ number_format($finalPrice, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-gray-50">
                <td colspan="3" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Totals') }}</td>
                <td class="border-2 border-black px-2 py-2 text-center font-bold">${{ number_format($bill->products->sum('pivot.discount'), 2) }}</td>
                <td class="border-2 border-black px-2 py-2 text-center font-bold">${{ number_format($bill->total_price, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    
    <!-- Footer -->
    <div class="text-left">
        <div class="text-xs">HawiTech</div>
        <div class="text-xs">WhatsApp: +(970) 599647713</div>
    </div>
</div>

    {{-- Professional Receipt Print --}}
<div id="receipt-area" class="print-hidden">
    <div class="receipt-content">
        <!-- Header with Shop Info -->
        <div class="text-center mb-6">
            <div class="mb-3">
                <h1 class="text-2xl font-bold">{{ $shopName }}</h1>
                <p class="text-sm font-bold">HawiTech</p>
                <p class="text-xs">WhatsApp: +(970) 599647713</p>
            </div>
            <hr class="border-2 border-black my-3">
        </div>

        <!-- Shop Owner Name Prominently Displayed -->
        <div class="text-center mb-4">
            <div class="text-lg font-bold bg-gray-200 py-2 px-4 rounded">
                @php
                    $shopOwnerName = '';
                    if (auth()->user()->role === 'employee' && auth()->user()->shop_owner_id) {
                        $shopOwnerName = auth()->user()->shopOwner->name ?? 'Shop Owner';
                    } elseif (auth()->user()->role !== 'employee') {
                        $shopOwnerName = auth()->user()->name ?? 'Shop Owner';
                    }
                @endphp
                {{ $shopOwnerName }}
            </div>
        </div>

        <!-- Bill Info Section -->
        <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
            <div>
                <div class="font-bold">{{__('messages.Date')}}: {{ $bill->created_at->format('d-m-Y') }}</div>
                <div class="font-bold">{{__('messages.Time')}}: {{ $bill->created_at->format('H:i:s') }}</div>
            </div>
            <div class="text-right">
                <div class="font-bold">{{__('messages.Bill number')}}: {{ $bill->id }}</div>
                <div class="font-bold">
                    {{ $bill->customer ? $bill->customer->name : '' }}
                </div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="mb-4">
            <div class="font-bold text-sm">{{__('messages.Created By')}}: {{ $bill->creator->name }}</div>
            <div class="text-xs">{{ auth()->user()->details ?? "" }}</div>
        </div>

        <!-- Products Table -->
        <div class="w-full overflow-x-auto">
            <table class="w-full border-2 border-black mb-4" style="border-collapse: collapse;">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border-2 border-black px-2 py-2 text-center font-bold text-sm">#</th>
                        <th class="border-2 border-black px-2 py-2 text-center font-bold text-sm">اسم الطبق</th>
                        <th class="border-2 border-black px-2 py-2 text-center font-bold text-sm">الكمية</th>
                        <th class="border-2 border-black px-2 py-2 text-center font-bold text-sm">السعر</th>
                        <th class="border-2 border-black px-2 py-2 text-center font-bold text-sm">الخصم</th>
                        <th class="border-2 border-black px-2 py-2 text-center font-bold text-sm">المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bill->products as $index => $product)
                        @php
                            $basePrice = $product->pivot->selling_price;
                            $tagPairs = explode('&', $product->pivot->tags ?? '');
                            $totalTagPrice = 0;
                            $tagsDisplayArabic = '';
                            
                            foreach($tagPairs as $tagPair) {
                                if(str_contains($tagPair, '@')) {
                                    [$name, $tagPrice] = explode('@', $tagPair);
                                    $totalTagPrice += floatval($tagPrice);
                                    $tagsDisplayArabic .= $tagsDisplayArabic ? '، ' . $name . ' (+' . number_format($tagPrice, 1) . ')' : $name . ' (+' . number_format($tagPrice, 1) . ')';
                                }
                            }
                            
                            $finalPrice = ($basePrice + $totalTagPrice) * $product->pivot->quantity - ($product->pivot->discount ?? 0);
                        @endphp
                        <tr>
                            <td class="border-2 border-black px-2 py-2 text-center font-bold">{{ $index + 1 }}</td>
                            <td class="border-2 border-black px-2 py-2 text-center font-bold">
                                <div>{{ $product->name }}</div>
                                @if($tagsDisplayArabic)
                                    <div class="text-xs">إضافات: {{ $tagsDisplayArabic }}</div>
                                @endif
                            </td>
                            <td class="border-2 border-black px-2 py-2 text-center font-bold">{{ $product->pivot->quantity }}</td>
                            <td class="border-2 border-black px-2 py-2 text-center font-bold">
                                <div>{{ number_format($basePrice, 1) }}</div>
                                @if($totalTagPrice > 0)
                                    <div class="text-xs">+{{ number_format($totalTagPrice, 1) }} إضافات</div>
                                @endif
                            </td>
                            <td class="border-2 border-black px-2 py-2 text-center font-bold">
                                {{ ($product->pivot->discount ?? 0) > 0 ? number_format($product->pivot->discount, 1) : '-' }}
                            </td>
                            <td class="border-2 border-black px-2 py-2 text-center font-bold">{{ number_format($finalPrice, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Detailed Totals Section -->
        <div class="border-2 border-black mb-4">
            @php
                $subtotal = 0;
                $totalDiscount = 0;
                foreach($bill->products as $product) {
                    $basePrice = $product->pivot->selling_price;
                    $tagPairs = explode('&', $product->pivot->tags ?? '');
                    $totalTagPrice = 0;
                    foreach($tagPairs as $tagPair) {
                        if(str_contains($tagPair, '@')) {
                            $totalTagPrice += floatval(explode('@', $tagPair)[1]);
                        }
                    }
                    $subtotal += ($basePrice + $totalTagPrice) * $product->pivot->quantity;
                    $totalDiscount += $product->pivot->discount ?? 0;
                }
            @endphp
            <!-- Subtotal -->
            <div class="grid grid-cols-2 text-center font-bold text-base border-b-2 border-black">
                <div class="border-r-2 border-black py-2">{{__('messages.Subtotal')}}:</div>
                <div class="py-2">{{ number_format($subtotal, 1) }}</div>
            </div>
            <!-- Total Discount -->
            <div class="grid grid-cols-2 text-center font-bold text-base border-b-2 border-black">
                <div class="border-r-2 border-black py-2">{{__('messages.Total discount')}}:</div>
                <div class="py-2">{{ number_format($totalDiscount, 1) }}</div>
            </div>
            <!-- Final Total -->
            <div class="grid grid-cols-2 text-center font-bold text-lg bg-gray-200">
                <div class="border-r-2 border-black py-3">{{__('messages.Total')}}:</div>
                <div class="py-3">{{ number_format($bill->total_price, 1) }}</div>
            </div>
        </div>

        <!-- User Details -->
        <div class="mt-4 text-center">
            <div class="text-xs font-bold">{{ auth()->user()->details ?? "" }}</div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-sm font-bold">
            <div class="mb-2">{{__('messages.Thank you for your business!')}}</div>
            <hr class="border-2 border-black my-3">
            <div class="text-xs">HawiTech</div>
            <p class="text-xs">WhatsApp: +(970) 599647713</p>
        </div>
    </div>
</div>
    {{-- Enhanced Performance Styles --}}
    <style>
        /* Receipt styles for roll paper */

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
        /* Professional Receipt Print Styles */
.receipt-content {
    width: 100% !important;
    max-width: 100% !important;
    padding: 20px !important;
    font-size: 14pt !important;
    line-height: 1.4 !important;
    font-weight: bold !important;
    font-family: Arial, sans-serif !important;
    color: black !important;
    background: white !important;
    margin: 0 auto !important;
}

#receipt-area {
    display: none;
    font-family: Arial, sans-serif;
}

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
        right: 0 !important;
        width: 100% !important;
        height: auto !important;
        padding: 0 !important;
        margin: 0 !important;
        background: white !important;
        font-size: 14pt !important;
        font-weight: bold !important;
    }

    .print-receipt .receipt-content {
        font-weight: bold !important;
        width: 100% !important;
        height: auto !important;
        max-width: none !important;
        padding: 20px !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }

    .print-receipt table {
        border-collapse: collapse !important;
        width: 100% !important;
        table-layout: auto !important;
    }

    .print-receipt th, .print-receipt td {
        border: 2px solid black !important;
        padding: 8px !important;
        font-weight: bold !important;
        word-wrap: break-word !important;
    }

    /* Make table columns responsive */
    .print-receipt th:nth-child(1), .print-receipt td:nth-child(1) { width: 8%; } /* # */
    .print-receipt th:nth-child(2), .print-receipt td:nth-child(2) { width: 35%; } /* Product name */
    .print-receipt th:nth-child(3), .print-receipt td:nth-child(3) { width: 12%; } /* Quantity */
    .print-receipt th:nth-child(4), .print-receipt td:nth-child(4) { width: 15%; } /* Price */
    .print-receipt th:nth-child(5), .print-receipt td:nth-child(5) { width: 15%; } /* Discount */
    .print-receipt th:nth-child(6), .print-receipt td:nth-child(6) { width: 15%; } /* Total */

    .print-receipt .grid {
        display: grid !important;
    }

    .print-receipt .grid-cols-2 {
        grid-template-columns: 1fr 1fr !important;
        gap: 20px !important;
    }

    .print-receipt .border-r-2 {
        border-right: 2px solid black !important;
    }

    .print-receipt .border-b-2 {
        border-bottom: 2px solid black !important;
    }
}

.receipt-product-row {
    margin-bottom: 2mm !important;
    font-weight: bold !important;
}

.receipt-product-name {
    font-weight: bold !important;
    font-size: 11pt !important;
}

.receipt-product-details {
    display: flex;
    justify-content: space-between;
    font-size: 10pt !important;
    font-weight: bold !important;
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

let availableTags = [];

async function fetchTags() {
    try {
        const response = await fetch('/api/tags');
        if (response.ok) {
            availableTags = await response.json();
        }
    } catch (error) {
        console.error('Failed to fetch tags:', error);
    }
}

function showTagsDialog(product) {
    const modal = document.createElement('div');
    modal.id = 'tags-modal';
    modal.className = 'fixed inset-0 z-50 overflow-y-auto';
    modal.innerHTML = `
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="modal-overlay fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">{{__('messages.Select Tags for')}} ${product.name}</h3>
                    <div id="tags-list" class="space-y-2 max-h-60 overflow-y-auto">
                        ${availableTags.map(tag => `
                            <label class="flex items-center p-2 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" value="${tag.id}" data-name="${tag.name}" data-price="${tag.price}" class="tag-checkbox mr-3">
                                <div class="flex-1">
                                    <div class="font-medium">${tag.name}</div>
                                    <div class="text-sm text-gray-500">+$${parseFloat(tag.price).toFixed(2)}</div>
                                </div>
                            </label>
                        `).join('')}
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="confirm-tags" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded mr-2">{{__('messages.Add to Bill')}}</button>
                    <button type="button" id="cancel-tags" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">{{__('messages.Cancel')}}</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('confirm-tags').addEventListener('click', () => {
        const selectedTags = [];
        const checkboxes = modal.querySelectorAll('.tag-checkbox:checked');
        checkboxes.forEach(cb => {
            selectedTags.push(`${cb.dataset.name}@${cb.dataset.price}`);
        });
        const tagsString = selectedTags.join('&');
        addProductToTable(product, tagsString);
        document.body.removeChild(modal);
    });
    
    document.getElementById('cancel-tags').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    modal.querySelector('.modal-overlay').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
}

function generateUniqueKey(productId, tags) {
    return `${productId}_${tags || ''}`;
}

function isProductWithTagsExists(productId, tags) {
    const uniqueKey = generateUniqueKey(productId, tags);
    return !!document.querySelector(`tr[data-unique-key="${uniqueKey}"]`);
}

function addProductToTable(product, tagsString = '') {
    // Check if exact combination exists
    if (isProductWithTagsExists(product.id, tagsString)) {
        alert('{{__('messages.This product with the same tags is already in the bill')}}');
        return;
    }

    const uniqueKey = generateUniqueKey(product.id, tagsString);
    const basePrice = parseFloat(product.price || product.selling_price);
    
    // Calculate tags price
    let totalTagPrice = 0;
    let tagsDisplay = '';
    if (tagsString) {
        const tagPairs = tagsString.split('&');
        tagPairs.forEach(pair => {
            if (pair.includes('@')) {
                const [name, price] = pair.split('@');
                totalTagPrice += parseFloat(price) || 0;
            }
        });
        tagsDisplay = tagPairs.map(tag => {
            const [name, price] = tag.split('@');
            return `<span class="inline-block bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs mr-1">${name} (+$${parseFloat(price).toFixed(2)})</span>`;
        }).join('');
    }

    const tr = document.createElement('tr');
    tr.setAttribute('data-product-id', product.id);
    tr.setAttribute('data-unique-key', uniqueKey);
    tr.className = 'hover:bg-gray-50';

    tr.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">${product.name}</div>
                    <div class="text-sm text-gray-500">${product.barcode || '{{__('messages.No barcode')}}'}</div>
                    ${tagsString ? `<div class="text-xs text-blue-600 mt-1"><span class="font-medium">{{__('messages.Tags')}}:</span> ${tagsDisplay}</div>` : ''}
                </div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <input type="number" name="quantities[${uniqueKey}]" value="1" min="1"
                   class="w-20 px-3 py-2 border border-gray-300 rounded-md quantity" required>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900 font-medium">
                <div>$${basePrice.toFixed(2)}</div>
                ${totalTagPrice > 0 ? `<div class="text-xs text-blue-600">Tags: +$${totalTagPrice.toFixed(2)}</div>` : ''}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <input type="number" name="discounts[${uniqueKey}]" value="0" min="0" step="0.01"
                   class="w-20 px-3 py-2 border border-gray-300 rounded-md discount" required>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 total-cell">
            $${(basePrice + totalTagPrice).toFixed(2)}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-center">
            <label class="inline-flex items-center">
                <input type="checkbox" name="remove_products[]" value="${uniqueKey}" 
                       class="rounded border-gray-300 text-red-600">
                <span class="ml-2 text-sm text-red-600">{{__('messages.Remove')}}</span>
            </label>
        </td>
    `;

    productsTableBody.appendChild(tr);
    updateGrandTotal();
}

function addProductRow(product) {
    if (product.has_tags && availableTags.length > 0) {
        showTagsDialog(product);
    } else {
        addProductToTable(product, '');
    }
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('#products-table tbody tr').forEach(row => {
        const qtyInput = row.querySelector('.quantity');
        const discountInput = row.querySelector('.discount');
        
        if (qtyInput && discountInput) {
            const qty = parseInt(qtyInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            
            const priceCell = row.children[2];
            const basePriceText = priceCell.querySelector('div').textContent.replace('$', '');
            const basePrice = parseFloat(basePriceText) || 0;
            
            let tagsPrice = 0;
            const tagsElement = priceCell.querySelector('.text-xs.text-blue-600');
            if (tagsElement) {
                const match = tagsElement.textContent.match(/\+\$([0-9.]+)/);
                if (match) tagsPrice = parseFloat(match[1]) || 0;
            }
            
            const lineTotal = Math.max(0, (basePrice + tagsPrice) * qty - discount);
            total += lineTotal;
            
            const totalCell = row.querySelector('.total-cell');
            if (totalCell) {
                totalCell.textContent = '$' + lineTotal.toFixed(2);
            }
        }
    });
    document.getElementById('grand-total').textContent = '$' + total.toFixed(2);
}

// Add this debug function right before the form submission
function addDynamicProductsToForm() {
    // Clear existing dynamic inputs
    form.querySelectorAll('input[name^="dynamic_"]').forEach(input => input.remove());
    
    console.log('=== FORM SUBMISSION DEBUG ===');
    
    // Check what remove checkboxes are checked
    const removeCheckboxes = document.querySelectorAll('input[name="remove_products[]"]:checked');
    console.log('Remove checkboxes checked:', removeCheckboxes.length);
    removeCheckboxes.forEach(cb => {
        console.log('- Remove value:', cb.value);
    });
    
    // Add current table products as dynamic inputs
    document.querySelectorAll('#products-table tbody tr').forEach(row => {
        const uniqueKey = row.getAttribute('data-unique-key');
        const productId = row.getAttribute('data-product-id');
        const qty = row.querySelector('.quantity')?.value;
        const discount = row.querySelector('.discount')?.value;
        
        console.log('Processing table row:', {
            uniqueKey,
            productId,
            qty,
            discount
        });
        
        // Extract tags from uniqueKey (everything after the first underscore)
        const tags = uniqueKey.includes('_') ? uniqueKey.split('_').slice(1).join('_') : '';
        
        // Create hidden inputs
        ['product_ids', 'quantities', 'discounts', 'product_tags'].forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `dynamic_${field}[${uniqueKey}]`;
            
            switch(field) {
                case 'product_ids': input.value = productId; break;
                case 'quantities': input.value = qty; break;
                case 'discounts': input.value = discount; break;
                case 'product_tags': input.value = tags; break;
            }
            
            console.log('Adding hidden input:', input.name, '=', input.value);
            form.appendChild(input);
        });
    });
    
    // Log all form data that will be sent
    const formData = new FormData(form);
    console.log('=== FINAL FORM DATA ===');
    for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
    }
    console.log('=== END DEBUG ===');
}

// Event Listeners
addBarcodeBtn.addEventListener('click', () => {
    const code = barcodeInput.value.trim();
    if (!code) return alert('Please enter a barcode');
    
    const product = products.find(p => p.barcode === code);
    if (!product) return alert('Product not found');
    
    product.has_tags = product.has_tags === true || product.has_tags === 1 || product.has_tags === '1';
    addProductRow(product);
    barcodeInput.value = '';
});

barcodeInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addBarcodeBtn.click();
    }
});

productSelect.addEventListener('change', function() {
    const productId = this.value;
    if (!productId) return;
    
    const product = products.find(p => p.id == productId);
    if (product) {
        product.has_tags = product.has_tags === true || product.has_tags === 1 || product.has_tags === '1';
        addProductRow(product);
        this.value = '';
        if (newQuantityInput) newQuantityInput.value = '';
    }
});

saveButton.addEventListener('click', (e) => {
    e.preventDefault();
    addDynamicProductsToForm();
    form.submit();
});

document.querySelector('#products-table').addEventListener('input', (e) => {
    if (e.target.classList.contains('quantity') || e.target.classList.contains('discount')) {
        updateGrandTotal();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    fetchTags();
    updateGrandTotal();
});
// Enhanced mobile device detection
function isMobileDevice() {
    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    const mobileRegex = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i;
    const isMobileUA = mobileRegex.test(userAgent.toLowerCase());
    const isMobileScreen = window.innerWidth <= 768 || window.innerHeight <= 600;
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    return isMobileUA || (isMobileScreen && isTouchDevice);
}

// Standard Print Button - Enhanced for mobile
document.getElementById('print-button').addEventListener('click', () => {
    if (isMobileDevice()) {
        // For mobile: Open in new tab
        openBillPrintInNewTab(false); // false = standard print
    } else {
        // For desktop: Use existing method
        document.body.classList.remove('print-receipt');
        window.print();
    }
});

// Receipt Print Button - Enhanced for mobile
document.getElementById('print-receipt-button').addEventListener('click', () => {
    if (isMobileDevice()) {
        // For mobile: Use simplified receipt print
        printBillReceiptForMobile();
    } else {
        // For desktop: Use existing method
        document.body.classList.add('print-receipt');
        window.print();
        document.body.classList.remove('print-receipt');
    }
});

    // Function to open bill print content in new tab
    function openBillPrintInNewTab(isReceipt = false) {
        try {
            let htmlContent;

            if (isReceipt) {
                // Get receipt content
                const receiptArea = document.getElementById('receipt-area');
                if (!receiptArea) {
                    alert('Receipt template not found');
                    return;
                }

                // Temporarily show to get content
                const originalDisplay = receiptArea.style.display;
                receiptArea.style.display = 'block';
                const receiptContent = receiptArea.innerHTML;
                receiptArea.style.display = originalDisplay;

                htmlContent = generateBillReceiptPageHTML(receiptContent);
            } else {
                // Get standard print content
                const printArea = document.getElementById('print-area');
                if (!printArea) {
                    alert('Print template not found');
                    return;
                }

                // Temporarily show to get content
                const originalDisplay = printArea.style.display;
                printArea.style.display = 'block';
                const printContent = printArea.innerHTML;
                printArea.style.display = originalDisplay;

                htmlContent = generateBillStandardPageHTML(printContent);
            }

            // Open in new tab
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                alert('Please allow popups for printing');
                return;
            }

            printWindow.document.write(htmlContent);
            printWindow.document.close();

            // Auto-print after a short delay
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                    // Don't auto-close on mobile to avoid issues
                }, 500);
            };

        } catch (error) {
            console.error('Print error:', error);
            alert('Print failed. Please try again.');
        }
    }

    // Simplified mobile receipt print function for bills
    function printBillReceiptForMobile() {
        try {
            // Get receipt content
            const receiptArea = document.getElementById('receipt-area');
            if (!receiptArea) {
                alert('Receipt template not found');
                return;
            }

            // Temporarily show to get content
            const originalDisplay = receiptArea.style.display;
            receiptArea.style.display = 'block';
            const receiptContent = receiptArea.innerHTML;
            receiptArea.style.display = originalDisplay;

            // Generate simplified mobile-friendly receipt HTML
            const htmlContent = generateBillMobileReceiptHTML(receiptContent);

            // Open in new tab
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                alert('Please allow popups for printing');
                return;
            }

            printWindow.document.write(htmlContent);
            printWindow.document.close();

            // Auto-print after a short delay
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                    // Don't auto-close on mobile to avoid issues
                }, 500);
            };

        } catch (error) {
            console.error('Mobile receipt print error:', error);
            alert('Print failed. Please try again.');
        }
    }

    // Generate simplified mobile-friendly receipt HTML for bills
    function generateBillMobileReceiptHTML(content) {
        return `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <title>Receipt - {{ $bill->id }}</title>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    * {
                        box-sizing: border-box;
                        margin: 0;
                        padding: 0;
                    }

                    body {
                        font-family: Arial, sans-serif;
                        font-size: 14px;
                        font-weight: bold;
                        line-height: 1.4;
                        color: black;
                        background: white;
                        padding: 15mm;
                        direction: rtl;
                    }

                    .receipt-container {
                        max-width: 210mm;
                        margin: 0 auto;
                    }

                    table {
                        width: 100% !important;
                        border-collapse: collapse !important;
                        margin: 8mm 0 !important;
                        font-size: 12px !important;
                    }

                    th, td {
                        border: 1px solid black !important;
                        padding: 4mm 2mm !important;
                        text-align: center !important;
                        font-weight: bold !important;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                    }

                    h1, h2, h3 {
                        font-size: 16px !important;
                        font-weight: bold !important;
                        margin: 4mm 0 !important;
                        text-align: center !important;
                    }

                    .text-center { text-align: center !important; }
                    .text-left { text-align: left !important; }
                    .text-right { text-align: right !important; }
                    .font-bold { font-weight: bold !important; }
                    .text-sm { font-size: 11px !important; }
                    .text-xs { font-size: 10px !important; }
                    .mb-2 { margin-bottom: 2mm !important; }
                    .mb-4 { margin-bottom: 4mm !important; }
                    .mb-6 { margin-bottom: 6mm !important; }
                    .mt-4 { margin-top: 4mm !important; }
                    .mt-6 { margin-top: 6mm !important; }

                    .grid {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 4mm;
                        margin: 4mm 0;
                    }

                    .grid-cols-2 > div {
                        flex: 1;
                        min-width: 45%;
                    }

                    .border-r-2 {
                        border-right: 1px solid black !important;
                    }

                    .border-b-2 {
                        border-bottom: 1px solid black !important;
                    }

                    hr {
                        border: 1px solid black;
                        margin: 3mm 0;
                    }

                    @media print {
                        body {
                            margin: 0 !important;
                            padding: 10mm !important;
                            font-size: 12px !important;
                        }

                        .receipt-container {
                            max-width: none !important;
                        }

                        table {
                            page-break-inside: avoid;
                            font-size: 11px !important;
                        }

                        th, td {
                            padding: 3mm 2mm !important;
                            font-size: 11px !important;
                        }

                        .no-print {
                            display: none !important;
                        }
                    }

                    /* Mobile-specific adjustments */
                    @media (max-width: 768px) {
                        body {
                            font-size: 16px;
                            padding: 10mm;
                        }

                        table {
                            font-size: 14px;
                        }

                        th, td {
                            padding: 3mm;
                            font-size: 14px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="receipt-container">
                    ${content}

                    <!-- Print button for mobile users -->
                    <div class="no-print" style="text-align: center; margin-top: 15mm;">
                        <button onclick="window.print(); return false;"
                                style="background: #4CAF50; color: white; padding: 12px 24px;
                                       border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 5px;">
                            🖨️ Print Receipt
                        </button>
                        <br><br>
                        <button onclick="window.close(); return false;"
                                style="background: #f44336; color: white; padding: 10px 20px;
                                       border: none; border-radius: 5px; font-size: 14px; cursor: pointer;">
                            ❌ Close
                        </button>
                    </div>
                </div>
            </body>
            </html>
        `;
    }

    // Generate HTML for bill standard print
    function generateBillStandardPageHTML(content) {
        return `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Bill #{{ $bill->id }}</title>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    * {
                        box-sizing: border-box;
                        margin: 0;
                        padding: 0;
                    }

                    body {
                        font-family: Arial, sans-serif;
                        font-size: 14px;
                        font-weight: bold;
                        line-height: 1.4;
                        color: black;
                        background: white;
                        padding: 10mm;
                    }

                    table {
                        width: 100% !important;
                        border-collapse: collapse !important;
                        margin: 5mm 0 !important;
                    }

                    th, td {
                        border: 2px solid black !important;
                        padding: 3mm !important;
                        text-align: center !important;
                        font-weight: bold !important;
                        font-size: 12px !important;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                    }

                    h1, h2, h3 {
                        font-weight: bold !important;
                        margin: 3mm 0 !important;
                    }

                    .grid {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 5mm;
                        margin: 3mm 0;
                    }

                    .grid-cols-2 > div {
                        flex: 1;
                        min-width: 45%;
                    }

                    .text-center { text-align: center !important; }
                    .text-left { text-align: left !important; }
                    .text-right { text-align: right !important; }
                    .font-semibold, .font-bold { font-weight: bold !important; }
                    .text-xl { font-size: 18px !important; }
                    .text-lg { font-size: 16px !important; }
                    .text-sm { font-size: 12px !important; }
                    .text-xs { font-size: 10px !important; }
                    .mb-3 { margin-bottom: 3mm !important; }
                    .mb-4 { margin-bottom: 4mm !important; }

                    @media print {
                        body {
                            margin: 0 !important;
                            padding: 5mm !important;
                            font-size: 12px !important;
                        }

                        table {
                            page-break-inside: avoid;
                            font-size: 11px !important;
                        }

                        th, td {
                            padding: 2mm !important;
                            font-size: 11px !important;
                        }

                        .no-print {
                            display: none !important;
                        }
                    }

                    /* Mobile adjustments */
                    @media (max-width: 768px) {
                        body {
                            font-size: 16px;
                            padding: 5mm;
                        }

                        table {
                            font-size: 14px;
                        }

                        th, td {
                            padding: 2mm;
                            font-size: 14px;
                        }
                    }
                </style>
            </head>
            <body>
                ${content}

                <!-- Print button for mobile users -->
                <div class="no-print" style="text-align: center; margin-top: 10mm;">
                    <button onclick="window.print(); return false;"
                            style="background: #2196F3; color: white; padding: 10px 20px;
                                   border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
                        🖨️ Print Bill
                    </button>
                    <br><br>
                    <button onclick="window.close(); return false;"
                            style="background: #f44336; color: white; padding: 8px 16px;
                                   border: none; border-radius: 5px; font-size: 14px; cursor: pointer;">
                        ❌ Close
                    </button>
                </div>
            </body>
            </html>
        `;
    }

    // Generate HTML for bill receipt print (thermal paper style)
    function generateBillReceiptPageHTML(content) {
        return `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <title>Receipt - Bill #{{ $bill->id }}</title>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    * {
                        box-sizing: border-box;
                        margin: 0;
                        padding: 0;
                    }

                    body {
                        font-family: 'Courier New', monospace;
                        font-size: 12px;
                        font-weight: bold;
                        line-height: 1.3;
                        color: black;
                        background: white;
                        direction: rtl;
                        padding: 5mm;
                    }

                    .receipt-content {
                        width: 100%;
                        max-width: 80mm;
                        margin: 0 auto;
                    }

                    table {
                        width: 100% !important;
                        border-collapse: collapse !important;
                        margin: 2mm 0 !important;
                        font-size: 10px !important;
                    }

                    th, td {
                        border: 1px solid black !important;
                        padding: 1mm !important;
                        text-align: center !important;
                        font-weight: bold !important;
                        font-size: 9px !important;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                    }

                    h1, h2, h3 {
                        font-size: 12px !important;
                        font-weight: bold !important;
                        margin: 2mm 0 !important;
                        text-align: center !important;
                    }

                    .text-center { text-align: center !important; }
                    .text-right { text-align: right !important; }
                    .text-left { text-align: left !important; }
                    .font-bold { font-weight: bold !important; }
                    .text-sm { font-size: 10px !important; }
                    .text-xs { font-size: 8px !important; }
                    .mb-2 { margin-bottom: 2mm !important; }
                    .mb-4 { margin-bottom: 4mm !important; }
                    .mb-6 { margin-bottom: 6mm !important; }
                    .mt-4 { margin-top: 4mm !important; }
                    .mt-6 { margin-top: 6mm !important; }

                    .grid {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 2mm;
                        margin: 2mm 0;
                    }

                    .grid-cols-2 > div {
                        flex: 1;
                        min-width: 45%;
                    }

                    .border-r-2 {
                        border-right: 1px solid black !important;
                    }

                    .border-b-2 {
                        border-bottom: 1px solid black !important;
                    }

                    hr {
                        border: 1px solid black;
                        margin: 2mm 0;
                    }

                    /* Print-specific styles for thermal paper */
                    @media print {
                        body {
                            margin: 0 !important;
                            padding: 2mm !important;
                            font-size: 10px !important;
                        }

                        .receipt-content {
                            width: 76mm !important;
                            max-width: 76mm !important;
                        }

                        table {
                            page-break-inside: avoid;
                            font-size: 9px !important;
                        }

                        th, td {
                            padding: 1mm !important;
                            font-size: 9px !important;
                        }

                        .no-print {
                            display: none !important;
                        }
                    }

                    /* Mobile adjustments */
                    @media (max-width: 768px) {
                        body {
                            font-size: 14px;
                        }

                        table {
                            font-size: 12px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="receipt-content">
                    ${content}

                    <!-- Print button for mobile users -->
                    <div class="no-print" style="text-align: center; margin-top: 10mm;">
                        <button onclick="window.print(); return false;"
                                style="background: #4CAF50; color: white; padding: 10px 20px;
                                       border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
                            🖨️ Print Receipt
                        </button>
                        <br><br>
                        <button onclick="window.close(); return false;"
                                style="background: #f44336; color: white; padding: 8px 16px;
                                       border: none; border-radius: 5px; font-size: 14px; cursor: pointer;">
                            ❌ Close
                        </button>
                    </div>
                </div>
            </body>
            </html>
        `;
    }

    </script>
</x-app-layout>