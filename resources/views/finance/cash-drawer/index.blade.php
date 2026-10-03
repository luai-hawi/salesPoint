<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.cash_drawer.title')" :subtitle="__('finance.cash_drawer.subtitle')">
            <a href="{{ route('finance.cash-drawer.export', request()->query()) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.common.export') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" action="{{ route('finance.cash-drawer.index') }}" class="grid gap-4 lg:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.period') }}</label>
                        <select name="preset" class="w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['today', 'yesterday', 'this_week', 'this_month', 'custom'] as $preset)
                                <option value="{{ $preset }}" @selected($period['preset'] === $preset)>{{ __('finance.common.' . $preset) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label>
                        <input type="date" name="from" value="{{ $period['from_date'] }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label>
                        <input type="date" name="to" value="{{ $period['to_date'] }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.cash_drawer.drawer_mode') }}</label>
                        <select name="method" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="cash" @selected($data['method_filter'] === 'cash')>{{ __('finance.cash_drawer.drawer_only') }}</option>
                            <option value="all" @selected($data['method_filter'] === 'all')>{{ __('finance.cash_drawer.all_methods') }}</option>
                            @foreach (['card', 'transfer', 'check'] as $method)
                                <option value="{{ $method }}" @selected($data['method_filter'] === $method)>{{ __('finance.methods.' . $method) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.common.filter') }}</button>
                    </div>
                </form>
            </x-ui.card>

            <div class="grid gap-4 md:grid-cols-4">
                <x-ui.card><div class="text-sm text-gray-500">{{ __('finance.common.opening_balance') }}</div><div class="mt-2 text-2xl font-bold">{{ $data['opening_balance'] === null ? '—' : '₪' . number_format($data['opening_balance'], 2) }}</div></x-ui.card>
                <x-ui.card><div class="text-sm text-gray-500">{{ $data['method_filter'] === 'all' ? __('finance.dashboard.settlement_in') : __('finance.dashboard.cash_in') }}</div><div class="mt-2 text-2xl font-bold">₪{{ number_format($data['totals']['in'], 2) }}</div></x-ui.card>
                <x-ui.card><div class="text-sm text-gray-500">{{ $data['method_filter'] === 'all' ? __('finance.dashboard.settlement_out') : __('finance.dashboard.cash_out') }}</div><div class="mt-2 text-2xl font-bold">₪{{ number_format($data['totals']['out'], 2) }}</div></x-ui.card>
                <x-ui.card><div class="text-sm text-gray-500">{{ __('finance.dashboard.cash_drawer_balance') }}</div><div class="mt-2 text-2xl font-bold">{{ $data['cash_closing_balance'] === null ? '—' : '₪' . number_format($data['cash_closing_balance'], 2) }}</div></x-ui.card>
            </div>

            @if ($data['opening_notice'])
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $data['opening_notice'] }}</div>
            @endif

            <x-ui.card :title="__('finance.cash_drawer.add_movement')">
                <form method="POST" action="{{ route('finance.cash-drawer.store') }}" class="grid gap-4 lg:grid-cols-5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.cash_drawer.movement_type') }}</label>
                        <select name="type" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="opening">{{ __('finance.cash_drawer.opening_float') }}</option>
                            <option value="in">{{ __('finance.cash_drawer.money_in') }}</option>
                            <option value="out">{{ __('finance.cash_drawer.money_out') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="w-full rounded-lg border-gray-300 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.reason') }}</label>
                        <input type="text" name="reason" class="w-full rounded-lg border-gray-300 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.cash_drawer.occurred_at') }}</label>
                        <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.notes') }}</label>
                        <input type="text" name="note" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="lg:col-span-5">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.common.save') }}</button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('finance.cash_drawer.title')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.date') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.cash_drawer.category') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.cash_drawer.method') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.cash_drawer.document') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.user') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.cash_drawer.amount_in') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.cash_drawer.amount_out') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.cash_drawer.running_balance') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($data['rows'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ \App\Support\ShopTime::local($row['occurred_at'], auth()->user()->ownerId())->format('Y-m-d H:i') }}</td>
                                    <td class="px-3 py-2">{{ __('finance.cash_drawer.categories.' . $row['category']) }}</td>
                                    <td class="px-3 py-2">{{ __('finance.methods.' . $row['method']) }}</td>
                                    <td class="px-3 py-2">{{ $row['document_label'] }}</td>
                                    <td class="px-3 py-2">{{ $row['actor_name'] }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['amount_in'], 2) }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['amount_out'], 2) }}</td>
                                    <td class="px-3 py-2">{{ $row['running_balance'] === null ? '—' : '₪' . number_format($row['running_balance'], 2) }}</td>
                                    <td class="px-3 py-2">
                                        @if ($row['document_type'] === 'cash_movement')
                                            <form method="POST" action="{{ route('finance.cash-drawer.destroy', $row['document']['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">{{ __('finance.common.delete') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $data['rows']->links() }}</div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
