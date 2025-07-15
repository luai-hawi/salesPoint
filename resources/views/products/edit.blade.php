<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Product') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Product Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        </div>

                        <div class="mb-4">
                            <label for="barcode" class="block text-sm font-medium text-gray-700">Product Code</label>
                            <input type="text" name="barcode" id="barcode" value="{{ old('barcode', $product->barcode) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        </div>


                        <div class="mb-4">
                            <label for="pictures" class="block text-sm font-medium text-gray-700">Product Pictures</label>
                            <input type="file" name="pictures[]" id="pictures" multiple class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>

                        <div class="mb-4">
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input type="number" name="quantity" step="1" id="quantity" value="{{ old('quantity', $product->quantity) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" step="0.01" required>
                        </div>

                        <div class="mb-4">
                            <label for="cost_price" class="block text-sm font-medium text-gray-700">Cost Price</label>
                            <input type="number" name="cost_price" id="cost_price" value="{{ old('cost_price', $product->cost_price) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" step="0.01" required>
                        </div>

                        <div class="mb-4">
                            <label for="selling_price" class="block text-sm font-medium text-gray-700">Selling Price</label>
                            <input type="number" name="selling_price" id="selling_price" value="{{ old('selling_price', $product->selling_price) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50" step="0.01" required>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Update Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>