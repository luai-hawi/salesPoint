<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Product') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">

            {{-- Display Validation Errors --}}
            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <strong>Whoops!</strong> There were some problems with your input:
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label for="name" class="block text-sm font-semibold">Product Name</label>
                    <input type="text" name="name" id="name" 
                           class="border rounded px-3 py-2 w-full @error('name') border-red-500 @enderror" 
                           value="{{ old('name') }}" required>
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="barcode" class="block text-sm font-semibold">Product Code</label>
                    <input type="text" name="barcode" id="barcode" 
                           class="border rounded px-3 py-2 w-full @error('barcode') border-red-500 @enderror"
                           value="{{ old('barcode') }}">
                    @error('barcode')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="pictures" class="block text-sm font-semibold">Product Pictures</label>
                    <input type="file" name="pictures[]" id="pictures" class="border rounded px-3 py-2 w-full" multiple>
                </div>

                <div class="mb-4">
                    <label for="quantity" class="block text-sm font-semibold">Quantity</label>
                    <input type="number" step="1" name="quantity" id="quantity" 
                           class="border rounded px-3 py-2 w-full" 
                           value="{{ old('quantity') }}" required>
                </div>

                <div class="mb-4">
                    <label for="cost_price" class="block text-sm font-semibold">Cost Price</label>
                    <input type="number" name="cost_price" id="cost_price" 
                           class="border rounded px-3 py-2 w-full" step="0.01" 
                           value="{{ old('cost_price') }}" required>
                </div>

                <div class="mb-4">
                    <label for="selling_price" class="block text-sm font-semibold">Selling Price</label>
                    <input type="number" name="selling_price" id="selling_price" 
                           class="border rounded px-3 py-2 w-full" step="0.01"
                           value="{{ old('selling_price') }}" required>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Create Product
                    </button>
                    <a href="{{ route('products.index') }}" class="text-blue-500 hover:underline">Back to Products</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
