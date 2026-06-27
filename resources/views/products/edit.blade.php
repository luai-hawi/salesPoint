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
                <svg class="w-8 h-8 mr-3 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                    </path>
                </svg>
                {{ __('messages.Edit Product: ') . $product->name }}
            </h2>
            <a href="{{ route('products.index') }}" class="text-gray-600 hover:text-gray-800 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('messages.Back to Products') }}
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
                                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                {{ __('messages.Product Information') }}
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">{{ __('messages.Product Management') }}</p>
                        </div>

                        <form action="{{ route('products.update', $product->id) }}" method="POST"
                            enctype="multipart/form-data" class="p-6" id="product-edit-form">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Product Name -->
                                <div class="md:col-span-2">
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Product Name') }}</label>
                                    <input type="text" name="name" value="{{ old('name', $product->name) }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors"
                                        required>
                                </div>

                                <!-- Product Variants Section -->
                                @if ($product->variant_group_id)
                                    <div class="md:col-span-2">
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex items-center">
                                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01">
                                                        </path>
                                                    </svg>
                                                    <h4 class="text-sm font-semibold text-blue-800">
                                                        {{ __('messages.Product Variants') }}</h4>
                                                </div>
                                                <button type="button" id="toggle-add-variants-btn"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg transition-colors flex items-center text-xs">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                    {{ __('messages.Add More Variants') }}
                                                </button>
                                            </div>
                                            <p class="text-xs text-blue-600 mb-3">
                                                {{ __('messages.This product is part of a variant group. Click on any variant below to edit it.') }}
                                            </p>
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                @php
                                                    $allVariants = \App\Models\Product::where(
                                                        'variant_group_id',
                                                        $product->variant_group_id,
                                                    )
                                                        ->orderBy('variant_name')
                                                        ->get();
                                                @endphp
                                                @foreach ($allVariants as $variant)
                                                    @if ($variant->id == $product->id)
                                                        <span
                                                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-600 text-white">
                                                            {{ $variant->variant_name }}
                                                            ({{ __('messages.Current') }})
                                                        </span>
                                                    @else
                                                        <a href="{{ route('products.edit', $variant->id) }}"
                                                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white text-blue-600 border border-blue-300 hover:bg-blue-100 transition-colors">
                                                            {{ $variant->variant_name }}
                                                            <svg class="w-3 h-3 ml-1" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                            </svg>
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Category') }}</label>
                                    <input type="text" name="category"
                                        value="{{ old('category', $product->category) }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors"
                                        placeholder="{{ __('messages.Enter product category (optional)...') }}"
                                        list="category-suggestions">
                                    <datalist id="category-suggestions">
                                        <!-- This will be populated with existing categories -->
                                    </datalist>
                                    <small
                                        class="text-gray-500">{{ __('messages.Optional - helps organize products by category.') }}</small>
                                </div>

                                <!-- Barcode -->
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Product Code / Barcode') }}</label>
                                    <div class="flex">
                                        <div class="relative flex-1">
                                            <input type="text" name="barcode" id="barcode"
                                                value="{{ old('barcode', $product->barcode) }}"
                                                class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors">
                                            <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z">
                                                </path>
                                            </svg>
                                            <!-- Camera Scanner Icon -->
                                            <button type="button" id="scan-barcode-btn"
                                                class="absolute right-3 top-3.5 h-5 w-5 text-gray-400 hover:text-yellow-500 transition-colors cursor-pointer"
                                                title="{{ __('messages.Scan with camera') }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <button type="button" id="generate-barcode"
                                            class="ml-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-colors flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            {{ __('messages.Generate') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Additional Barcodes -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('messages.Additional Barcodes') }}
                                    </label>
                                    <div id="additional-barcodes-container" class="h-40 overflow-y-auto">
                                        @foreach ($product->barcodes as $barcode)
                                            <div class="flex items-center mb-2">
                                                <input type="text" name="additional_barcodes[]"
                                                    value="{{ $barcode->barcode }}"
                                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 mr-2"
                                                    placeholder="{{ __('messages.Enter barcode') }}">
                                                <button type="button"
                                                    class="remove-barcode-btn bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button" id="add-barcode-btn"
                                        class="mt-2 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center text-sm">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        {{ __('messages.Add Barcode') }}
                                    </button>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ __('messages.Optional - add multiple barcodes for this product.') }}</p>
                                </div>

                                <!-- Current Quantity (Read-only) -->
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Current Stock') }}</label>
                                    <div class="relative">
                                        <input type="number" value="{{ $product->quantity }}" readonly
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                                </path>
                                            </svg>
                                        </div>
                                    </div>
                                    <small
                                        class="text-gray-500">{{ __('messages.Managed automatically through batches') }}</small>
                                </div>

                                <!-- Low Stock Threshold -->
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Low Stock Warning Threshold') }}</label>
                                    <div class="relative">
                                        <input type="number" name="low_stock_threshold"
                                            value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 10) }}"
                                            class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors"
                                            min="1" required>
                                        <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4v2m0 0v2m0 0a9 9 0 1118 0 9 9 0 01-18 0z">
                                            </path>
                                        </svg>
                                    </div>
                                    <small
                                        class="text-gray-500">{{ __('messages.When stock reaches this number or below, a low stock badge will appear.') }}</small>
                                </div>

                                <!-- Cost Price -->
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Average Cost Price') }}</label>
                                    <div class="relative">
                                        <input type="number" name="cost_price"
                                            value="{{ old('cost_price', $product->cost_price) }}"
                                            class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors"
                                            step="0.01" required>
                                        <span class="absolute left-3 top-3.5 text-gray-400">₪</span>
                                    </div>
                                </div>

                                <!-- Selling Price -->
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Selling Price (per unit)') }}</label>
                                    <div class="relative">
                                        <input type="number" name="selling_price" id="selling_price"
                                            value="{{ old('selling_price', $product->selling_price) }}"
                                            class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors"
                                            step="0.01" required>
                                        <span class="absolute left-3 top-3.5 text-gray-400">₪</span>
                                    </div>
                                </div>
                                <!-- Has Tags Checkbox -->
                                <div class="mb-6">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="has_tags" id="has_tags"
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                            value="1"
                                            {{ old('has_tags', $product->has_tags ?? false) ? 'checked' : '' }}>
                                        <label for="has_tags" class="ml-2 block text-sm font-medium text-gray-700">
                                            {{ __('messages.This product has customizable tags') }}
                                        </label>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ __('messages.Check this if customers can add extra options/tags to this product') }}
                                    </p>
                                </div>

                                <!-- Has IMEIs Toggle -->
                                <div class="mb-6 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                            </svg>
                                            <label for="has_imeis" class="text-sm font-semibold text-gray-700">
                                                {{ __('messages.This product has IMEI / Serial codes') }}
                                            </label>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="has_imeis" id="has_imeis"
                                                class="sr-only peer" value="1"
                                                {{ old('has_imeis', $product->has_imeis ?? false) ? 'checked' : '' }}>
                                            <div
                                                class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                            </div>
                                        </label>
                                    </div>
                                    <p class="text-xs text-indigo-600 mt-2">
                                        {{ __('messages.Enable this for products like phones, laptops, etc. that have unique IMEI or serial numbers per unit.') }}
                                    </p>
                                </div>

                                <!-- Product Pictures -->
                                <div class="md:col-span-2">
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2">{{ __('messages.Product Pictures') }}</label>
                                    <div
                                        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                                        <div class="space-y-2 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor"
                                                fill="none" viewBox="0 0 48 48">
                                                <path
                                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                    stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="pictures"
                                                    class="relative cursor-pointer bg-white rounded-md font-medium text-yellow-600 hover:text-yellow-500">
                                                    <span>{{ __('messages.Upload new images') }}</span>
                                                    <input id="pictures" name="pictures[]" type="file"
                                                        class="sr-only" multiple accept="image/*">
                                                </label>
                                                <p class="pl-1">{{ __('messages.or drag and drop') }}</p>
                                            </div>
                                            <p class="text-xs text-gray-500">
                                                {{ __('messages.PNG, JPG, GIF up to 2MB each') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end">
                                <button type="button" id="print-barcode"
                                    class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center mr-4">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                                        </path>
                                    </svg>
                                    {{ __('messages.Print Barcode') }}
                                </button>
                                <button type="submit"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-3 px-6 rounded-lg transition-colors flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('messages.Update Product') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Add More Variants Card (Separate Form) -->
                    @if ($product->variant_group_id)
                        <div id="add-variants-card" style="display: none;"
                            class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-6">
                            <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('messages.Add More Variants') }}
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ __('messages.Add additional variants to this product group') }}</p>
                            </div>
                            <form action="{{ route('products.addVariants', $product->id) }}" method="POST"
                                class="p-6" id="add-variants-form-element">
                                @csrf
                                <div class="bg-white rounded-lg mb-3">
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="text-sm font-semibold text-gray-700">
                                            {{ __('messages.New Variants') }}</h5>
                                        <button type="button" id="add-new-variant-btn"
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg text-sm flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            {{ __('messages.Add Variant') }}
                                        </button>
                                    </div>
                                    <div id="new-variants-container" class="space-y-3 max-h-96 overflow-y-auto">
                                        <!-- New variant inputs will be added here -->
                                    </div>
                                </div>
                                <div class="flex gap-2 pt-4 border-t border-gray-200">
                                    <button type="submit"
                                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors font-medium">
                                        {{ __('messages.Save New Variants') }}
                                    </button>
                                    <button type="button" id="cancel-add-variants-btn"
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors font-medium">
                                        {{ __('messages.Cancel') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <!-- Right Column - Batch Management -->
                <div class="space-y-6">

                    @if ($product->has_imeis)
                        <!-- IMEI Management Panel -->
                        <div class="bg-white rounded-xl shadow-sm border border-indigo-200 overflow-hidden"
                            id="imei-panel">
                            <div
                                class="p-4 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50 flex items-center justify-between">
                                <h3 class="text-base font-semibold text-indigo-800 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                    </svg>
                                    {{ __('messages.IMEI / Serial Codes') }}
                                </h3>
                                <div class="flex gap-2">
                                    <select id="imei-filter"
                                        class="text-xs border border-indigo-300 rounded px-2 py-1">
                                        <option value="all">{{ __('messages.All') }}</option>
                                        <option value="unsold">{{ __('messages.Unsold') }}</option>
                                        <option value="sold">{{ __('messages.Sold') }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="grid grid-cols-3 divide-x divide-indigo-100 border-b border-indigo-100">
                                <div class="p-3 text-center">
                                    <div class="text-lg font-bold text-gray-800" id="imei-total-count">—</div>
                                    <div class="text-xs text-gray-500">{{ __('messages.Total') }}</div>
                                </div>
                                <div class="p-3 text-center">
                                    <div class="text-lg font-bold text-green-600" id="imei-unsold-count">—</div>
                                    <div class="text-xs text-gray-500">{{ __('messages.Unsold') }}</div>
                                </div>
                                <div class="p-3 text-center">
                                    <div class="text-lg font-bold text-red-500" id="imei-sold-count">—</div>
                                    <div class="text-xs text-gray-500">{{ __('messages.Sold') }}</div>
                                </div>
                            </div>

                            <!-- IMEI List -->
                            <div class="max-h-64 overflow-y-auto" id="imei-list-container">
                                <div class="p-4 text-center text-gray-400 text-sm" id="imei-loading">
                                    {{ __('messages.Loading...') }}</div>
                            </div>

                            <!-- Add IMEI Section -->
                            <div class="p-4 border-t border-indigo-100 bg-indigo-50">
                                <p class="text-xs font-semibold text-indigo-700 mb-2">
                                    {{ __('messages.Add IMEI Codes') }}</p>
                                <div class="flex gap-2 mb-2">
                                    <select id="new-imei-supplier"
                                        class="flex-1 text-sm border border-indigo-300 rounded px-2 py-1.5">
                                        <option value="">{{ __('messages.No Supplier') }}</option>
                                        @foreach (\App\Models\Supplier::where('user_id', auth()->user()->role === 'employee' ? auth()->user()->shop_owner_id : auth()->id())->orderBy('name')->get() as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" id="new-imei-date"
                                        class="text-sm border border-indigo-300 rounded px-2 py-1.5"
                                        value="{{ date('Y-m-d') }}">
                                </div>
                                <div id="new-imeis-list" class="max-h-32 overflow-y-auto mb-2 space-y-1">
                                    <!-- New IMEI inputs appear here -->
                                </div>
                                <div class="flex gap-2">
                                    <input type="text" id="new-imei-input"
                                        class="flex-1 text-sm border border-indigo-300 rounded px-2 py-1.5 font-mono"
                                        placeholder="{{ __('messages.Enter IMEI or serial...') }}">
                                    <button type="button" id="add-imei-to-list"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-sm">+</button>
                                </div>
                                <button type="button" id="save-imeis-btn"
                                    class="mt-2 w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm py-2 rounded-lg font-medium">
                                    {{ __('messages.Save IMEI Codes') }}
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Product Stats Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                </path>
                            </svg>
                            {{ __('messages.Quick Stats') }}
                        </h3>

                        <div class="space-y-4">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-700">{{ __('messages.Total Stock:') }}</span>
                                    <span class="font-bold text-green-800"
                                        id="total-stock">{{ $product->quantity }}</span>
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-700">{{ __('messages.Profit Margin:') }}</span>
                                    @php
                                        $margin =
                                            $product->selling_price > 0
                                                ? (($product->selling_price - $product->cost_price) /
                                                        $product->selling_price) *
                                                    100
                                                : 0;
                                    @endphp
                                    <span class="font-bold text-blue-800">{{ number_format($margin, 1) }}%</span>
                                </div>
                            </div>

                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-purple-700">{{ __('messages.Batches:') }}</span>
                                    <span class="font-bold text-purple-800"
                                        id="batch-count">{{ $product->batches->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add New Batch -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('messages.Add New Batch') }}
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Quantity') }}</label>
                                <input type="number" id="new-batch-qty" min="0.01" step="0.01"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                    placeholder="{{ __('messages.Enter quantity') }}" min="1">
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.Cost Price per Unit') }}</label>
                                <div class="relative">
                                    <input type="number" id="new-batch-cost" step="0.01"
                                        class="w-full px-3 py-2 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                        placeholder="{{ __('messages.Enter cost price') }}" min="0">
                                    <span class="absolute left-3 top-2.5 text-gray-400">₪</span>
                                </div>
                            </div>

                            <button type="button" id="add-batch"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('messages.Add Batch') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Batch Management Section -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                            </path>
                        </svg>
                        {{ __('messages.Product Batches') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('messages.Manage individual stock batches with different cost prices.') }}</p>
                </div>

                <div id="batches-container">
                    @if ($product->batches->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Batch ID') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Quantity') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Cost Price (per unit)') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Total Value') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Created') }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('messages.Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($product->batches as $batch)
                                        <tr data-id="{{ $batch->id }}" class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    #{{ $batch->id }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="number"
                                                    class="batch-qty w-20 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                    min="0.01" step="0.01" value="{{ $batch->quantity }}"
                                                    min="0">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="relative">
                                                    <input type="number" step="0.01"
                                                        class="batch-cost w-24 px-2 py-1 pl-6 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        value="{{ $batch->cost_price }}" min="0">
                                                    <span
                                                        class="absolute left-2 top-1.5 text-gray-400 text-sm">₪</span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 total-value">
                                                ₪{{ number_format($batch->quantity * $batch->cost_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $batch->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button type="button"
                                                        class="save-batch bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                                        {{ __('messages.Save') }}
                                                    </button>
                                                    <button type="button"
                                                        class="delete-batch bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors">
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
                        <div class="p-12 text-center">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                </path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('messages.No batches yet') }}
                            </h3>
                            <p class="mt-2 text-gray-500">
                                {{ __('messages.Get started by adding your first batch above.') }}</p>
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
            notification.className =
                `fixed top-4 right-4 z-50 p-4 rounded-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Barcode verification for main product edit form
        const productEditForm = document.getElementById('product-edit-form');
        if (productEditForm) {
            productEditForm.addEventListener('submit', async function(e) {
                if (productEditForm.dataset.barcodeConfirmed === '1') {
                    return;
                }

                const mainBarcode = document.getElementById('barcode').value.trim();
                const additionalInputs = document.querySelectorAll('input[name="additional_barcodes[]"]');
                const additionalBarcodes = Array.from(additionalInputs)
                    .map(input => input.value.trim())
                    .filter(Boolean);

                const barcodesToCheck = [mainBarcode, ...additionalBarcodes].filter(Boolean);

                if (barcodesToCheck.length) {
                    e.preventDefault();
                    try {
                        const response = await fetch('{{ route('products.check-barcodes') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                barcode: mainBarcode,
                                additional_barcodes: additionalBarcodes,
                                ignore_product_id: {{ $product->id }}
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Failed to check barcodes');
                        }

                        const data = await response.json();
                        const duplicates = Array.isArray(data.duplicates) ? data.duplicates : [];

                        if (duplicates.length) {
                            const lines = duplicates.map(item => {
                                const productNames = (item.products || [])
                                    .map(product => {
                                        const label = product.source === 'main' ?
                                            '{{ __('messages.Main barcode') }}' :
                                            '{{ __('messages.Additional barcode') }}';
                                        return product.name ? `${product.name} (${label})` : label;
                                    })
                                    .filter(Boolean)
                                    .join(', ');
                                return `${item.barcode}: ${productNames}`;
                            });

                            const message =
                                '{{ __('messages.Barcode already exists. Do you want to continue?') }}' +
                                '\n\n' + lines.join('\n');

                            if (confirm(message)) {
                                productEditForm.dataset.barcodeConfirmed = '1';
                                productEditForm.submit();
                            }
                        } else {
                            productEditForm.dataset.barcodeConfirmed = '1';
                            productEditForm.submit();
                        }
                    } catch (error) {
                        console.error('Error checking barcodes:', error);
                        if (confirm('{{ __('messages.Could not verify barcodes. Continue anyway?') }}')) {
                            productEditForm.dataset.barcodeConfirmed = '1';
                            productEditForm.submit();
                        }
                    }
                }
            });
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
        document.getElementById('add-batch').addEventListener('click', function() {
            const qtyInput = document.getElementById('new-batch-qty');
            const costInput = document.getElementById('new-batch-cost');
            const qty = parseFloat(qtyInput.value);
            const cost = parseFloat(costInput.value);

            if (!qty || qty <= 0) {
                showNotification('{{ __('messages.Please enter a valid quantity') }}', 'error');
                return;
            }

            if (!cost || cost < 0) {
                showNotification('{{ __('messages.Please enter a valid cost price') }}', 'error');
                return;
            }

            // Disable button and show loading
            this.disabled = true;
            this.innerHTML =
                '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>{{ __('messages.Adding...') }}';

            // Check for existing batch with same cost price
            let updatedExisting = false;
            const existingRows = document.querySelectorAll('tbody tr[data-id]');

            for (const row of existingRows) {
                const batchCostInput = row.querySelector('.batch-cost');
                const batchQtyInput = row.querySelector('.batch-qty');

                if (batchCostInput && parseFloat(batchCostInput.value) === cost) {
                    const currentQty = parseFloat(batchQtyInput.value);
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
                            showNotification('{{ __('messages.Batch added successfully!') }}');
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
                        showNotification('{{ __('messages.Failed to add batch. Please try again.') }}',
                            'error');
                    })
                    .finally(() => {
                        // Reset button
                        this.disabled = false;
                        this.innerHTML =
                            '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>{{ __('messages.Add Batch') }}';
                    });
            } else {
                // Reset button if we updated existing
                this.disabled = false;
                this.innerHTML =
                    '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>{{ __('messages.Add Batch') }}';

                // Clear inputs
                qtyInput.value = '';
                costInput.value = '';

                showNotification('{{ __('messages.Existing batch updated with new quantity!') }}');
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
                    showNotification('{{ __('messages.Please enter a valid quantity') }}', 'error');
                    return;
                }

                if (!cost || cost < 0) {
                    showNotification('{{ __('messages.Please enter a valid cost price') }}', 'error');
                    return;
                }

                // Add loading state
                const originalText = btn.textContent;
                btn.textContent = '{{ __('messages.Saving...') }}';
                btn.disabled = true;

                fetch(`/batches/${id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            quantity: parseFloat(qty),
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

                            showNotification('{{ __('messages.Batch updated successfully!') }}');
                        } else {
                            throw new Error('Server returned success: false');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('{{ __('messages.Failed to save batch. Please try again.') }}',
                            'error');
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
                if (!confirm('{{ __('messages.Are you sure you want to delete this batch?') }}')) return;

                const btn = e.target;
                const row = btn.closest('tr');
                const id = row.dataset.id;

                // Add loading state
                const originalText = btn.textContent;
                btn.textContent = '{{ __('messages.Deleting...') }}';
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
                            showNotification('{{ __('messages.Batch deleted successfully!') }}');

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
                        showNotification('{{ __('messages.Failed to delete batch. Please try again.') }}',
                            'error');
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

                reader.onload = function(e) {
                    img.src = e.target.result;
                };

                img.onload = function() {
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
        document.getElementById("pictures").addEventListener("change", async function(e) {
            await handleCompressedUpload(e.target);
        });




        // Auto-save on Enter key for batch fields only
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                // Prevent form submission for batch fields
                if (e.target.classList.contains('batch-qty') || e.target.classList.contains('batch-cost')) {
                    const saveBtn = e.target.closest('tr').querySelector('.save-batch');
                    if (saveBtn) {
                        saveBtn.click();
                        e.preventDefault(); // Prevent form submission
                    }
                }
            }
        });

        loadCategoryAutoComplete();

        // Additional barcodes functionality
        function addBarcodeInput(value = '') {
            const container = document.getElementById('additional-barcodes-container');
            const div = document.createElement('div');
            div.className = 'flex items-center mb-2';
            div.innerHTML = `
                <div class="relative flex-1">
                    <input type="text" name="additional_barcodes[]" value="${value}"
                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 mr-2"
                           placeholder="{{ __('messages.Enter barcode') }}">
                    <button type="button" class="scan-additional-barcode-btn absolute right-2 top-2.5 h-5 w-5 text-gray-400 hover:text-yellow-500 transition-colors cursor-pointer"
                            title="{{ __('messages.Scan with camera') }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </div>
                <button type="button" class="remove-barcode-btn bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;
            container.appendChild(div);

            // Add event listener to remove button
            div.querySelector('.remove-barcode-btn').addEventListener('click', function() {
                div.remove();
            });

            // Add event listener to barcode input for Enter key
            const barcodeInput = div.querySelector('input[name="additional_barcodes[]"]');
            barcodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addBarcodeInput(); // Add new barcode field instead of submitting
                }
            });

            // Add event listener for blur to check for duplicates
            barcodeInput.addEventListener('blur', function() {
                checkForDuplicateBarcodes();
            });

            // Add event listener to scan button
            div.querySelector('.scan-additional-barcode-btn').addEventListener('click', function() {
                const input = div.querySelector('input[name="additional_barcodes[]"]');
                initBarcodeScannerForAdditionalBarcode(input);
            });
        }

        // Scanner function for additional barcodes
        function initBarcodeScannerForAdditionalBarcode(inputElement) {
            if (document.getElementById('barcode-scanner-modal')) {
                return;
            }

            const scannerModal = document.createElement('div');
            scannerModal.id = 'barcode-scanner-modal';
            scannerModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90';
            scannerModal.innerHTML = `
                <div class="bg-white rounded-lg p-4 w-full max-w-lg mx-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">{{ __('messages.Scan Barcode') }}</h3>
                        <button type="button" id="close-scanner" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="scanner-container" class="relative bg-black rounded-lg overflow-hidden" style="height: 350px;"></div>
                    <p class="text-sm text-gray-500 mt-2 text-center">{{ __('messages.Point camera at barcode') }}</p>
                </div>
            `;
            document.body.appendChild(scannerModal);

            let hasScanned = false;
            let html5Qrcode = null;

            try {
                const scannerContainer = document.getElementById('scanner-container');
                const videoElement = document.createElement('video');
                videoElement.style.width = '100%';
                videoElement.style.height = '100%';
                videoElement.style.objectFit = 'cover';
                videoElement.setAttribute('playsinline', 'true');
                scannerContainer.appendChild(videoElement);

                html5Qrcode = new Html5Qrcode("scanner-container");

                html5Qrcode.start({
                        facingMode: "environment"
                    }, {
                        fps: 10,
                        qrbox: {
                            width: 250,
                            height: 150
                        },
                        aspectRatio: 1.0
                    },
                    (decodedText, decodedResult) => {
                        if (hasScanned) return;
                        const code = decodedText.trim();
                        if (code.length < 4) return;
                        hasScanned = true;
                        html5Qrcode.stop().then(() => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }).catch(err => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        });
                    },
                    (errorMessage) => {}
                ).catch(err => {
                    console.error('Camera start error:', err);
                    alert('{{ __('messages.Camera access denied or not available') }}');
                    scannerModal.remove();
                });
            } catch (err) {
                console.error('HTML5 QR Code scanner error:', err);
                alert('{{ __('messages.Camera access denied or not available') }}');
                scannerModal.remove();
            }

            document.getElementById('close-scanner').addEventListener('click', function() {
                if (html5Qrcode) {
                    html5Qrcode.stop().then(() => scannerModal.remove()).catch(() => scannerModal.remove());
                } else {
                    scannerModal.remove();
                }
            });

            scannerModal.addEventListener('click', function(e) {
                if (e.target === scannerModal) {
                    if (html5Qrcode) {
                        html5Qrcode.stop().then(() => scannerModal.remove()).catch(() => scannerModal.remove());
                    } else {
                        scannerModal.remove();
                    }
                }
            });
        }

        // Generate barcode
        document.getElementById('generate-barcode').addEventListener('click', function() {
            const barcode = '150702' + {{ $product->id }};
            document.getElementById('barcode').value = barcode;
        });

        // Add barcode button
        document.getElementById('add-barcode-btn').addEventListener('click', function() {
            addBarcodeInput();
        });

        // Add event listeners to existing remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-barcode-btn') || e.target.closest('.remove-barcode-btn')) {
                const button = e.target.classList.contains('remove-barcode-btn') ? e.target : e.target.closest(
                    '.remove-barcode-btn');
                button.closest('.flex').remove();
            }
        });

        // Add this to your existing JavaScript in create.blade.php and edit.blade.php

        // Function to check for duplicate barcodes
        function checkForDuplicateBarcodes() {
            const barcodeInputs = document.querySelectorAll('input[name="additional_barcodes[]"]');
            const barcodes = [];
            let hasDuplicate = false;

            // Collect all barcodes
            barcodeInputs.forEach(input => {
                const barcode = input.value.trim();
                if (barcode) {
                    if (barcodes.includes(barcode)) {
                        hasDuplicate = true;
                        // Highlight duplicate input
                        input.classList.add('border-red-500', 'border-2');
                    } else {
                        barcodes.push(barcode);
                        input.classList.remove('border-red-500', 'border-2');
                    }
                }
            });

            if (hasDuplicate) {
                showNotification('{{ __('messages.Duplicate barcode detected! Please use unique barcodes.') }}', 'error');
            }

            return !hasDuplicate;
        }

        // Add validation before form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const hasDuplicates = !checkForDuplicateBarcodes();
            if (hasDuplicates) {
                e.preventDefault();
                e.stopImmediatePropagation();
                showNotification('{{ __('messages.Cannot save product with duplicate barcodes.') }}', 'error');
                return false;
            }
            return true;
        });

        // Fetch existing categories for autocomplete
        async function loadCategoryAutoComplete() {
            try {
                const response = await fetch('/api/categories');
                if (response.ok) {
                    const categories = await response.json();
                    const datalist = document.getElementById('category-suggestions');

                    datalist.innerHTML = '';
                    categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category;
                        datalist.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load categories:', error);
            }
        }

        // Print barcode function
        document.getElementById('print-barcode').addEventListener('click', function() {
            const barcode = document.getElementById('barcode').value.trim();
            const price = document.getElementById('selling_price').value.trim();

            if (!barcode) {
                alert('{{ __('messages.Please enter a barcode first.') }}');
                return;
            }

            // Open new window/tab for printing
            const printWindow = window.open('', '_blank', 'width=400,height=200');

            // Write the barcode content to the new window
            printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>{{ __('messages.Barcode Print') }}</title>
                <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
    <style>
        body {
            margin: 0;
            padding: 10px;
            text-align: center;
            font-family: Arial, sans-serif;
            background: white;
        }

        .barcode-container {
            display: inline-block;
            text-align: center;
            border: 1px solid #ccc;
            padding: 10px;
            background: #f9f9f9;
        }

        .barcode-svg {
            width: 300px;
            height: 80px;
            display: block;
            margin: 0 auto 5px auto;
        }

        .price-text {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            text-align: center;
        }

        @media print {
            body {
                margin: 0;
                padding: 2mm;
                background: white !important;
            }

            .barcode-container {
                border: none !important;
                padding: 0 !important;
                background: white !important;
                margin: 0 !important;
            }

            .barcode-svg {
                width: 70mm !important;
                height: 20mm !important;
                margin-bottom: 2mm !important;
            }

            .price-text {
                font-size: 12pt !important;
                margin-top: 0 !important;
            }

            button {
                display: none !important;
            }
        }
    </style>
    </head>

    <body>
        <div class="barcode-container">
            <canvas id="print-barcode" class="barcode-svg"></canvas>
            <div class="price-text">₪${price || '0.00'}</div>
        </div>
        <button onclick="window.print(); setTimeout(() => window.close(), 1000);" style="margin-top: 10px; padding: 5px 10px;">Print Barcode</button>
        <script>
            window.addEventListener('load', function() {
                if (typeof JsBarcode === 'undefined') {
                    console.error('JsBarcode failed to load');
                    return;
                }
                JsBarcode('#print-barcode', '${barcode}', {
                    format: 'CODE128',
                    width: 2,
                    height: 60,
                    displayValue: true,
                    fontSize: 12,
                    margin: 0,
                    textAlign: 'center'
                });
            });
        <\/script>
    </body>

    </html>
    `);
            printWindow.document.close();
        });

        // Add Variants Functionality
        @if ($product->variant_group_id)
            const toggleAddVariantsBtn = document.getElementById('toggle-add-variants-btn');
            const addVariantsCard = document.getElementById('add-variants-card');
            const cancelAddVariantsBtn = document.getElementById('cancel-add-variants-btn');
            const addNewVariantBtn = document.getElementById('add-new-variant-btn');
            const newVariantsContainer = document.getElementById('new-variants-container');
            let newVariantCounter = 0;

            if (toggleAddVariantsBtn) {
                toggleAddVariantsBtn.addEventListener('click', function() {
                    addVariantsCard.style.display = 'block';
                    toggleAddVariantsBtn.style.display = 'none';
                    // Scroll to the card
                    addVariantsCard.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                    // Add initial variant input
                    if (newVariantsContainer.children.length === 0) {
                        addNewVariantInput();
                    }
                });
            }

            if (cancelAddVariantsBtn) {
                cancelAddVariantsBtn.addEventListener('click', function() {
                    addVariantsCard.style.display = 'none';
                    toggleAddVariantsBtn.style.display = 'flex';
                    newVariantsContainer.innerHTML = '';
                    newVariantCounter = 0;
                });
            }

            if (addNewVariantBtn) {
                addNewVariantBtn.addEventListener('click', function() {
                    addNewVariantInput();
                });
            }

            function addNewVariantInput(name = '', quantity = '', barcode = '') {
                newVariantCounter++;
                const div = document.createElement('div');
                div.className = 'bg-gray-50 border border-gray-200 rounded-lg p-3';
                div.innerHTML = `
                <div class="flex items-start gap-2">
                    <div class="flex-1 grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('messages.Variant Name') }} *</label>
                            <input type="text" step="0.01" min="0" name="new_variants[${newVariantCounter}][name]" value="${name}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                   placeholder="e.g., XXL" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('messages.Quantity') }} *</label>
                            <input type="number" step="0.01" min="0" name="new_variants[${newVariantCounter}][quantity]" value="${quantity}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                   placeholder="0" min="0" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('messages.Barcode') }}</label>
                            <input type="text" step="0.01" min="0" name="new_variants[${newVariantCounter}][barcode]" value="${barcode}"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                   placeholder="{{ __('messages.Optional') }}">
                        </div>
                    </div>
                    <button type="button" class="remove-new-variant-btn mt-5 bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
                newVariantsContainer.appendChild(div);

                // Add event listener to remove button
                div.querySelector('.remove-new-variant-btn').addEventListener('click', function() {
                    div.remove();
                });
            }

            // Add barcode verification for new variants form
            const addVariantsFormElement = document.getElementById('add-variants-form-element');
            if (addVariantsFormElement) {
                addVariantsFormElement.addEventListener('submit', async function(e) {
                    if (addVariantsFormElement.dataset.barcodeConfirmed === '1') {
                        return;
                    }

                    // Collect all variant barcodes
                    const variantBarcodeInputs = document.querySelectorAll(
                        'input[name^="new_variants"][name$="[barcode]"]');
                    const barcodesToCheck = Array.from(variantBarcodeInputs)
                        .map(input => input.value.trim())
                        .filter(Boolean);

                    if (barcodesToCheck.length) {
                        e.preventDefault();
                        try {
                            const response = await fetch('{{ route('products.check-barcodes') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                        .content
                                },
                                body: JSON.stringify({
                                    barcode: barcodesToCheck[0] || '',
                                    additional_barcodes: barcodesToCheck.slice(1)
                                })
                            });

                            if (!response.ok) {
                                throw new Error('Failed to check barcodes');
                            }

                            const data = await response.json();
                            const duplicates = Array.isArray(data.duplicates) ? data.duplicates : [];

                            if (duplicates.length) {
                                const lines = duplicates.map(item => {
                                    const productNames = (item.products || [])
                                        .map(product => {
                                            const label = product.source === 'main' ?
                                                '{{ __('messages.Main barcode') }}' :
                                                '{{ __('messages.Additional barcode') }}';
                                            return product.name ? `${product.name} (${label})` :
                                                label;
                                        })
                                        .filter(Boolean)
                                        .join(', ');
                                    return `${item.barcode}: ${productNames}`;
                                });

                                const message =
                                    '{{ __('messages.Barcode already exists. Do you want to continue?') }}' +
                                    '\n\n' + lines.join('\n');

                                if (confirm(message)) {
                                    addVariantsFormElement.dataset.barcodeConfirmed = '1';
                                    addVariantsFormElement.submit();
                                }
                            } else {
                                addVariantsFormElement.dataset.barcodeConfirmed = '1';
                                addVariantsFormElement.submit();
                            }
                        } catch (error) {
                            console.error('Error checking barcodes:', error);
                            if (confirm('{{ __('messages.Could not verify barcodes. Continue anyway?') }}')) {
                                addVariantsFormElement.dataset.barcodeConfirmed = '1';
                                addVariantsFormElement.submit();
                            }
                        }
                    }
                });
            }
        @endif

        // Barcode Scanner Function using HTML5 QR Code
        function initBarcodeScanner(inputId) {
            // Check if scanner modal already exists
            if (document.getElementById('barcode-scanner-modal')) {
                return;
            }

            const scannerModal = document.createElement('div');
            scannerModal.id = 'barcode-scanner-modal';
            scannerModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90';
            scannerModal.innerHTML = `
                <div class="bg-white rounded-lg p-4 w-full max-w-lg mx-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">{{ __('messages.Scan Barcode') }}</h3>
                        <button type="button" id="close-scanner" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="scanner-container" class="relative bg-black rounded-lg overflow-hidden" style="height: 350px;"></div>
                    <p class="text-sm text-gray-500 mt-2 text-center">{{ __('messages.Point camera at barcode') }}</p>
                </div>
            `;
            document.body.appendChild(scannerModal);

            const inputElement = document.getElementById(inputId);
            let hasScanned = false;
            let html5Qrcode = null;

            // Use HTML5 QR Code - Direct camera start
            try {
                const scannerContainer = document.getElementById('scanner-container');

                // Create video element for camera
                const videoElement = document.createElement('video');
                videoElement.style.width = '100%';
                videoElement.style.height = '100%';
                videoElement.style.objectFit = 'cover';
                videoElement.setAttribute('playsinline', 'true');
                scannerContainer.appendChild(videoElement);

                html5Qrcode = new Html5Qrcode("scanner-container");

                // Start camera directly
                html5Qrcode.start({
                        facingMode: "environment"
                    }, {
                        fps: 10,
                        qrbox: {
                            width: 250,
                            height: 150
                        },
                        aspectRatio: 1.0
                    },
                    (decodedText, decodedResult) => {
                        if (hasScanned) return;

                        const code = decodedText.trim();

                        // Validate: code should be at least 4 characters
                        if (code.length < 4) {
                            return;
                        }

                        hasScanned = true;

                        // Stop scanner
                        html5Qrcode.stop().then(() => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }).catch(err => {
                            scannerModal.remove();
                            inputElement.value = code;
                            inputElement.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        });
                    },
                    (errorMessage) => {
                        // Parse error, ignore
                    }
                ).catch(err => {
                    console.error('Camera start error:', err);
                    alert('{{ __('messages.Camera access denied or not available') }}');
                    scannerModal.remove();
                });

            } catch (err) {
                console.error('HTML5 QR Code scanner error:', err);
                alert('{{ __('messages.Camera access denied or not available') }}');
                scannerModal.remove();
            }

            document.getElementById('close-scanner').addEventListener('click', function() {
                if (html5Qrcode) {
                    html5Qrcode.stop().then(() => {
                        scannerModal.remove();
                    }).catch(err => {
                        scannerModal.remove();
                    });
                } else {
                    scannerModal.remove();
                }
            });

            scannerModal.addEventListener('click', function(e) {
                if (e.target === scannerModal) {
                    if (html5Qrcode) {
                        html5Qrcode.stop().then(() => {
                            scannerModal.remove();
                        }).catch(err => {
                            scannerModal.remove();
                        });
                    } else {
                        scannerModal.remove();
                    }
                }
            });
        }

        // Initialize scanner button for products edit
        document.addEventListener('DOMContentLoaded', function() {
            const scanBtn = document.getElementById('scan-barcode-btn');
            if (scanBtn) {
                scanBtn.addEventListener('click', function() {
                    initBarcodeScanner('barcode');
                });
            }

            // IMEI Management (only for products with has_imeis)
            const imeiPanel = document.getElementById('imei-panel');
            if (!imeiPanel) return;

            const productId = {{ $product->id }};
            const imeiListContainer = document.getElementById('imei-list-container');
            const imeiTotalCount = document.getElementById('imei-total-count');
            const imeiUnsoldCount = document.getElementById('imei-unsold-count');
            const imeiSoldCount = document.getElementById('imei-sold-count');
            const imeiFilter = document.getElementById('imei-filter');
            const newImeiInput = document.getElementById('new-imei-input');
            const addImeiBtn = document.getElementById('add-imei-to-list');
            const newImeisList = document.getElementById('new-imeis-list');
            const saveImeisBtn = document.getElementById('save-imeis-btn');
            let pendingImeis = [];

            function loadImeis(filter = 'all') {
                fetch(`/products/${productId}/imeis?filter=${filter}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        imeiTotalCount.textContent = data.total;
                        imeiUnsoldCount.textContent = data.unsold_count;
                        imeiSoldCount.textContent = data.sold_count;

                        imeiListContainer.innerHTML = '';
                        if (!data.imeis.length) {
                            imeiListContainer.innerHTML =
                                '<div class="p-4 text-center text-gray-400 text-sm">{{ __('messages.No IMEI codes found') }}</div>';
                            return;
                        }
                        data.imeis.forEach(imei => {
                            const div = document.createElement('div');
                            div.className =
                                'flex items-center justify-between px-4 py-2 border-b border-gray-100 hover:bg-gray-50';
                            const isSold = imei.sale_bill_id !== null;
                            div.innerHTML = `
                            <div class="flex-1 min-w-0">
                                <div class="font-mono text-sm ${isSold ? 'text-gray-400 line-through' : 'text-gray-800'}">${imei.imei}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    ${imei.supplier ? '🏪 ' + imei.supplier.name : ''}
                                    ${imei.purchased_at ? '📅 ' + String(imei.purchased_at).split('T')[0] : ''}
                                    ${isSold ? '<span class="text-red-500">✓ {{ __('messages.Sold') }}</span>' : '<span class="text-green-600">● {{ __('messages.Unsold') }}</span>'}
                                </div>
                            </div>
                            ${!isSold ? `<button type="button" data-id="${imei.id}" class="delete-imei text-red-400 hover:text-red-600 ml-2 text-lg leading-none">×</button>` : ''}
                        `;
                            imeiListContainer.appendChild(div);
                        });

                        // Delete handlers
                        document.querySelectorAll('.delete-imei').forEach(btn => {
                            btn.addEventListener('click', function() {
                                if (!confirm('{{ __('messages.Delete this IMEI?') }}'))
                            return;
                                const id = this.dataset.id;
                                fetch(`/products/${productId}/imeis/${id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                }).then(() => loadImeis(imeiFilter.value));
                            });
                        });
                    });
            }

            loadImeis();
            imeiFilter.addEventListener('change', () => loadImeis(imeiFilter.value));

            function addToPendingList(code) {
                if (pendingImeis.includes(code)) {
                    alert('{{ __('messages.This IMEI is already in the list') }}');
                    return;
                }
                pendingImeis.push(code);
                const div = document.createElement('div');
                div.className =
                    'flex items-center justify-between bg-white border border-indigo-200 rounded px-2 py-1';
                div.innerHTML = `
                    <span class="font-mono text-xs text-indigo-800">${code}</span>
                    <button type="button" class="text-red-400 hover:text-red-600 ml-2 remove-pending">×</button>
                `;
                div.querySelector('.remove-pending').addEventListener('click', () => {
                    div.remove();
                    pendingImeis = pendingImeis.filter(i => i !== code);
                });
                newImeisList.appendChild(div);
            }

            addImeiBtn.addEventListener('click', async () => {
                const code = newImeiInput.value.trim();
                if (!code) return;

                // Check duplicate
                try {
                    const resp = await fetch(`/products/imei/check?imei=${encodeURIComponent(code)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const result = await resp.json();
                    if (result.exists) {
                        const isSameProduct = result.product_id === productId;
                        const msg = isSameProduct ?
                            `{{ __('messages.This IMEI already exists for this product') }}. {{ __('messages.Continue?') }}` :
                            `{{ __('messages.IMEI already exists for product') }}: ${result.product_name}. {{ __('messages.Continue?') }}`;
                        if (!confirm(msg)) return;
                    }
                } catch (e) {}

                addToPendingList(code);
                newImeiInput.value = '';
                newImeiInput.focus();
            });

            newImeiInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addImeiBtn.click();
                }
            });

            saveImeisBtn.addEventListener('click', async () => {
                if (!pendingImeis.length) {
                    alert('{{ __('messages.No IMEI codes to save') }}');
                    return;
                }

                const supplierId = document.getElementById('new-imei-supplier').value;
                const purchasedAt = document.getElementById('new-imei-date').value;

                try {
                    const resp = await fetch(`/products/${productId}/imeis`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            imeis: pendingImeis,
                            supplier_id: supplierId || null,
                            purchased_at: purchasedAt || null,
                            force: true
                        })
                    });
                    const result = await resp.json();
                    if (result.success) {
                        alert(
                            `{{ __('messages.Saved') }} ${result.saved} {{ __('messages.IMEI codes') }}`);
                        pendingImeis = [];
                        newImeisList.innerHTML = '';
                        loadImeis(imeiFilter.value);
                    }
                } catch (e) {
                    alert('{{ __('messages.Error saving IMEIs') }}');
                }
            });
        });
    </script>
</x-app-layout>
