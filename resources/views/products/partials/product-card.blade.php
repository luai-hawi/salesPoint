@php
    $images = is_array($product->pictures) ? $product->pictures : json_decode($product->pictures, true);
    $firstImage = $images[0] ?? null;
    $isOutOfStock = $product->quantity <= 0;
    $isLowStock = $product->quantity > 0 && $product->quantity <= 10;
@endphp

<div class="product-card bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden transition-all duration-200 hover:shadow-md" data-product-id="{{ $product->id }}">
    <!-- Product Image -->
    <div class="relative overflow-hidden">
        @if($firstImage)
            <img src="{{ asset('storage/' . $firstImage) }}" 
                 alt="{{ $product->name }}" 
                 class="product-image w-full h-32 object-cover">
        @else
            <div class="w-full h-32 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        @endif

        <!-- Stock Status Overlay -->
        @if($isOutOfStock)
            <div class="absolute inset-0 bg-red-500 bg-opacity-80 flex items-center justify-center">
                <span class="text-white font-bold text-sm">OUT OF STOCK</span>
            </div>
        @elseif($isLowStock)
            <div class="absolute top-1 right-1">
                <span class="bg-yellow-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                    LOW
                </span>
            </div>
        @endif

        <!-- Product ID Badge -->
        <div class="absolute top-1 left-1">
            <span class="bg-gray-800 bg-opacity-75 text-white text-xs font-mono px-1.5 py-0.5 rounded">
                #{{ $product->id }}
            </span>
        </div>
    </div>

    <!-- Product Info -->
    <div class="p-3">
        <!-- Product Name -->
        <h3 class="font-semibold text-gray-900 text-sm mb-2 line-clamp-2 min-h-[2rem]">
            {{ $product->name }}
        </h3>

        <!-- Barcode -->
        @if($product->barcode)
            <div class="mb-2 p-1.5 bg-gray-50 rounded border-l-2 border-blue-500">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                    </svg>
                    <span class="text-xs text-gray-600 font-mono">{{ $product->barcode }}</span>
                </div>
            </div>
        @endif

        <!-- Pricing -->
        <div class="grid grid-cols-2 gap-1.5 mb-2">
            <div class="text-center p-1.5 bg-green-50 rounded">
                <div class="text-xs text-green-600 font-medium">{{__('messages.Selling')}}</div>
                <div class="text-sm font-bold text-green-700">${{ number_format($product->selling_price, 2) }}</div>
            </div>
            <div class="text-center p-1.5 bg-orange-50 rounded">
                <div class="text-xs text-orange-600 font-medium">{{__('messages.Cost')}}</div>
                <div class="text-sm font-semibold text-orange-700">${{ number_format($product->cost_price, 2) }}</div>
            </div>
        </div>

        <!-- Stock Information -->
        <div class="mb-3 p-2 bg-gray-50 rounded">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-gray-700">Stock:</span>
                <span class="quantity-display text-sm font-bold {{ $isOutOfStock ? 'text-red-600' : ($isLowStock ? 'text-yellow-600' : 'text-green-600') }}">
                    {{ $product->quantity }}
                </span>
            </div>
            
            <!-- Stock Status Bar -->
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                @php
                    $maxStock = 100;
                    $stockPercentage = min(($product->quantity / $maxStock) * 100, 100);
                    $barColor = $isOutOfStock ? 'bg-red-500' : ($isLowStock ? 'bg-yellow-500' : 'bg-green-500');
                @endphp
                <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-300" style="width: {{ $stockPercentage }}%"></div>
            </div>
        </div>

        <!-- Profit Margin -->
        @php
            $profitMargin = $product->selling_price > 0 ? (($product->selling_price - $product->cost_price) / $product->selling_price) * 100 : 0;
        @endphp
        <div class="mb-3 text-center">
            <span class="text-xs text-gray-500">Margin</span>
            <div class="text-sm font-semibold {{ $profitMargin > 20 ? 'text-green-600' : ($profitMargin > 10 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ number_format($profitMargin, 1) }}%
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="px-3 pb-3 space-y-2">
        <!-- Add Stock Button -->
        <button class="add-stock-btn w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-3 rounded text-sm transition-colors flex items-center justify-center"
                data-product-id="{{ $product->id }}" 
                data-product-name="{{ $product->name }}">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            {{__('messages.Add Stock')}}
        </button>

        <!-- Action Buttons Row -->
        <div class="flex gap-1.5">
            <a href="{{ route('products.edit', $product->id) }}" 
               class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium py-1.5 px-2 rounded transition-colors flex items-center justify-center">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                {{__('messages.Edit')}}
            </a>
            
            <button class="delete-btn flex-1 bg-red-500 hover:bg-red-600 text-white text-xs font-medium py-1.5 px-2 rounded transition-colors flex items-center justify-center"
                    data-product-id="{{ $product->id }}" 
                    data-product-name="{{ $product->name }}">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                {{__('messages.Delete')}}
            </button>
        </div>
    </div>
</div>

<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>