<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12 flex flex-row space-x-6">
        <x-block>
            <div class="flex space-x-4 mb-4 items-center justify-center">
            <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add</button>
            <button type="button" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Delete</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div style="background: #f5f5f5; padding: 20px;">Item 1</div>
            <div style="background: #f5f5f5; padding: 20px;">Item 2</div>
            <div style="background: #f5f5f5; padding: 20px;">Item 3</div>
            <div style="background: #f5f5f5; padding: 20px;">Item 4</div>
            <div style="background: #f5f5f5; padding: 20px;">Item 5</div>
            <div style="background: #f5f5f5; padding: 20px;">Item 6</div>
            </div>
        </x-block>

    


        <x-block class="w-full">
            goods!
        </x-block>

        <x-block class="w-2/5">
            <div class="flex flex-col space-y-4">
            <label class="font-semibold">Bar Code</label>
            <input type="text" name="barcode" class="border rounded px-3 py-2" placeholder="Enter bar code">

            <label class="font-semibold">Price</label>
            <input type="number" name="price" class="border rounded px-3 py-2 bg-gray-100" placeholder="Price" step="0.01" readonly>

            <label class="font-semibold">Discount</label>
            <input type="number" name="discount" class="border rounded px-3 py-2" placeholder="Discount" step="0.01">

            <label class="font-semibold">Total Price</label>
            <input type="number" name="total_price" class="border rounded px-3 py-2 bg-gray-100" placeholder="Total Price" step="0.01" readonly>
            </div>
        </x-block>
    </div>
</x-app-layout>
