<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Product') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
            <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label for="name" class="block text-sm font-semibold">Product Name</label>
                    <input type="text" name="name" id="name" class="border rounded px-3 py-2 w-full" required>
                </div>

                <div class="mb-4">
                    <label for="pictures" class="block text-sm font-semibold">Product Pictures</label>
                    <input type="file" name="pictures[]" id="pictures" class="border rounded px-3 py-2 w-full" multiple required>
                </div>

                <div class="mb-4">
                    <label for="cost_price" class="block text-sm font-semibold">Cost Price</label>
                    <input type="number" name="cost_price" id="cost_price" class="border rounded px-3 py-2 w-full" step="0.01" required>
                </div>

                <div class="mb-4">
                    <label for="selling_price" class="block text-sm font-semibold">Selling Price</label>
                    <input type="number" name="selling_price" id="selling_price" class="border rounded px-3 py-2 w-full" step="0.01" required>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create Product</button>
                    <a href="{{ route('products.index') }}" class="text-blue-500 hover:underline">Back to Products</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>