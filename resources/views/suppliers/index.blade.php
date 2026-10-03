<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('payables.titles.suppliers')" :subtitle="__('payables.subtitles.suppliers')">
            <a href="{{ route('suppliers.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                {{ __('payables.actions.create_supplier') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('payables.actions.filters')">
                <form method="GET" action="{{ route('suppliers.index') }}"
                    class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label for="search" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('payables.fields.search') }}
                        </label>
                        <input id="search" name="search" type="text" value="{{ request('search') }}"
                            placeholder="{{ __('payables.placeholders.search_suppliers') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="balance" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ __('payables.fields.balance_status') }}
                        </label>
                        <select id="balance" name="balance"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="">{{ __('payables.placeholders.all_balances') }}</option>
                            <option value="owed" @selected(request('balance') === 'owed')>{{ __('payables.filters.owed') }}</option>
                            <option value="credit" @selected(request('balance') === 'credit')>{{ __('payables.filters.credit') }}</option>
                            <option value="settled" @selected(request('balance') === 'settled')>{{ __('payables.filters.settled') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('payables.actions.apply') }}
                        </button>
                        <a href="{{ route('suppliers.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('payables.actions.clear') }}
                        </a>
                    </div>
                </form>
            </x-ui.card>

            @php
                $owedCount = $suppliers->getCollection()->where('balance', '>', 0)->count();
                $creditCount = $suppliers->getCollection()->where('balance', '<', 0)->count();
                $settledCount = $suppliers->getCollection()->where('balance', 0)->count();
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('payables.titles.suppliers')" :value="$suppliers->total()" :hint="__('payables.subtitles.suppliers')" />
                <x-ui.stat :label="__('payables.filters.owed')" :value="$owedCount" tone="red" />
                <x-ui.stat :label="__('payables.filters.credit')" :value="$creditCount" tone="green" />
                <x-ui.stat :label="__('payables.filters.settled')" :value="$settledCount" tone="gray" />
            </div>

            <x-ui.card :title="__('payables.titles.suppliers')">
                @if ($suppliers->count() === 0)
                    <x-ui.empty :title="__('payables.messages.no_suppliers')">
                        <a href="{{ route('suppliers.create') }}"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('payables.actions.create_supplier') }}
                        </a>
                    </x-ui.empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.name') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.phone') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.balance') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.status') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('payables.fields.notes') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('payables.actions.view') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($suppliers as $supplier)
                                    @php
                                        $balance = (float) $supplier->balance;
                                        $tone = $balance > 0 ? 'red' : ($balance < 0 ? 'green' : 'gray');
                                        $status = $balance > 0 ? __('payables.statuses.we_owe') : ($balance < 0 ? __('payables.statuses.supplier_owes') : __('payables.statuses.settled'));
                                    @endphp
                                    <tr class="align-top">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900">{{ $supplier->name }}</div>
                                            @if ($supplier->email)
                                                <div class="text-xs text-gray-500">{{ $supplier->email }}</div>
                                            @endif
                                            @if ($supplier->system_key === \App\Services\SupplierLedger::WALK_IN_KEY)
                                                <div class="mt-1">
                                                    <x-ui.badge tone="blue">{{ __('payables.statuses.system_supplier') }}</x-ui.badge>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $supplier->phone ?: '—' }}</td>
                                        <td class="px-4 py-3 font-semibold text-gray-900">₪{{ number_format(abs($balance), 2) }}</td>
                                        <td class="px-4 py-3">
                                            <x-ui.badge :tone="$tone">{{ $status }}</x-ui.badge>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $supplier->notes ? \Illuminate\Support\Str::limit($supplier->notes, 70) : '—' }}</td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('suppliers.edit', $supplier) }}"
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
                        {{ $suppliers->links() }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
