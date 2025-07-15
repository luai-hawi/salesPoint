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

        {{-- 📊 Totals --}}
        <div class="mb-6 flex gap-10 text-lg font-semibold">
            <div>Total Sales: <span id="total-sales">0</span></div>
            <div>Total Profit: <span id="total-profit">0</span></div>
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
                        @php
                            $costTotal = 0;
                            foreach ($bill->products as $product) {
                                $costTotal += $product->pivot->quantity * $product->pivot->cost_price;
                            }
                        @endphp
                        <tr class="bg-gray-200 px-6 bill-row"
                            data-date="{{ $bill->created_at->format('Y-m-d') }}"
                            data-total="{{ $bill->total_price }}"
                            data-cost="{{ $costTotal }}">
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
        const dateInput = document.getElementById('bill-date-filter');
        const salesOutput = document.getElementById('total-sales');
        const profitOutput = document.getElementById('total-profit');

        function updateTotals() {
            let total = 0;
            let cost = 0;
            document.querySelectorAll('.bill-row').forEach(row => {
                if (row.style.display !== 'none') {
                    total += parseFloat(row.dataset.total || 0);
                    cost += parseFloat(row.dataset.cost || 0);
                }
            });
            salesOutput.textContent = total.toFixed(2);
            profitOutput.textContent = (total - cost).toFixed(2);
        }

        // Filter bills by selected date
        dateInput.addEventListener('change', function () {
            const selectedDate = this.value;
            document.querySelectorAll('.bill-row').forEach(row => {
                const rowDate = row.getAttribute('data-date');
                row.style.display = (selectedDate === "" || rowDate === selectedDate) ? '' : 'none';
            });
            updateTotals();
        });

        function resetFilter() {
            dateInput.value = '';
            document.querySelectorAll('.bill-row').forEach(row => row.style.display = '');
            updateTotals();
        }

        // Calculate on page load
        document.addEventListener('DOMContentLoaded', updateTotals);
    </script>
</x-app-layout>
