<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bills') }}
        </h2>
    </x-slot>

    <div class="py-12 mx-6">

       {{-- 📅 Date Filter Form --}}
        <form method="GET" action="{{ route('bills.index') }}" class="mb-6 flex gap-4 items-center" id="dateFilterForm">
            <input 
                type="date" 
                name="date" 
                value="{{ $selectedDate }}" 
                class="border px-4 py-2 rounded" 
                id="dateFilterInput"
            />
            @if($selectedDate)
                <a href="{{ route('bills.index') }}" class="text-sm text-gray-500 underline">Reset</a>
            @endif
        </form>

        {{-- 📊 Totals --}}
        <div class="mb-6 flex gap-10 text-lg font-semibold">
            <div>Total Sales: {{ number_format($totalSales, 2) }}</div>
            <div>Total Profit: {{ number_format($totalProfit, 2) }}</div>
        </div>

        {{-- 📄 Bills Table --}}
        <div>
            <table class="min-w-full bg-white border border-gray-300">
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
                    @forelse($bills as $bill)
                        @php
                            $costTotal = 0;
                            foreach ($bill->products as $product) {
                                $costTotal += $product->pivot->quantity * $product->pivot->cost_price;
                            }
                        @endphp
                        <tr class="bg-gray-200 px-6">
                            <td class="py-3 px-6 border-b text-left">{{ $bill->id }}</td>
                            <td class="py-3 px-6 border-b text-left">{{ $bill->total_price }}</td>
                            <td class="py-3 px-6 border-b text-left">{{ $bill->note }}</td>
                            <td class="py-3 px-6 border-b text-left">{{ $bill->created_at->format('Y-m-d') }}</td>
                            <td class="py-3 px-6 border-b text-left">
                                <a href="{{ route('bills.show', $bill->id) }}" class="text-blue-500 hover:underline">Edit</a>
                                <form action="{{ route('bills.destroy', $bill->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this bill?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-3 px-6 text-center text-gray-500">No bills found for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pagination --}}
            <div class="mt-6 flex justify-center">
                {{ $bills->appends(['date' => $selectedDate])->links('vendor.pagination.custom-light') }}
            </div>
        </div>
    </div>
    <script>
    document.getElementById('dateFilterInput').addEventListener('change', function () {
        document.getElementById('dateFilterForm').submit();
    });
</script>
</x-app-layout>
