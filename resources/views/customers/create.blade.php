<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add New Customer') }}
        </h2>
    </x-slot>

    <div class="py-12 mx-6">
        <div class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow">
            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('customers.store') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label for="name" class="block font-medium text-sm text-gray-700">Name</label>
                    <input type="text" name="name" id="name" required
                        class="w-full px-3 py-2 border rounded focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label for="phone" class="block font-medium text-sm text-gray-700">Phone</label>
                    <input type="text" name="phone" id="phone"
                        class="w-full px-3 py-2 border rounded focus:ring focus:ring-blue-200">
                </div>

                <div class="flex justify-between">
                    <a href="{{ route('customers.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
