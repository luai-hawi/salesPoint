<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('payables.titles.purchase_bills')" :subtitle="__('payables.subtitles.purchase_bills')">
            <a href="{{ route('purchase-bills.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ __('payables.actions.create_bill') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('payables.actions.filters')">
                <form method="GET" action="{{ route('purchase-bills.index') }}"
                    class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <label for="search" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('payables.fields.search') }}
                        </label>
                        <input id="search" name="search" type="text" value="{{ request('search') }}"
                            placeholder="{{ __('payables.placeholders.search_bills') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="supplier_id" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('payables.fields.supplier') }}
                        </label>
                        <select id="supplier_id" name="supplier_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="">{{ __('payables.placeholders.all_suppliers') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payment_status" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('payables.fields.payment_status') }}
                        </label>
                        <select id="payment_status" name="payment_status"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="">{{ __('payables.placeholders.all_statuses') }}</option>
                            @foreach (['paid', 'partial', 'unpaid'] as $status)
                                <option value="{{ $status }}" @selected(request('payment_status') === $status)>
                                    {{ __('payables.statuses.' . $status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="date_from" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.date_from') }}
                            </label>
                            <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="date_to" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.date_to') }}
                            </label>
                            <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="flex items-end gap-2 lg:col-span-5">
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('payables.actions.apply') }}
                        </button>
                        <a href="{{ route('purchase-bills.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('payables.actions.clear') }}
                        </a>
                    </div>
                </form>
            </x-ui.card>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-ui.stat :label="__('payables.titles.purchase_bills')" :value="$bills->count()" :hint="$bills->total() . ' total'" />
                <x-ui.stat :label="__('payables.fields.amount')" :value="'₪' . number_format($totalAmount, 2)" tone="blue" />
                <x-ui.stat :label="__('payables.fields.remaining')" :value="'₪' . number_format($dueAmount, 2)" tone="red" />
            </div>

            <x-ui.card :title="__('payables.titles.purchase_bills')">
                @if ($bills->count() === 0)
                    <x-ui.empty :title="__('payables.messages.no_bills')" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.bill') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.supplier') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.purchase_date') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.amount') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.actions.record_payment') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.remaining') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.status') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('payables.actions.view') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($bills as $bill)
                                    @php
                                        $summary = $bill->payables_summary;
                                        $tone = $summary['status'] === 'paid' ? 'green' : ($summary['status'] === 'partial' ? 'amber' : 'red');
                                    @endphp
                                    <tr class="align-top">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">#{{ $bill->id }}</div>
                                            @if ($bill->reference_number)
                                                <div class="text-xs text-gray-500">{{ $bill->reference_number }}</div>
                                            @endif
                                            @if ($bill->source === 'stock_intake')
                                                <div class="mt-1">
                                                    <x-ui.badge tone="blue">{{ __('payables.statuses.from_stock_entry') }}</x-ui.badge>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $bill->supplier?->name }}</div>
                                            @if ($bill->supplier?->system_key === \App\Services\SupplierLedger::WALK_IN_KEY)
                                                <div class="mt-1 text-xs text-gray-500">{{ __('payables.statuses.system_supplier') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ optional($bill->purchase_date)->format('Y-m-d') }}</td>
                                        <td class="px-4 py-3 font-semibold text-gray-900">₪{{ number_format((float) $bill->total_amount, 2) }}</td>
                                        <td class="px-4 py-3 text-gray-900">₪{{ number_format($summary['paid'], 2) }}</td>
                                        <td class="px-4 py-3 text-gray-900">
                                            ₪{{ number_format($summary['due'], 2) }}
                                            @if ($summary['overpaid'] > 0)
                                                <div class="text-xs text-green-600">{{ __('payables.messages.overpaid_credit') }}: ₪{{ number_format($summary['overpaid'], 2) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-ui.badge :tone="$tone">{{ __('payables.statuses.' . $summary['status']) }}</x-ui.badge>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('purchase-bills.show', $bill) }}"
                                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                                {{ __('payables.actions.view') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="pt-4">
                        {{ $bills->links() }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
