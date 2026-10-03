<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('messages.Bills Management')" :subtitle="__('receivables.payment_panel')">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge tone="blue">{{ __('bills.Total Bills') }}: {{ $bills->total() }}</x-ui.badge>
            </div>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('bills.Total Sales')" :value="'₪' . number_format($totalSales, 2)" />
                <x-ui.stat :label="__('bills.Total Profit')" :value="'₪' . number_format($totalProfit, 2)" />
                <x-ui.stat :label="__('receivables.paid')" :value="'₪' . number_format($filteredPaid, 2)" />
                <x-ui.stat :label="__('receivables.due')" :value="'₪' . number_format($filteredDue, 2)" />
            </div>

            <x-ui.card>
                <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Search...') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Date') }}</label>
                        <input type="date" name="date" value="{{ $selectedDate }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.payment_status') }}</label>
                        <select name="payment_status"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('receivables.all_statuses') }}</option>
                            <option value="cash" @selected($paymentStatus === 'cash')>{{ __('receivables.cash_sale') }}</option>
                            <option value="paid" @selected($paymentStatus === 'paid')>{{ __('receivables.paid') }}</option>
                            <option value="partial" @selected($paymentStatus === 'partial')>{{ __('receivables.partial') }}</option>
                            <option value="unpaid" @selected($paymentStatus === 'unpaid')>{{ __('receivables.unpaid') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                            {{ __('messages.Filter') }}
                        </button>
                        <a href="{{ route('bills.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('messages.Clear') }}
                        </a>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('bills.Bill Info') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('bills.Customer') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.payment_status') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Amount') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.payment_method') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($bills as $bill)
                                @php
                                    $summary = $bill->payment_summary ?? ['status' => 'cash', 'paid' => $bill->total_price, 'due' => 0];
                                    $confirmText = $summary['paid'] > 0 && $summary['status'] !== 'cash'
                                        ? __('receivables.bill_payments_are_deleted_with_bill')
                                        : __('bills.Are you sure you want to delete this bill? This will restore product quantities.');
                                    $tone = match ($summary['status']) {
                                        'paid' => 'green',
                                        'partial' => 'amber',
                                        'unpaid' => 'red',
                                        default => 'blue',
                                    };
                                    $label = match ($summary['status']) {
                                        'paid' => __('receivables.paid'),
                                        'partial' => __('receivables.partial'),
                                        'unpaid' => __('receivables.unpaid'),
                                        default => __('receivables.cash_sale'),
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">#{{ $bill->id }}</div>
                                        <div class="text-xs text-gray-500">{{ $bill->products->count() }} {{ __('receivables.items') }}</div>
                                        @if ($bill->note)
                                            <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $bill->note }}">{{ $bill->note }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $bill->customer->name ?? __('bills.Walk-in') }}</div>
                                        <div class="text-xs text-gray-500">{{ $bill->creator->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-2">
                                            <x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
                                            <div class="text-xs text-gray-500">
                                                {{ __('receivables.paid') }}: ₪{{ number_format($summary['paid'], 2) }}<br>
                                                {{ __('receivables.due') }}: ₪{{ number_format($summary['due'], 2) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        ₪{{ number_format($bill->total_price, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ __('messages.' . ucfirst($bill->payment_method ?: 'cash')) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $bill->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('bills.show', $bill) }}"
                                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                {{ __('messages.View') }}
                                            </a>
                                            @if (! $bill->is_returned)
                                                <a href="{{ route('bills.edit', $bill) }}"
                                                    class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-200">
                                                    {{ __('bills.Edit') }}
                                                </a>
                                            @endif
                                            <form action="{{ route('bills.destroy', $bill) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    data-confirm="{{ $confirmText }}"
                                                    class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">
                                                    {{ __('messages.Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12">
                                        <x-ui.empty :title="__('bills.No bills found')" :text="__('bills.Start by creating your first bill.')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $bills->links('vendor.pagination.custom-light') }}
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
