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

                    {{-- Product Update Form --}}
                    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Product Name</label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Barcode</label>
                            <input type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Product Pictures</label>
                            <input type="file" name="pictures[]" multiple class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Total Quantity</label>
                            <input type="number" value="{{ $product->quantity }}" readonly
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100">
                            <small class="text-gray-500">Calculated automatically from batches.</small>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Average Cost Price</label>
                            <input type="number" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" step="0.01" required>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Selling Price</label>
                            <input type="number" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" step="0.01" required>
                        </div>

                        <div class="flex items-center justify-end mb-8">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Update Product</button>
                        </div>
                    </form>

                    {{-- Batch Management --}}
                    <h3 class="text-lg font-semibold mb-4">Product Batches</h3>
                    <table class="min-w-full bg-white border mb-4">
                        <thead class="bg-gray-100 text-gray-600 text-sm">
                            <tr>
                                <th class="px-4 py-2">Batch ID</th>
                                <th class="px-4 py-2">Quantity</th>
                                <th class="px-4 py-2">Cost Price</th>
                                <th class="px-4 py-2">Created</th>
                                <th class="px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->batches as $batch)
                                <tr data-id="{{ $batch->id }}" class="border-t">
                                    <td class="px-4 py-2">{{ $batch->id }}</td>
                                    <td class="px-4 py-2">
                                        <input type="number" class="batch-qty border rounded px-2 py-1 w-20"
                                               value="{{ $batch->quantity }}">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" step="0.01" class="batch-cost border rounded px-2 py-1 w-24"
                                               value="{{ $batch->cost_price }}">
                                    </td>
                                    <td class="px-4 py-2">{{ $batch->created_at->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 flex gap-2">
                                        <button type="button" class="save-batch bg-green-500 text-white px-3 py-1 rounded">Save</button>
                                        <button type="button" class="delete-batch bg-red-500 text-white px-3 py-1 rounded">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Add New Batch --}}
                    <h4 class="font-semibold mb-2">Add New Batch</h4>
                    <div class="flex gap-2 mb-6">
                        <input type="number" id="new-batch-qty" class="border rounded px-2 py-1 w-24" placeholder="Quantity">
                        <input type="number" id="new-batch-cost" step="0.01" class="border rounded px-2 py-1 w-32" placeholder="Cost Price">
                        <button type="button" id="add-batch" class="bg-blue-500 text-white px-3 py-1 rounded">Add Batch</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Add AJAX functionality for Save, Delete, Add new batch
        document.getElementById('add-batch').addEventListener('click', function () {
    const qty = parseInt(document.getElementById('new-batch-qty').value, 10);
    const cost = parseFloat(document.getElementById('new-batch-cost').value);

    if (!qty || !cost) {
        alert('Enter valid quantity and cost.');
        return;
    }

    // Check for existing row with the same cost price
    let updatedExisting = false;

    document.querySelectorAll('tbody tr').forEach(row => {
        const batchCostInput = row.querySelector('.batch-cost');
        const batchQtyInput = row.querySelector('.batch-qty');

        if (parseFloat(batchCostInput.value) === cost) {
            const currentQty = parseInt(batchQtyInput.value, 10);
            batchQtyInput.value = currentQty + qty;

            // Optionally save this updated batch
            row.querySelector('.save-batch').click();

            updatedExisting = true;
        }
    });

    if (!updatedExisting) {
        // If no match found, create new batch via API
        fetch('/batches', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                product_id: {{ $product->id }},
                quantity: qty,
                cost_price: cost
            })
        }).then(res => location.reload());
    }

    // Clear input fields
    document.getElementById('new-batch-qty').value = '';
    document.getElementById('new-batch-cost').value = '';
});


        document.querySelectorAll('.save-batch').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const id = row.dataset.id;
                const qty = row.querySelector('.batch-qty').value;
                const cost = row.querySelector('.batch-cost').value;

                fetch('/batches/'+id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ quantity: qty, cost_price: cost })
                }).then(res => location.reload());
            });
        });

        document.querySelectorAll('.delete-batch').forEach(btn => {
            btn.addEventListener('click', function() {
                if(!confirm('Delete this batch?')) return;
                const row = this.closest('tr');
                const id = row.dataset.id;

                fetch('/batches/'+id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    }
                }).then(res => location.reload());
            });
        });
    </script>
</x-app-layout>
