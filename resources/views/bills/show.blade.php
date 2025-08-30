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
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
                            <p class="font-semibold text-gray-900">₪{{ number_format($bill->total_price, 2) }}</p>
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
                                                                    {{ $tagName }} (+₪{{ number_format($tagPrice, 2) }})
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
                                            <div>₪{{ number_format($product->pivot->selling_price, 2) }}</div>
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
                                                        {{ __('bills.Tags Price') }}: +₪{{ number_format($totalTagPrice, 2) }}
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
                                        ₪{{ number_format($finalPrice, 2) }}
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
                                    <option value="{{ $prod->id }}">{{ $prod->name }} (₪{{ number_format($prod->selling_price, 2) }})</option>
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
                    <p class="text-2xl font-bold text-gray-900" id="grand-total">₪{{ number_format($bill->total_price, 2) }}</p>
                </div>
            </div>
    </div>

    {{-- Enhanced Performance Styles --}}
 <style>
@media print {
    body * {
        visibility: hidden !important;
        height: 0 !important;
        overflow: hidden !important;
    }
    
    .print-content, .print-content * {
        visibility: visible !important;
        height: auto !important;
        overflow: visible !important;
    }
    
    .print-content {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        background: white;
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
                                    <div class="text-sm text-gray-500">+₪${parseFloat(tag.price).toFixed(2)}</div>
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
            return `<span class="inline-block bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs mr-1">${name} (+₪${parseFloat(price).toFixed(2)})</span>`;
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
                <div>₪${basePrice.toFixed(2)}</div>
                ${totalTagPrice > 0 ? `<div class="text-xs text-blue-600">Tags: +₪${totalTagPrice.toFixed(2)}</div>` : ''}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <input type="number" name="discounts[${uniqueKey}]" value="0" min="0" step="0.01"
                   class="w-20 px-3 py-2 border border-gray-300 rounded-md discount" required>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 total-cell">
            ₪${(basePrice + totalTagPrice).toFixed(2)}
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
            const basePriceText = priceCell.querySelector('div').textContent.replace('₪', '');
            const basePrice = parseFloat(basePriceText) || 0;
            
            let tagsPrice = 0;
            const tagsElement = priceCell.querySelector('.text-xs.text-blue-600');
            if (tagsElement) {
                const match = tagsElement.textContent.match(/\+\₪([0-9.]+)/);
                if (match) tagsPrice = parseFloat(match[1]) || 0;
            }
            
            const lineTotal = Math.max(0, (basePrice + tagsPrice) * qty - discount);
            total += lineTotal;
            
            const totalCell = row.querySelector('.total-cell');
            if (totalCell) {
                totalCell.textContent = '₪' + lineTotal.toFixed(2);
            }
        }
    });
    document.getElementById('grand-total').textContent = '₪' + total.toFixed(2);
}

