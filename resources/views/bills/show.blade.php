<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bill Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <x-block>
            <div class="mb-4">
                <h3 class="font-semibold">Bill ID: {{ $bill->id }}</h3>
                <p><strong>Product:</strong> {{ $bill->product->name }}</p>
                <p><strong>Quantity:</strong> {{ $bill->quantity }}</p>
                <p><strong>Total Price:</strong> ${{ number_format($bill->total_price, 2) }}</p>
                <p><strong>Created At:</strong> {{ $bill->created_at->format('d-m-Y H:i') }}</p>
                <p><strong>Updated At:</strong> {{ $bill->updated_at->format('d-m-Y H:i') }}</p>
            </div>
            <div class="flex space-x-4">
                <a href="{{ route('bills.edit', $bill->id) }}" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">Edit</a>
                <form action="{{ route('bills.destroy', $bill->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Delete</button>
                </form>
            </div>
        </x-block>
    </div>
</x-app-layout>