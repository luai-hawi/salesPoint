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
                <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                    </path>
                </svg>
                {{ __('messages.Create New Product') }}
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

    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Main Form Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-green-50 to-blue-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        {{ __('messages.Product Information') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('messages.Fill in the details below to create a new product in your inventory.') }}</p>
                </div>

                <!-- Display Validation Errors -->
                @if ($errors->any())
                    <div class="m-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <strong class="text-red-800">{{ __('messages.Please fix the following errors:') }}</strong>
                        </div>
                        <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="p-6"
                    id="product-create-form">
                    @csrf

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                        <!-- Left Column -->
                        <div class="space-y-6">

                            <!-- Product Name -->
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Product Name') }} *
                                </label>
                                <input type="text" name="name" id="name" tabindex="1" autofocus
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('name') border-red-500 @enderror"
                                    value="{{ old('name') }}" required
                                    placeholder="{{ __('messages.Enter product name...') }}">
                                @error('name')
                                    <p class="text-red-500 text-xs mt-1 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                            <div>
                                <label for="category" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Category') }}
                                </label>
                                <input type="text" name="category" id="category"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('category') border-red-500 @enderror"
                                    value="{{ old('category') }}"
                                    placeholder="{{ __('messages.Enter product category (optional)...') }}"
                                    list="category-suggestions">
                                <datalist id="category-suggestions">
                                    <!-- This will be populated with existing categories -->
                                </datalist>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('messages.Optional - helps organize products by category.') }}</p>
                                @error('category')
                                    <p class="text-red-500 text-xs mt-1 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- Variant Toggle Switch -->
                            <div
                                class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01">
                                            </path>
                                        </svg>
                                        <label for="has_variants_toggle" class="text-sm font-semibold text-gray-700">
                                            {{ __('messages.Create Product with Variants') }}
                                        </label>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="has_variants_toggle" class="sr-only peer">
                                        <input type="hidden" name="has_variants" id="has_variants" value="0">
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600">
                                        </div>
                                    </label>
                                </div>
                                <p class="text-xs text-purple-600 mt-2">
                                    {{ __('messages.Enable this to create multiple variants (e.g., S, M, L, XL) as separate products') }}
                                </p>
                            </div>

                            <!-- Single Product Fields (shown when variants are disabled) -->
                            <div id="single-product-fields">
                                <!-- Product Code/Barcode -->
                                <div>
                                    <label for="barcode" class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('messages.Product Code / Barcode') }}
                                    </label>
                                    <div class="flex">
                                        <div class="relative flex-1">
                                            <input type="text" name="barcode" id="barcode"
                                                class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('barcode') border-red-500 @enderror"
                                                value="{{ old('barcode') }}"
                                                placeholder="{{ __('messages.Optional barcode or product code...') }}">
                                            <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z">
                                                </path>
                                            </svg>
                                            <!-- Camera Scanner Icon -->
                                            <button type="button" id="scan-barcode-btn"
                                                class="absolute right-3 top-3.5 h-5 w-5 text-gray-400 hover:text-blue-500 transition-colors cursor-pointer"
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
                                    @error('barcode')
                                        <p class="text-red-500 text-xs mt-1 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Additional Barcodes -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('messages.Additional Barcodes') }}
                                    </label>
                                    <div id="additional-barcodes-container" class="h-40 overflow-y-auto">
                                        <!-- Additional barcode inputs will be added here -->
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

                                <!-- Initial Quantity -->
                                <div>
                                    <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('messages.Initial Stock Quantity') }} *
                                    </label>
                                    <div class="relative">
                                        <input type="number" step="0.01" name="quantity" id="quantity"
                                            tabindex="4"
                                            class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                            value="{{ old('quantity', 0) }}" required min="0"
                                            placeholder="{{ __('messages.Enter initial stock quantity') }}">
                                        <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                                            </path>
                                        </svg>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ __('messages.This will create the initial stock batch for your product.') }}
                                    </p>
                                </div>

                                <!-- Low Stock Threshold -->
                                <div>
                                    <label for="low_stock_threshold"
                                        class="block text-sm font-semibold text-gray-700 mb-2">
                                        {{ __('messages.Low Stock Warning Threshold') }}
                                    </label>
                                    <div class="relative">
                                        <input type="number" name="low_stock_threshold" id="low_stock_threshold"
                                            class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                            value="{{ old('low_stock_threshold', 10) }}" min="1"
                                            placeholder="{{ __('messages.Enter low stock threshold') }}">
                                        <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4v2m0 0v2m0 0a9 9 0 1118 0 9 9 0 01-18 0z">
                                            </path>
                                        </svg>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ __('messages.When stock reaches this number or below, a low stock badge will appear. Default is 10.') }}
                                    </p>
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
                                                {{ old('has_imeis') ? 'checked' : '' }}>
                                            <div
                                                class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                            </div>
                                        </label>
                                    </div>
                                    <p class="text-xs text-indigo-600 mt-2">
                                        {{ __('messages.Enable this for products like phones, laptops, etc. that have unique IMEI or serial numbers per unit.') }}
                                    </p>
                                </div>

                                <!-- IMEI Entry Panel (shown when has_imeis is toggled on) -->
                                <div id="create-imei-panel"
                                    class="mb-6 bg-white border border-indigo-300 rounded-lg p-4 space-y-3"
                                    style="display:none;">
                                    <h4 class="text-sm font-semibold text-indigo-700 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        {{ __('messages.Add IMEI Code') }}
                                    </h4>

                                    <!-- Supplier + Date -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.IMEI Supplier') }}</label>
                                            <select name="new_imeis_supplier" id="create-imei-supplier"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">— {{ __('messages.None') }} —</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.Purchase Date') }}</label>
                                            <input type="date" name="new_imeis_date" id="create-imei-date"
                                                value="{{ date('Y-m-d') }}"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                    </div>

                                    <!-- IMEI input -->
                                    <div class="flex gap-2">
                                        <input type="text" id="create-imei-input"
                                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                                            placeholder="{{ __('messages.Enter IMEI or serial code...') }}">
                                        <button type="button" id="create-add-imei-btn"
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                            {{ __('messages.Add') }}
                                        </button>
                                    </div>

                                    <!-- Pending IMEI list -->
                                    <div id="create-imei-list" class="space-y-1 max-h-48 overflow-y-auto"></div>
                                    <p class="text-xs text-gray-400">
                                        {{ __('messages.Add one IMEI per unit purchased') }}</p>

                                    <!-- Hidden inputs populated by JS -->
                                    <div id="create-imei-hidden-inputs"></div>
                                </div>
                            </div>

                            <!-- Variants Section (shown when variants are enabled) -->
                            <div id="variants-section" style="display: none;">
                                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-purple-800">
                                            {{ __('messages.Product Variants') }}</h4>
                                        <button type="button" id="add-variant-btn"
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded-lg transition-colors flex items-center text-sm">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            {{ __('messages.Add Variant') }}
                                        </button>
                                    </div>
                                    <div id="variants-container" class="space-y-3 max-h-96 overflow-y-auto">
                                        <!-- Variant inputs will be added here -->
                                    </div>
                                    <p class="text-xs text-purple-600 mt-2">
                                        {{ __('messages.Add variants like S, M, L, XL. Each will be saved as a separate product.') }}
                                    </p>
                                </div>
                            </div>

                        </div>

                        <!-- Right Column -->
                        <div class="space-y-6">

                            <!-- Cost Price -->
                            <div>
                                <label for="cost_price" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Cost Price (per unit)') }} *
                                </label>
                                <div class="relative">
                                    <input type="number" name="cost_price" id="cost_price" tabindex="2"
                                        class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                        step="0.01" value="{{ old('cost_price') }}" required min="0"
                                        placeholder="{{ __('messages.Enter cost price') }}">
                                    <span class="absolute left-3 top-3.5 text-gray-400">₪</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('messages.How much you paid for this product.') }}</p>
                            </div>

                            <!-- Selling Price -->
                            <div>
                                <label for="selling_price" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Selling Price (per unit)') }} *
                                </label>
                                <div class="relative">
                                    <input type="number" name="selling_price" id="selling_price" tabindex="3"
                                        class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                        step="0.01" value="{{ old('selling_price') }}" required min="0"
                                        placeholder="{{ __('messages.Enter selling price') }}">
                                    <span class="absolute left-3 top-3.5 text-gray-400">₪</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('messages.Price you\'ll sell this product for.') }}</p>
                            </div>

                            <!-- Profit Margin Display -->
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm font-medium text-green-800">{{ __('messages.Profit Margin:') }}</span>
                                    <span id="profit-margin" class="text-lg font-bold text-green-700">0%</span>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-green-600">{{ __('messages.Profit per unit:') }}</span>
                                    <span id="profit-amount" class="text-sm font-semibold text-green-700">$0.00</span>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Product Pictures Section -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="mb-4">
                            <label for="pictures" class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ __('messages.Product Pictures') }}
                            </label>
                            <div
                                class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                                <div class="space-y-2 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none"
                                        viewBox="0 0 48 48">
                                        <path
                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="pictures"
                                            class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>{{ __('messages.Upload images') }}</span>
                                            <input id="pictures" name="pictures[]" type="file" class="sr-only"
                                                multiple accept="image/*">
                                        </label>
                                        <p class="pl-1">{{ __('messages.or drag and drop') }}</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        {{ __('messages.PNG, JPG, GIF up to 2MB each') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Image Preview Area -->
                        <div id="image-preview" class="hidden mt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">{{ __('messages.Preview:') }}</h4>
                            <div id="preview-container" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-between">
                        <a href="{{ route('products.index') }}"
                            class="text-gray-600 hover:text-gray-800 font-medium flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            {{ __('messages.Cancel') }}
                        </a>

                        <div class="flex items-center">
                            <button type="button" id="print-barcode"
                                class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-8 rounded-lg transition-colors flex items-center mr-4 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                                    </path>
                                </svg>
                                {{ __('messages.Print Barcode') }}
                            </button>

                            <button type="submit" tabindex="5"
                                class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-8 rounded-lg transition-colors flex items-center shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ __('messages.Create Product') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Profit margin calculation
        function calculateProfitMargin() {
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;

            if (sellingPrice > 0) {
                const profit = sellingPrice - costPrice;
                const margin = (profit / sellingPrice) * 100;

                document.getElementById('profit-margin').textContent = margin.toFixed(1) + '%';
                document.getElementById('profit-amount').textContent = '$' + profit.toFixed(2);

                // Color coding
                const marginElement = document.getElementById('profit-margin');
                const amountElement = document.getElementById('profit-amount');

                if (margin > 20) {
                    marginElement.className = 'text-lg font-bold text-green-700';
                    amountElement.className = 'text-sm font-semibold text-green-700';
                } else if (margin > 10) {
                    marginElement.className = 'text-lg font-bold text-yellow-600';
                    amountElement.className = 'text-sm font-semibold text-yellow-600';
                } else {
                    marginElement.className = 'text-lg font-bold text-red-600';
                    amountElement.className = 'text-sm font-semibold text-red-600';
                }
            } else {
                document.getElementById('profit-margin').textContent = '0%';
                document.getElementById('profit-amount').textContent = '$0.00';
            }
        }

        // Image preview functionality
        function handleImagePreview(files) {
            const previewSection = document.getElementById('image-preview');
            const previewContainer = document.getElementById('preview-container');

            if (files.length > 0) {
                previewSection.classList.remove('hidden');
                previewContainer.innerHTML = '';

                Array.from(files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'relative';
                            div.innerHTML = `
                                <img src="${e.target.result}" class="w-full h-24 object-cover rounded-lg border border-gray-200">
                                <button type="button" onclick="removeImage(${index})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition-colors">
                                    ×
                                </button>
                            `;
                            previewContainer.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                previewSection.classList.add('hidden');
            }
        }

        // Remove image from preview
        function removeImage(index) {
            const input = document.getElementById('pictures');
            const dt = new DataTransfer();
            const files = input.files;

            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }

            input.files = dt.files;
            handleImagePreview(input.files);
        }
        // Add this to your existing JavaScript in create.blade.php and edit.blade.php

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

        // Additional barcodes functionality
        function addBarcodeInput(value = '') {
            const container = document.getElementById('additional-barcodes-container');
            const div = document.createElement('div');
            div.className = 'flex items-center mb-2';
            div.innerHTML = `
                <div class="relative flex-1">
                    <input type="text" name="additional_barcodes[]" value="${value}"
                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mr-2"
                           placeholder="{{ __('messages.Enter barcode') }}">
                    <button type="button" class="scan-additional-barcode-btn absolute right-2 top-2.5 h-5 w-5 text-gray-400 hover:text-blue-500 transition-colors cursor-pointer"
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

            // Prevent Enter from submitting the form (barcode readers send Enter after the code)
            div.querySelector('input[name="additional_barcodes[]"]').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') e.preventDefault();
            });

            // Add event listener to remove button
            div.querySelector('.remove-barcode-btn').addEventListener('click', function() {
                div.remove();
            });

            // Add event listener to scan button
            div.querySelector('.scan-additional-barcode-btn').addEventListener('click', function() {
                const input = div.querySelector('input[name="additional_barcodes[]"]');
                initBarcodeScannerForAdditionalBarcode(input);
            });
        }

        // Scanner function for additional barcodes
        async function initBarcodeScannerForAdditionalBarcode(inputElement) {
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

                if (typeof Html5Qrcode === 'undefined') {
                    if (!navigator.onLine) {
                        showNotification('Barcode scanner is unavailable offline', 'warning');
                        scannerModal.remove();
                        return;
                    }
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://unpkg.com/html5-qrcode';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                if (typeof Html5Qrcode === 'undefined') {
                    showNotification('Error loading scanner', 'error');
                    scannerModal.remove();
                    return;
                }
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

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            loadCategoryAutoComplete();

            // Profit margin calculation
            document.getElementById('cost_price').addEventListener('input', calculateProfitMargin);
            document.getElementById('selling_price').addEventListener('input', calculateProfitMargin);

            // Add barcode button
            document.getElementById('add-barcode-btn').addEventListener('click', function() {
                addBarcodeInput();
            });

            // Generate barcode
            document.getElementById('generate-barcode').addEventListener('click', async function() {
                try {
                    const response = await fetch('/products/next-id');
                    if (response.ok) {
                        const data = await response.json();
                        const barcode = '150702' + data.next_id;
                        document.getElementById('barcode').value = barcode;
                    } else {
                        console.error('Failed to fetch next product ID');
                    }
                } catch (error) {
                    console.error('Error generating barcode:', error);
                }
            });

            // Prevent Enter key submission - Use keydown instead of keypress
            const inputs = document.querySelectorAll('input[type="text"], input[type="number"]');
            inputs.forEach(function(input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });
            });



            // --- Image compression helper ---
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

                        // Scale down while keeping aspect ratio
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
                            quality // compression quality (0.7 = 70%)
                        );
                    };

                    reader.readAsDataURL(file);
                });
            }

            async function handleCompressedImages(input) {
                const dt = new DataTransfer();
                for (let file of input.files) {
                    if (file.type.startsWith("image/")) {
                        const compressed = await compressImage(file, 800, 800, 0.7);
                        dt.items.add(compressed);
                    } else {
                        dt.items.add(file);
                    }
                }
                input.files = dt.files;
                handleImagePreview(input.files); // reuse your preview function
            }

            // Image preview
            document.getElementById("pictures").addEventListener("change", async function(e) {
                await handleCompressedImages(e.target);
            });

            // Initial calculation
            calculateProfitMargin();

            // Variant toggle functionality
            const variantsToggle = document.getElementById('has_variants_toggle');
            const variantsInput = document.getElementById('has_variants');
            const singleProductFields = document.getElementById('single-product-fields');
            const variantsSection = document.getElementById('variants-section');
            const quantityField = document.getElementById('quantity');
            let variantCounter = 0;

            variantsToggle.addEventListener('change', function() {
                if (this.checked) {
                    variantsInput.value = '1';
                    singleProductFields.style.display = 'none';
                    variantsSection.style.display = 'block';
                    quantityField.removeAttribute('required');
                    // Add initial variant
                    if (document.getElementById('variants-container').children.length === 0) {
                        addVariantInput();
                    }
                } else {
                    variantsInput.value = '0';
                    singleProductFields.style.display = 'block';
                    variantsSection.style.display = 'none';
                    quantityField.setAttribute('required', 'required');
                }
            });

            // Add variant input function
            function addVariantInput(name = '', quantity = '', barcode = '') {
                variantCounter++;
                const container = document.getElementById('variants-container');
                const div = document.createElement('div');
                div.className = 'bg-white border border-purple-200 rounded-lg p-3';
                div.innerHTML = `
                    <div class="flex items-start gap-2">
                        <div class="flex-1 grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('messages.Variant Name') }} *</label>
                                <input type="text" step="0.01" min="0" name="variants[${variantCounter}][name]" value="${name}"
                                       class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                       placeholder="e.g., S, M, L" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('messages.Quantity') }} *</label>
                                <input type="number" step="0.01" min="0" name="variants[${variantCounter}][quantity]" value="${quantity}"
                                       class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                       placeholder="0" min="0" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('messages.Barcode') }}</label>
                                <input type="text" step="0.01" min="0" name="variants[${variantCounter}][barcode]" value="${barcode}"
                                       class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                       placeholder="{{ __('messages.Optional') }}">
                            </div>
                        </div>
                        <button type="button" class="remove-variant-btn mt-5 bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                `;
                container.appendChild(div);

                // Add event listener to remove button
                div.querySelector('.remove-variant-btn').addEventListener('click', function() {
                    div.remove();
                });
            }

            // Add variant button
            document.getElementById('add-variant-btn').addEventListener('click', function() {
                addVariantInput();
            });

            // Quick add common sizes
            const commonSizes = ['S', 'M', 'L', 'XL'];
            // You can add a button to quickly add these if needed
        });

        // Form validation before submit
        const productCreateForm = document.getElementById('product-create-form');

        productCreateForm.addEventListener('submit', async function(e) {
            if (productCreateForm.dataset.barcodeConfirmed === '1') {
                return;
            }
            const name = document.getElementById('name').value.trim();
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
            const hasVariants = document.getElementById('has_variants').value === '1';

            let barcodesToCheck = [];

            if (hasVariants) {
                // Collect all variant barcodes
                const variantBarcodeInputs = document.querySelectorAll(
                    'input[name^="variants"][name$="[barcode]"]');
                barcodesToCheck = Array.from(variantBarcodeInputs)
                    .map(input => input.value.trim())
                    .filter(Boolean);
            } else {
                // Collect main and additional barcodes
                const mainBarcode = document.getElementById('barcode').value.trim();
                const additionalInputs = document.querySelectorAll('input[name="additional_barcodes[]"]');
                const additionalBarcodes = Array.from(additionalInputs)
                    .map(input => input.value.trim())
                    .filter(Boolean);
                barcodesToCheck = [mainBarcode, ...additionalBarcodes].filter(Boolean);
            }

            if (!name) {
                e.preventDefault();
                alert('{{ __('messages.Please enter a product name.') }}');
                document.getElementById('name').focus();
                return;
            }

            if (costPrice < 0) {
                e.preventDefault();
                alert('{{ __('messages.Cost price cannot be negative.') }}');
                document.getElementById('cost_price').focus();
                return;
            }

            if (sellingPrice < 0) {
                e.preventDefault();
                alert('{{ __('messages.Selling price cannot be negative.') }}');
                document.getElementById('selling_price').focus();
                return;
            }

            if (sellingPrice < costPrice) {
                if (!confirm(
                        '{{ __('messages.Selling price is lower than cost price. This will result in a loss. Are you sure you want to continue?') }}'
                    )) {
                    e.preventDefault();
                    return;
                }
            }

            if (barcodesToCheck.length) {
                e.preventDefault();
                try {
                    const response = await fetch('{{ route('products.check-barcodes') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
                                    return product.name ? `${product.name} (${label})` : label;
                                })
                                .filter(Boolean)
                                .join(', ');
                            return `${item.barcode}: ${productNames}`;
                        });

                        const message =
                            '{{ __('messages.Barcode already exists. Do you want to continue?') }}' +
                            '\n\n' +
                            '{{ __('messages.Conflicting barcodes') }}' +
                            '\n' +
                            lines.join('\n');

                        if (!confirm(message)) {
                            return;
                        }
                    }

                    productCreateForm.dataset.barcodeConfirmed = '1';
                    productCreateForm.submit();
                } catch (error) {
                    console.error(error);
                    alert('{{ __('messages.Failed to verify barcodes. Please try again.') }}');
                }
            }
        });


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

                    // Scale down while keeping aspect ratio
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
                        quality // 0.7 = 70% quality
                    );
                };

                reader.readAsDataURL(file);
            });
        }

        async function handleCompressedImages(input) {
            const dt = new DataTransfer();
            for (let file of input.files) {
                if (file.type.startsWith("image/")) {
                    const compressed = await compressImage(file, 800, 800, 0.7);
                    dt.items.add(compressed);
                } else {
                    dt.items.add(file);
                }
            }
            input.files = dt.files;
            handleImagePreview(input.files); // use your existing preview function
        }

        // Replace your event listener for file input
        document.getElementById("pictures").addEventListener("change", async function(e) {
            await handleCompressedImages(e.target);
        });

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
    </script>

    <!-- Barcode Scanner for Products Create -->
    <script>
        // Barcode Scanner Function using HTML5 QR Code
        async function initBarcodeScanner(inputId) {
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

                if (typeof Html5Qrcode === 'undefined') {
                    if (!navigator.onLine) {
                        showNotification('Barcode scanner is unavailable offline', 'warning');
                        scannerModal.remove();
                        return;
                    }
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://unpkg.com/html5-qrcode';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                if (typeof Html5Qrcode === 'undefined') {
                    showNotification('Error loading scanner', 'error');
                    scannerModal.remove();
                    return;
                }
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

        // Initialize scanner button for products create
        document.addEventListener('DOMContentLoaded', function() {
            const scanBtn = document.getElementById('scan-barcode-btn');
            if (scanBtn) {
                scanBtn.addEventListener('click', function() {
                    initBarcodeScanner('barcode');
                });
            }

            // ── IMEI Panel (create form) ─────────────────────────────────────
            const hasImeisChk = document.getElementById('has_imeis');
            const imeiPanel = document.getElementById('create-imei-panel');
            const imeiInput = document.getElementById('create-imei-input');
            const addImeiBtn = document.getElementById('create-add-imei-btn');
            const imeiList = document.getElementById('create-imei-list');
            const hiddenInputs = document.getElementById('create-imei-hidden-inputs');
            const pendingImeis = [];

            if (!hasImeisChk || !imeiPanel) return;

            // Toggle panel visibility
            function syncPanel() {
                imeiPanel.style.display = hasImeisChk.checked ? '' : 'none';
            }
            hasImeisChk.addEventListener('change', syncPanel);
            syncPanel();

            function renderList() {
                imeiList.innerHTML = '';
                hiddenInputs.innerHTML = '';
                pendingImeis.forEach((code, idx) => {
                    // visible badge
                    const row = document.createElement('div');
                    row.className =
                        'flex items-center justify-between bg-indigo-50 border border-indigo-200 rounded px-3 py-1.5';
                    row.innerHTML = `
                        <span class="font-mono text-sm text-indigo-800">${code}</span>
                        <button type="button" class="remove-create-imei text-red-400 hover:text-red-600 ml-2" data-idx="${idx}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>`;
                    imeiList.appendChild(row);
                    // hidden input
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'new_imeis[]';
                    inp.value = code;
                    hiddenInputs.appendChild(inp);
                });
            }

            function addImei() {
                const code = imeiInput.value.trim();
                if (!code) return;
                if (pendingImeis.includes(code)) {
                    imeiInput.classList.add('border-red-400');
                    imeiInput.title = '{{ __('messages.IMEI already in list') }}';
                    setTimeout(() => imeiInput.classList.remove('border-red-400'), 1500);
                    return;
                }
                pendingImeis.push(code);
                renderList();
                imeiInput.value = '';
                imeiInput.focus();
            }

            addImeiBtn.addEventListener('click', addImei);
            imeiInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addImei();
                }
            });

            imeiList.addEventListener('click', e => {
                const btn = e.target.closest('.remove-create-imei');
                if (btn) {
                    pendingImeis.splice(parseInt(btn.dataset.idx), 1);
                    renderList();
                }
            });
        });
    </script>
</x-app-layout>