// Add this debug function right before the form submission
function addDynamicProductsToForm() {
    const form = document.getElementById('form');
    if (!form) {
        console.error('Form not found in addDynamicProductsToForm');
        return;
    }

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

// Message listener for print window communication
window.addEventListener('message', async (event) => {
    if (event.data.source === 'printWindow') {
        if (event.data.action === 'saveBill' || event.data.action === 'autoSaveBill') {
            console.log('Received save request from print window');

            const saved = await saveBillBeforePrint();
            if (saved) {
                // Send confirmation back to print window
                if (window.printWindowRef && !window.printWindowRef.closed) {
                    window.printWindowRef.postMessage({ action: 'billSaved', success: true }, '*');
                }
            }
        }
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    fetchTags();
    updateGrandTotal();
});

// Notification system
function showNotification(message, type = 'info') {
    let notification = document.querySelector('.notification-toast');

    if (!notification) {
        notification = document.createElement('div');
        notification.className = 'notification-toast fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
        document.body.appendChild(notification);
    }

    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };

    notification.className = `notification-toast fixed top-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
    notification.textContent = message;

    if (notification.hideTimeout) {
        clearTimeout(notification.hideTimeout);
    }

    requestAnimationFrame(() => {
        notification.classList.remove('translate-x-full');
    });

    notification.hideTimeout = setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Function for print tab to call when done
window.submitBillForm = async function() {
    console.log('=== SUBMIT BILL FORM STARTED ===');

    // Add dynamic inputs for products
    addDynamicProductsToForm();

    const form = document.getElementById('form');
    if (!form) {
        console.error('Form not found');
        showNotification('Form not found. Please refresh the page.', 'error');
        return;
    }

    const formData = new FormData(form);

    // Add the _method field for PUT request
    formData.append('_method', 'PUT');

    // Get CSRF token safely
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (!csrfToken) {
        console.error('CSRF token not found');
        showNotification('Security token missing. Please refresh the page.', 'error');
        return;
    }

    // Show loading notification
    showNotification('Saving bill...', 'info');

    console.log('Form action:', form.action);
    console.log('CSRF token:', csrfToken ? 'Present' : 'Missing');

    try {
        console.log('Sending request...');
        const response = await fetch(form.action, {
            method: 'POST', // Always POST, Laravel handles _method
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);

        if (response.ok) {
            const result = await response.json();
            console.log('Success result:', result);
            showNotification('Bill updated successfully!', 'success');
            if (result.bill && result.bill.id) {
                currentBillId = result.bill.id;
            }
        } else {
            try {
                const errorData = await response.json();
                console.error('Error response:', errorData);
                showNotification(errorData.message || 'Failed to update bill', 'error');
            } catch (parseError) {
                console.error('Failed to parse error response:', parseError);
                const textResponse = await response.text();
                console.error('Raw error response:', textResponse);
                showNotification('Failed to update bill - server error', 'error');
            }
        }
    } catch (error) {
        console.error('Update error:', error);
        showNotification('Failed to update bill - network error', 'error');
    }

    console.log('=== SUBMIT BILL FORM COMPLETED ===');
};
// ENHANCED AUTO-SAVE PRINT SYSTEM

// Save bill immediately when print is clicked
async function saveBillBeforePrint() {
    console.log('=== SAVING BILL BEFORE PRINT ===');

    // Add dynamic inputs for products
    addDynamicProductsToForm();

    const form = document.getElementById('form');
    if (!form) {
        console.error('Form not found');
        return false;
    }

    const formData = new FormData(form);

    // Add the _method field for PUT request
    formData.append('_method', 'PUT');

    // Get CSRF token safely
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (!csrfToken) {
        console.error('CSRF token not found');
        return false;
    }

    try {
        console.log('Sending save request...');
        const response = await fetch(form.action, {
            method: 'POST', // Always POST, Laravel handles _method
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        if (response.ok) {
            const result = await response.json();
            console.log('Bill updated successfully:', result);

            // Store bill data in sessionStorage as backup
            const billData = {
                id: result.bill?.id,
                products: collectPrintData().products,
                total: collectPrintData().total,
                timestamp: Date.now()
            };
            sessionStorage.setItem('lastSavedBill', JSON.stringify(billData));

            showNotification('Bill updated successfully!', 'success');
            return true;
        } else {
            try {
                const errorData = await response.json();
                console.error('Update failed:', errorData);
                showNotification(errorData.message || 'Failed to update bill', 'error');
                return false;
            } catch (parseError) {
                console.error('Failed to parse error response:', parseError);
                const textResponse = await response.text();
                console.error('Raw error response:', textResponse);
                showNotification('Failed to update bill - server error', 'error');
                return false;
            }
        }
    } catch (error) {
        console.error('Update error:', error);
        showNotification('Failed to update bill - network error', 'error');
        return false;
    }
}

// Standard Print Button
document.getElementById('print-button').addEventListener('click', async () => {
    // Save bill first
    const saved = await saveBillBeforePrint();
    if (!saved) {
        alert('Failed to save bill. Please try again.');
        return;
    }

    const printData = collectPrintData();
    openStandardPrintTab(printData);
});

// Receipt Print Button
document.getElementById('print-receipt-button').addEventListener('click', async () => {
    // Save bill first
    const saved = await saveBillBeforePrint();
    if (!saved) {
        alert('Failed to save bill. Please try again.');
        return;
    }

    const printData = collectPrintData();
    openReceiptPrintTab(printData);
});

// Collect print data from current bill
function collectPrintData() {
    const products = [];
    let total = 0, totalDiscount = 0, subtotal = 0;

    // Get products from the bill data passed from Laravel
    @foreach($bill->products as $product)
        @php
            $basePrice = $product->pivot->selling_price;
            $tagPairs = explode('&', $product->pivot->tags ?? '');
            $totalTagPrice = 0;
            $tagsString = '';
            
            foreach($tagPairs as $tagPair) {
                if(str_contains($tagPair, '@')) {
                    [$name, $tagPrice] = explode('@', $tagPair);
                    $totalTagPrice += floatval($tagPrice);
                    $tagsString .= $tagsString ? '&' . $tagPair : $tagPair;
                }
            }
            
            $actualDiscount = $product->pivot->discount ?? 0;
            $subtotalWithTags = ($basePrice * $product->pivot->quantity) + ($totalTagPrice * $product->pivot->quantity);
            $finalSubtotal = max(0, $subtotalWithTags - $actualDiscount);
        @endphp
        
        products.push({
            name: '{{ $product->name }}',
            qty: {{ $product->pivot->quantity }},
            price: {{ $basePrice }},
            tagsTotal: {{ $totalTagPrice }},
            tagsString: '{{ $tagsString }}',
            actualDiscount: {{ $actualDiscount }},
            discountType: 'total',
            finalSubtotal: {{ $finalSubtotal }}
        });
        
        subtotal += {{ $subtotalWithTags }};
        total += {{ $finalSubtotal }};
        totalDiscount += {{ $actualDiscount }};
    @endforeach

    const userDetails = {!! json_encode(auth()->user()->details ?? "") !!}.replace(/\\n/g, '\n');
    const shopOwnerName = 
        @if(auth()->user()->role === 'employee' && auth()->user()->shop_owner_id)
            '{{ auth()->user()->shopOwner->name ?? 'Shop Owner' }}'
        @else
            '{{ auth()->user()->name ?? 'Shop Owner' }}'
        @endif
    ;

    return {
        products: products,
        subtotal: subtotal,
        totalDiscount: totalDiscount,
        total: total,
        customerName: '{{ $bill->customer->name ?? "" }}',
        customerPhone: '{{ $bill->customer->phone ?? "" }}',
        userDetails: userDetails,
        shopName: shopName,
        shopOwnerName: shopOwnerName,
        userName: '{{ auth()->user()->name }}',
        currentDate: new Date('{{ $bill->created_at->format('Y-m-d') }}').toLocaleDateString('en-GB'),
        currentTime: new Date('{{ $bill->created_at->format('Y-m-d H:i:s') }}').toLocaleTimeString('en-GB', { hour12: false }),
        currentDateTime: new Date('{{ $bill->created_at->format('Y-m-d H:i:s') }}').toLocaleString('en-GB'),
        billId: {{ $bill->id }}
    };
}

// Enhanced print functions with postMessage communication
function openStandardPrintTab(data) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
        showNotification('Please allow popups for printing', 'error');
        return;
    }

    // Store print window reference for communication
    window.printWindowRef = printWindow;

    const standardHtml = generateStandardPrintHtml(data);
    printWindow.document.write(standardHtml);
    printWindow.document.close();

    printWindow.onload = function() {
        // Add close button that won't be printed
        const closeButton = printWindow.document.createElement('button');
        closeButton.innerHTML = '💾 Save & Close';
        closeButton.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            padding: 8px 16px;
            background-color: #dc2626;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        `;

        // Hide button when printing
        const style = printWindow.document.createElement('style');
        style.textContent = '@media print { .close-btn { display: none !important; } }';
        printWindow.document.head.appendChild(style);
        closeButton.className = 'close-btn';

        closeButton.onclick = () => {
            // Send message to parent window to save
            if (window.opener) {
                window.opener.postMessage({ action: 'saveBill', source: 'printWindow' }, '*');
            }
            printWindow.close();
        };
        printWindow.document.body.appendChild(closeButton);

        // Listen for messages from parent
        window.addEventListener('message', (event) => {
            if (event.data.action === 'billSaved') {
                console.log('Bill saved confirmation received');
            }
        });

        // Periodic auto-save every 30 seconds
        const autoSaveInterval = setInterval(() => {
            if (!printWindow.closed && window.opener) {
                window.opener.postMessage({ action: 'autoSaveBill', source: 'printWindow' }, '*');
            } else {
                clearInterval(autoSaveInterval);
            }
        }, 30000);

        // Also save when window is closed by other means
        printWindow.addEventListener('beforeunload', () => {
            if (window.opener) {
                window.opener.postMessage({ action: 'saveBill', source: 'printWindow' }, '*');
                // Also notify that window is closing
                setTimeout(() => {
                    window.opener.postMessage({ action: 'windowClosed', source: 'printWindow' }, '*');
                }, 100);
            }
            clearInterval(autoSaveInterval);
        });

        // Show print dialog
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
}

function openReceiptPrintTab(data) {
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    if (!printWindow) {
        showNotification('Please allow popups for printing', 'error');
        return;
    }

    // Store print window reference for communication
    window.printWindowRef = printWindow;

    const receiptHtml = generateReceiptPrintHtml(data);
    printWindow.document.write(receiptHtml);
    printWindow.document.close();

    printWindow.onload = function() {
        // Add close button that won't be printed
        const closeButton = printWindow.document.createElement('button');
        closeButton.innerHTML = '✕ Close & Save Bill';
        closeButton.style.cssText = `
            position: fixed;
            top: 5px;
            right: 5px;
            z-index: 9999;
            padding: 4px 8px;
            background-color: #dc2626;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        `;

        // Hide button when printing
        const style = printWindow.document.createElement('style');
        style.textContent = '@media print { .close-btn { display: none !important; } }';
        printWindow.document.head.appendChild(style);
        closeButton.className = 'close-btn';

        closeButton.onclick = () => {
            // Send message to parent window to save
            if (window.opener) {
                window.opener.postMessage({ action: 'saveBill', source: 'printWindow' }, '*');
            }
            printWindow.close();
        };
        printWindow.document.body.appendChild(closeButton);

        // Listen for messages from parent
        window.addEventListener('message', (event) => {
            if (event.data.action === 'billSaved') {
                console.log('Bill saved confirmation received');
            }
        });

        // Periodic auto-save every 30 seconds
        const autoSaveInterval = setInterval(() => {
            if (!printWindow.closed && window.opener) {
                window.opener.postMessage({ action: 'autoSaveBill', source: 'printWindow' }, '*');
            } else {
                clearInterval(autoSaveInterval);
            }
        }, 30000);

        // Also save when window is closed by other means
        printWindow.addEventListener('beforeunload', () => {
            if (window.opener) {
                window.opener.postMessage({ action: 'saveBill', source: 'printWindow' }, '*');
                // Also notify that window is closing
                setTimeout(() => {
                    window.opener.postMessage({ action: 'windowClosed', source: 'printWindow' }, '*');
                }, 100);
            }
            clearInterval(autoSaveInterval);
        });

        // Show print dialog
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
}

// Generate Standard Print HTML - Updated with translations
function generateStandardPrintHtml(data) {
    const productsHtml = data.products.map(product => {
        const tagsDisplay = product.tagsString ? product.tagsString.split('&').map(tag => {
            const [name, price] = tag.split('@');
            return `${name} (+${parseFloat(price).toFixed(2)}₪)`;
        }).join(', ') : '';

        return `
            <tr>
                <td class="border-2 border-black px-2 py-1 text-center">
                    <div class="font-semibold text-xs">${product.name}</div>
                    ${tagsDisplay ? `<div class="text-xs text-blue-600">{{ __('messages.Tags') }}: ${tagsDisplay}</div>` : ''}
                </td>
                <td class="border-2 border-black px-2 py-1 text-center font-semibold">${product.qty}</td>
                <td class="border-2 border-black px-2 py-1 text-center font-semibold">
                    <div>${product.price.toFixed(2)}₪</div>
                    ${product.tagsTotal > 0 ? `<small class="text-xs">+${product.tagsTotal.toFixed(2)}₪ {{ __('messages.Tags') }}</small>` : ''}
                </td>
                <td class="border-2 border-black px-2 py-1 text-center font-semibold">
                    ${product.actualDiscount > 0 ? `
                        <div>${product.actualDiscount.toFixed(2)}₪</div>
                        <small class="text-xs">${product.discountType === 'per-unit' ? '{{ __('messages.Per Unit') }}' : '{{ __('messages.Total') }}'}</small>
                    ` : '-'}
                </td>
                <td class="border-2 border-black px-2 py-1 text-center font-semibold">${product.finalSubtotal.toFixed(2)}₪</td>
            </tr>
        `;
    }).join('');

    return `
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <title>{{ __('messages.Invoice') }} - ${data.shopName}</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                
                body {
                    font-family: 'Arial', 'Tahoma', sans-serif;
                    font-size: 16px;
                    font-weight: bold;
                    line-height: 1.4;
                    color: black;
                    background: white;
                    margin: 0;
                    padding: 10mm;
                    direction: rtl;
                    text-align: right;
                }
                
                table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin: 3mm 0 !important;
                    direction: rtl;
                }
                
                th, td {
                    border: 2px solid black !important;
                    padding: 4mm !important;
                    text-align: center !important;
                    font-weight: bold !important;
                    font-size: 14px !important;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    vertical-align: middle;
                }
                
                .text-center { text-align: center !important; }
                .text-right { text-align: right !important; }
                .text-left { text-align: left !important; }
                .font-bold { font-weight: bold !important; }
                .text-xl { font-size: 20px !important; }
                .text-lg { font-size: 18px !important; }
                .text-sm { font-size: 14px !important; }
                .text-xs { font-size: 11px !important; line-height: 1.3 !important; }
                .mb-3 { margin-bottom: 3mm !important; }
                .mb-4 { margin-bottom: 4mm !important; }
                
                .grid { display: flex; flex-wrap: wrap; gap: 4mm; margin: 3mm 0; }
                .grid-cols-2 > div { flex: 1; min-width: 45%; }
                
                tfoot tr { background-color: #f3f4f6 !important; }
                tfoot .bg-gray-100 { background-color: #e5e7eb !important; }
                tfoot .bg-gray-200 { background-color: #d1d5db !important; }
                
                small { display: block; margin-top: 2px; color: #666; font-size: 10px !important; }
                
                @media print {
                    @page { margin: 5mm; size: A4; }
                    body { font-size: 16px !important; direction: rtl !important; }
                }
            </style>
        </head>
        <body>
            <div class="text-center mb-3">
                <div class="text-xl font-bold">${data.shopOwnerName}</div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4 text-xs">
                <div class="text-right">
                    <div class="font-semibold">${data.shopName}</div>
                    <div class="font-medium">{{ __('messages.Bill number') }}: #${data.billId}</div>
                    <div>{{ __('messages.Printed by') }}: ${data.userName}</div>
                    <div>${data.currentDateTime}</div>
                    ${data.userDetails ? `<div class="mt-1">${data.userDetails.replace(/\n/g, '<br>')}</div>` : ''}
                </div>
                
                <div class="text-left">
                    ${data.customerName ? `<div class="font-semibold">{{ __('messages.Customer') }}: ${data.customerName}</div>` : ''}
                    ${data.customerPhone ? `<div>{{ __('messages.Phone') }}: ${data.customerPhone}</div>` : ''}
                </div>
            </div>
            
            <table class="w-full border-2 border-black text-xs mb-4">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Product') }}</th>
                        <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Quantity') }}</th>
                        <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Unit Price') }}</th>
                        <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Discount') }}</th>
                        <th class="border-2 border-black px-2 py-2 font-bold text-center">{{ __('messages.Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    ${productsHtml}
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50">
                        <td colspan="3" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Subtotal') }}</td>
                        <td class="border-2 border-black px-2 py-2 text-center font-bold">-</td>
                        <td class="border-2 border-black px-2 py-2 text-center font-bold">${data.subtotal.toFixed(2)}₪</td>
                    </tr>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Total Discount') }}</td>
                        <td class="border-2 border-black px-2 py-2 text-center font-bold">${data.totalDiscount.toFixed(2)}₪</td>
                        <td class="border-2 border-black px-2 py-2 text-center font-bold">-</td>
                    </tr>
                    <tr class="bg-gray-200">
                        <td colspan="4" class="border-2 border-black px-2 py-2 text-right font-bold">{{ __('messages.Final Total') }}</td>
                        <td class="border-2 border-black px-2 py-2 text-center font-bold">${data.total.toFixed(2)}₪</td>
                    </tr>
                </tfoot>
            </table>
            
            <div class="text-left">
                <div class="text-xs">HawiTech</div>
                <div class="text-xs">{{ __('messages.WhatsApp') }}: +(970) 599647713</div>
            </div>
        </body>
        </html>
    `;
}

// Generate Receipt Print HTML - Updated with larger text and removed boldness
function generateReceiptPrintHtml(data) {
    const productsHtml = data.products.map(product => {
        const tagsDisplayArabic = product.tagsString ? product.tagsString.split('&').map(tag => {
            const [name, price] = tag.split('@');
            return `${name} (+${parseFloat(price).toFixed(1)})`;
        }).join(', ') : '';

        return `
            <tr>
                <td class="border px-1 py-1 text-center text-sm">
                    <div>${product.name}</div>
                    ${tagsDisplayArabic ? `<div class="text-xs">{{__('messages.Tags')}}: ${tagsDisplayArabic}</div>` : ''}
                </td>
                <td class="border px-1 py-1 text-center text-sm">${product.qty}</td>
                <td class="border px-1 py-1 text-center text-sm">
                    <div>${product.price.toFixed(1)}</div>
                    ${product.tagsTotal > 0 ? `<div class="text-xs">+${product.tagsTotal.toFixed(1)}</div>` : ''}
                </td>
                <td class="border px-1 py-1 text-center text-sm">${product.finalSubtotal.toFixed(1)}</td>
            </tr>
        `;
    }).join('');

    return `
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <title>Receipt - ${data.shopName}</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                
                body {
                    font-family: 'Arial', 'Courier New', monospace;
                    font-size: 14px;
                    font-weight: normal;
                    line-height: 1.4;
                    color: black;
                    background: white;
                    direction: rtl;
                    margin: 0;
                    padding: 2mm;
                    width: 100%;
                    max-width: 104mm;
                    min-width: 56mm;
                }
                
                .receipt-container {
                    width: 100%;
                    max-width: 100%;
                    overflow-wrap: break-word;
                    word-wrap: break-word;
                }
                
                table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin: 1mm 0 !important;
                    table-layout: fixed !important;
                }
                
                th, td {
                    border: 1px solid black !important;
                    padding: 2mm !important;
                    text-align: center !important;
                    font-weight: normal !important;
                    font-size: 13px !important;
                    word-wrap: break-word !important;
                    overflow-wrap: break-word !important;
                    vertical-align: middle !important;
                    hyphens: auto !important;
                }
                
                th {
                    font-weight: bold !important;
                    background-color: #f5f5f5 !important;
                }
                
                /* Column widths for better text fit */
                .col-product { width: 40% !important; }
                .col-qty { width: 15% !important; }
                .col-price { width: 20% !important; }
                .col-total { width: 25% !important; }
                
                h1, h2, h3 {
                    font-size: 16px !important;
                    font-weight: bold !important;
                    margin: 2mm 0 !important;
                    text-align: center !important;
                    word-wrap: break-word !important;
                }
                
                .text-center { text-align: center !important; }
                .text-right { text-align: right !important; }
                .text-left { text-align: left !important; }
                .font-bold { font-weight: bold !important; }
                .text-lg { font-size: 15px !important; font-weight: normal !important; }
                .text-sm { font-size: 13px !important; font-weight: normal !important; }
                .text-xs { font-size: 11px !important; font-weight: normal !important; }
                .mb-1 { margin-bottom: 1mm !important; }
                .mb-2 { margin-bottom: 2mm !important; }
                .mb-3 { margin-bottom: 3mm !important; }
                .mt-2 { margin-top: 2mm !important; }
                .mt-3 { margin-top: 3mm !important; }
                .py-1 { padding: 1mm 0 !important; }
                .py-2 { padding: 2mm 0 !important; }
                .bg-gray-200 { background-color: #e5e7eb !important; }
                
                .totals-section {
                    border: 1px solid black;
                    margin: 2mm 0;
                }
                
                .totals-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 2mm 3mm;
                    border-bottom: 1px solid black;
                    font-weight: normal;
                    font-size: 13px;
                }
                
                .totals-row:last-child {
                    border-bottom: none;
                    background-color: #e5e7eb;
                    font-size: 14px;
                    font-weight: bold;
                }
                
                hr {
                    border: 1px solid black;
                    margin: 2mm 0;
                }
                
                .info-grid {
                    display: flex;
                    justify-content: space-between;
                    margin: 2mm 0;
                    flex-wrap: wrap;
                }
                
                .info-left, .info-right {
                    flex: 1;
                    min-width: 45%;
                    font-size: 12px;
                    font-weight: normal;
                }
                
                .info-left .font-bold,
                .info-right .font-bold {
                    font-weight: bold;
                }
                
                /* Responsive adjustments for very small widths */
                @media (max-width: 60mm) {
                    body { font-size: 12px; padding: 1mm; }
                    th, td { font-size: 11px !important; padding: 1mm !important; }
                    h1, h2, h3 { font-size: 14px !important; }
                    .info-grid { flex-direction: column; }
                    .info-left, .info-right { min-width: 100%; margin-bottom: 1mm; }
                    .totals-row { font-size: 11px; padding: 1.5mm 2mm; }
                    .totals-row:last-child { font-size: 12px; }
                }
                
                @media print {
                    @page { 
                        margin: 0; 
                        size: auto;
                    }
                    
                    body {
                        margin: 0 !important;
                        padding: 1mm !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                <!-- Header -->
                <div class="text-center mb-3">
                    <h1>${data.shopName}</h1>
                    <div class="text-sm">HawiTech</div>
                    <div class="text-xs">WhatsApp: +(970) 599647713</div>
                    <hr>
                </div>

                <!-- Shop Owner Name -->
                <div class="text-center mb-2">
                    <div class="text-lg bg-gray-200 py-1" style="padding: 2mm; border: 1px solid black;">
                        ${data.shopOwnerName}
                    </div>
                </div>

                <!-- Bill Info -->
                <div class="info-grid text-sm">
                    <div class="info-right">
                        <div class="font-bold">{{__('messages.Date')}}: ${data.currentDate}</div>
                        <div class="font-bold">{{__('messages.Time')}}: ${data.currentTime}</div>
                    </div>
                    <div class="info-left">
                        <div class="font-bold">{{__('messages.Bill number')}}: ${data.billId}</div>
                        ${data.customerName ? `<div class="font-bold">${data.customerName}</div>` : ''}
                    </div>
                </div>

                <!-- Creator Info -->
                <div class="mb-2">
                    <div class="font-bold text-sm">{{__('messages.Created By')}}: ${data.userName}</div>
                    ${data.userDetails ? `<div class="text-sm">${data.userDetails.replace(/\n/g, '<br>')}</div>` : ''}
                </div>

                <!-- Products Table -->
                <table>
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="col-product">{{__('messages.Product')}}</th>
                            <th class="col-qty">{{__('messages.Qty')}}</th>
                            <th class="col-price">{{__('messages.Unit Price')}}</th>
                            <th class="col-total">{{__('messages.Total')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${productsHtml}
                    </tbody>
                </table>

                <!-- Totals Section -->
                <div class="totals-section">
                    <div class="totals-row">
                        <div>{{__('messages.Subtotal')}}:</div>
                        <div>${data.subtotal.toFixed(1)}</div>
                    </div>
                    <div class="totals-row">
                        <div>{{__('messages.Total discount')}}:</div>
                        <div>${data.totalDiscount.toFixed(1)}</div>
                    </div>
                    <div class="totals-row">
                        <div>{{__('messages.Final Total')}}:</div>
                        <div>${data.total.toFixed(1)}</div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-3 text-sm">
                    <div class="mb-1">{{__('messages.Thank you for your business!')}}</div>
                    <hr>
                    <div>HawiTech</div>
                    <div>WhatsApp: +(970) 599647713</div>
                </div>
            </div>
        </body>
        </html>
    `;
}
    </script>
</x-app-layout>