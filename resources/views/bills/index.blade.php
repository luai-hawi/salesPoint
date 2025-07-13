<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bills') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="flex justify-between mb-4">
            <a href="{{ route('bills.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add New Bill</a>
        </div>

        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">ID</th>
                    <th class="py-2 px-4 border-b">Product ID</th>
                    <th class="py-2 px-4 border-b">Quantity</th>
                    <th class="py-2 px-4 border-b">Total Price</th>
                    <th class="py-2 px-4 border-b">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bills as $bill)
                    <tr>
                        <td class="py-2 px-4 border-b">{{ $bill->id }}</td>
                        <td class="py-2 px-4 border-b">{{ $bill->product_id }}</td>
                        <td class="py-2 px-4 border-b">{{ $bill->quantity }}</td>
                        <td class="py-2 px-4 border-b">{{ $bill->total_price }}</td>
                        <td class="py-2 px-4 border-b">
                            <a href="{{ route('bills.edit', $bill->id) }}" class="text-blue-500 hover:underline">Edit</a>
                            <form action="{{ route('bills.destroy', $bill->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>