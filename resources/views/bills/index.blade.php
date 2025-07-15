<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bills') }}
        </h2>
    </x-slot>

    <div class="py-12 mx-6">
        {{-- 🔍 Search and Add New Bill --}}
        <div class="flex justify-between mb-4">
            <a href="{{ route('bills.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add New Bill</a>
        </div>

        {{-- 🔍 Date search --}}
        <div class="mb-6 flex gap-4 items-center">
            <input type="date" id="bill-date-filter" class="border px-4 py-2 rounded" />
            <button onclick="resetFilter()" class="text-sm text-gray-500 underline">Reset</button>
        </div>

        <div>
            <table class="min-w-full bg-white border border-gray-300" id="bills-table">
                <thead>
                    <tr class="bg-gray-200 px-6">
                        <th class="py-3 px-6 border-b text-left">ID</th>
                        <th class="py-3 px-6 border-b text-left">Total Price</th>
                        <th class="py-3 px-6 border-b text-left">Note</th>
                        <th class="py-3 px-6 border-b text-left">Date</th>
                        <th class="py-3 px-6 border-b text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bills as $bill)
                        <tr class="bg-gray-200 px-6 bill-row" data-date="{{ $bill->created_at->format('Y-m-d') }}">
                            <td class="py-3 px-6 border-b text-left">{{ $bill->id }}</td>
                            <td class="py-3 px-6 border-b text-left">{{ $bill->total_price }}</td>
                            <td class="py-3 px-6 border-b text-left">{{ $bill->note }}</td>
                            <td class="py-3 px-6 border-b text-left">{{ $bill->created_at->format('Y-m-d') }}</td>
                            <td class="py-3 px-6 border-b text-left">
                                <a href="{{ route('bills.show', $bill->id) }}" class="text-blue-500 hover:underline">Edit</a>
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
    </div>

    <script>
        document.getElementById('bill-date-filter').addEventListener('change', function () {
            const selectedDate = this.value;
            document.querySelectorAll('.bill-row').forEach(row => {
                const billDate = row.getAttribute('data-date');
                row.style.display = (selectedDate === "" || billDate === selectedDate) ? '' : 'none';
            });
        });

        function resetFilter() {
            document.getElementById('bill-date-filter').value = '';
            document.querySelectorAll('.bill-row').forEach(row => row.style.display = '');
        }
    </script>
</x-app-layout>
