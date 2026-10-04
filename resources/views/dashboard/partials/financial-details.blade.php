<div class="space-y-6">
    <x-ui.card :title="__('finance.restored.inventory')">
        <p class="mb-4 text-sm text-gray-500">{{ __('finance.restored.current_inventory') }}</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (['cost' => $details['inventory']->cost, 'selling' => $details['inventory']->selling,
                'potential_profit' => $details['inventory']->selling - $details['inventory']->cost,
                'units' => $details['inventory']->units, 'products' => $details['inventory']->products] as $key => $value)
                <div><p class="text-sm text-gray-500">{{ __('finance.restored.' . $key) }}</p><strong class="text-xl">{{ number_format($value, 2) }}</strong></div>
            @endforeach
        </div>
    </x-ui.card>
    <x-ui.card :title="__('messages.Capital')">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>{{ __('messages.Total Capital') }}: <strong>{{ number_format($details['capital']->sum('amount'), 2) }}</strong></div>
            <div>{{ __('messages.Products Cost') }}: <strong>{{ number_format($details['inventory']->cost, 2) }}</strong></div>
            <div>{{ __('finance.restored.capital_difference') }}: <strong>{{ number_format($details['capital']->sum('amount') - $details['inventory']->cost, 2) }}</strong></div>
        </div>
        <p class="mt-2 text-sm text-gray-500">{{ __('finance.restored.capital_note') }}</p>
        @if (! ($printing ?? false))
            <form method="POST" action="{{ route('capital.store') }}" class="mt-4 grid gap-3 sm:grid-cols-4 no-print">
                @csrf
                <input type="number" name="amount" min="0.01" step="0.01" required placeholder="{{ __('finance.common.amount') }}" aria-label="{{ __('finance.common.amount') }}" class="rounded-lg border-gray-300">
                <input type="date" name="entry_date" value="{{ \App\Support\ShopTime::today(auth()->user()->ownerId()) }}" required aria-label="{{ __('finance.common.date') }}" class="rounded-lg border-gray-300">
                <input name="note" maxlength="1000" placeholder="{{ __('finance.common.notes') }}" aria-label="{{ __('finance.common.notes') }}" class="rounded-lg border-gray-300">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">{{ __('messages.Add Capital') }}</button>
            </form>
        @endif
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr><th class="p-2 text-start">{{ __('finance.common.date') }}</th><th class="p-2 text-start">{{ __('finance.common.amount') }}</th><th class="p-2 text-start">{{ __('finance.common.notes') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($details['capital'] as $entry)
                    <tr class="border-t"><td class="p-2">{{ $entry->entry_date->format('Y-m-d') }}</td><td class="p-2">{{ number_format($entry->amount, 2) }}</td><td class="p-2">{{ $entry->note }}</td><td>
                        @if (! ($printing ?? false))
                            <form method="POST" action="{{ route('capital.destroy', $entry) }}">@csrf @method('DELETE')
                                <button class="text-red-700">{{ __('finance.common.delete') }}</button>
                            </form>
                        @endif
                    </td></tr>
                @empty
                    <tr><td colspan="4" class="p-3 text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($details['balances'] as $key => $group)
            <x-ui.card :title="__('finance.restored.' . $key)">
                <p class="mb-3 text-xl font-semibold">{{ number_format($group['total'], 2) }}</p>
                <p class="mb-2 text-sm text-gray-500">{{ __('finance.restored.top_ten_current') }}</p>
                <table class="w-full text-sm">
                    <tbody>@forelse ($group['rows'] as $row)
                        <tr class="border-t"><td class="p-2">{{ $row->name }}</td><td class="p-2">{{ $row->phone }}</td><td class="p-2">{{ number_format(abs($row->balance), 2) }}</td></tr>
                    @empty
                        <tr><td class="p-2">{{ __('finance.common.no_data') }}</td></tr>
                    @endforelse</tbody>
                </table>
            </x-ui.card>
        @endforeach
    </div>
    @foreach ($details['tables'] as $key => $table)
        @continue($key === 'staff_breakdown' && ! auth()->user()->canAccessFeature('hr'))
        <x-ui.card :title="__('finance.restored.' . $key)">
            @if ($key === 'trends' && ($printing ?? false) && $table['rows']->isNotEmpty())
                <div class="mb-4 grid gap-4 sm:grid-cols-2">
                    @foreach (['revenue', 'profit'] as $metric)
                        @php
                            $values = $table['rows']->pluck($metric);
                            $min = min(0, $values->min());
                            $range = max(1, $values->max() - $min);
                            $points = $values->map(fn ($value, $i) => (20 + ($i * 560 / max(1, $values->count() - 1))) . ',' . (160 - (($value - $min) / $range * 140)))->implode(' ');
                        @endphp
                        <div>
                            <p class="text-sm font-semibold">{{ __('finance.restored.' . $metric) }}</p>
                            <svg viewBox="0 0 600 180" role="img" aria-label="{{ __('finance.restored.' . $metric) }}" class="w-full">
                                <line x1="20" y1="160" x2="580" y2="160" stroke="#d1d5db" />
                                <polyline points="{{ $points }}" fill="none" stroke="#4f46e5" stroke-width="3" />
                                @foreach ($values as $i => $value)
                                    <circle cx="{{ 20 + ($i * 560 / max(1, $values->count() - 1)) }}" cy="{{ 160 - (($value - $min) / $range * 140) }}" r="3" fill="#4f46e5"><title>{{ $table['rows'][$i]['date'] }}: {{ number_format($value, 2) }}</title></circle>
                                @endforeach
                            </svg>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50"><tr>@foreach ($table['columns'] as $column)<th class="p-3 text-start">{{ __('finance.restored.' . $column) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @forelse ($table['rows'] as $row)
                        <tr class="border-t">@foreach ($table['columns'] as $column)
                            @php($value = data_get($row, $column))
                            <td class="p-3">{{ $value === null ? '—' : (is_numeric($value) && !in_array($column, ['id', 'count'], true) ? number_format($value, 2) . ($column === 'growth' ? '%' : '') : $value) }}</td>
                        @endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($table['columns']) }}" class="p-3 text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($key !== 'growth' && $table['rows']->isNotEmpty())
                        <tfoot class="border-t bg-gray-50 font-semibold"><tr>
                            @foreach ($table['columns'] as $column)
                                <td class="p-3">
                                    @if ($loop->first) {{ __('finance.restored.displayed_total') }}
                                    @elseif (! in_array($column, ['id', 'name', 'date', 'metric'], true))
                                        {{ number_format($table['rows']->sum(fn ($row) => (float) data_get($row, $column)), 2) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </x-ui.card>
    @endforeach
    @if (auth()->user()->canAccessFeature('team_activity'))
    <x-ui.card :title="__('finance.dashboard.team_summary')">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr><th class="p-3 text-start">{{ __('finance.common.user') }}</th>@foreach (['bills_count', 'sales_total', 'returns_total', 'discounts', 'collections'] as $key)<th class="p-3 text-start">{{ __('finance.team_summary.' . ($key === 'returns_total' ? 'returns' : $key)) }}</th>@endforeach</tr></thead>
                <tbody>@foreach ($details['team']['rows'] as $row)
                    <tr class="border-t"><td class="p-3">{{ $row['user']->name }}</td>@foreach (['bills_count', 'sales_total', 'returns_total', 'discounts', 'collections'] as $key)<td class="p-3">{{ number_format($row[$key], 2) }}</td>@endforeach</tr>
                @endforeach</tbody>
            </table>
        </div>
    </x-ui.card>
    @endif
</div>
