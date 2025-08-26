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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('messages.Create New Product') }}
            </h2>
            <a href="{{ route('products.index') }}" class="text-gray-600 hover:text-gray-800 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
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
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        {{ __('messages.Product Information') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">{{ __('messages.Fill in the details below to create a new product in your inventory.') }}</p>
                </div>

                <!-- Display Validation Errors -->
                @if ($errors->any())
                    <div class="m-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
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

                <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- Left Column -->
                        <div class="space-y-6">
                            
                            <!-- Product Name -->
                            <div>
                                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Product Name') }} *
                                </label>
                                <input 
                                    type="text" 
                                    name="name" 
                                    id="name" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('name') border-red-500 @enderror" 
                                    value="{{ old('name') }}" 
                                    required
                                    placeholder="{{ __('messages.Enter product name...') }}"
                                >
                                @error('name')
                                    <p class="text-red-500 text-xs mt-1 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- Product Code/Barcode -->
                            <div>
                                <label for="barcode" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Product Code / Barcode') }}
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        name="barcode" 
                                        id="barcode" 
                                        class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('barcode') border-red-500 @enderror"
                                        value="{{ old('barcode') }}"
                                        placeholder="{{ __('messages.Optional barcode or product code...') }}"
                                    >
                                    <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                                    </svg>
                                </div>
                                @error('barcode')
                                    <p class="text-red-500 text-xs mt-1 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- Initial Quantity -->
                            <div>
                                <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Initial Stock Quantity') }} *
                                </label>
                                <div class="relative">
                                    <input 
                                        type="number" 
                                        step="1" 
                                        name="quantity" 
                                        id="quantity" 
                                        class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                        value="{{ old('quantity', 0) }}" 
                                        required
                                        min="0"
                                        placeholder="{{ __('products.Enter initial stock quantity') }}"
                                    >
                                    <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ __('messages.This will create the initial stock batch for your product.') }}</p>
                            </div>

                            <!-- Has Tags Checkbox -->
                            <div class="mb-6">
                                <div class="flex items-center">
                                    <input type="checkbox" name="has_tags" id="has_tags" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" 
                                        value="1" {{ old('has_tags', $product->has_tags ?? false) ? 'checked' : '' }}>
                                    <label for="has_tags" class="ml-2 block text-sm font-medium text-gray-700">
                                       {{__('messages.This product has customizable tags')}}
                                    </label>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{__('messages.Check this if customers can add extra options/tags to this product')}}</p>
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
                                    <input 
                                        type="number" 
                                        name="cost_price" 
                                        id="cost_price" 
                                        class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                        step="0.01" 
                                        value="{{ old('cost_price') }}" 
                                        required
                                        min="0"
                                        placeholder="{{ __('messages.Enter cost price') }}"
                                    >
                                    <span class="absolute left-3 top-3.5 text-gray-400">$</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ __('messages.How much you paid for this product.') }}</p>
                            </div>

                            <!-- Selling Price -->
                            <div>
                                <label for="selling_price" class="block text-sm font-semibold text-gray-700 mb-2">
                                    {{ __('messages.Selling Price (per unit)') }} *
                                </label>
                                <div class="relative">
                                    <input 
                                        type="number" 
                                        name="selling_price" 
                                        id="selling_price" 
                                        class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                        step="0.01"
                                        value="{{ old('selling_price') }}" 
                                        required
                                        min="0"
                                        placeholder="{{ __('messages.Enter selling price') }}"
                                    >
                                    <span class="absolute left-3 top-3.5 text-gray-400">$</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ __('messages.Price you\'ll sell this product for.') }}</p>
                            </div>

                            <!-- Profit Margin Display -->
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-green-800">{{ __('messages.Profit Margin:') }}</span>
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
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                                <div class="space-y-2 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="pictures" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>{{ __('messages.Upload images') }}</span>
                                            <input id="pictures" name="pictures[]" type="file" class="sr-only" multiple accept="image/*">
                                        </label>
                                        <p class="pl-1">{{ __('messages.or drag and drop') }}</p>
                                    </div>
                                    <p class="text-xs text-gray-500">{{ __('messages.PNG, JPG, GIF up to 2MB each') }}</p>
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
                        <a href="{{ route('products.index') }}" class="text-gray-600 hover:text-gray-800 font-medium flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            {{ __('messages.Cancel') }}
                        </a>
                        
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-8 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('messages.Create Product') }}
                        </button>
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

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {             
            // Profit margin calculation             
            document.getElementById('cost_price').addEventListener('input', calculateProfitMargin);             
            document.getElementById('selling_price').addEventListener('input', calculateProfitMargin);             
            
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

                    reader.onload = function (e) {
                        img.src = e.target.result;
                    };

                    img.onload = function () {
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
            document.getElementById("pictures").addEventListener("change", async function (e) {
                await handleCompressedImages(e.target);
            });                    
            
            // Initial calculation             
            calculateProfitMargin();         
        });

        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
            
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
                if (!confirm('{{ __('messages.Selling price is lower than cost price. This will result in a loss. Are you sure you want to continue?') }}')) {
                    e.preventDefault();
                    return;
                }
            }
        });


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
    document.getElementById("pictures").addEventListener("change", async function (e) {
        await handleCompressedImages(e.target);
    });
    </script>
</x-app-layout>