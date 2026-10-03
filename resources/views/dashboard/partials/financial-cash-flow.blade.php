<x-ui.card :title="__('finance.dashboard.cash_flow')">
    <p class="mb-4 text-sm text-gray-500">{{ $cashDrawer['from_date'] ?? ($cashPeriod['from_date'] ?? $startDate) }} — {{ $cashDrawer['to_date'] ?? ($cashPeriod['to_date'] ?? $endDate) }}</p>
    <div class="grid gap-3 sm:grid-cols-3">
        <div>{{ __('finance.dashboard.cash_in') }}: <strong>{{ number_format($cashDrawer['totals']['in'], 2) }}</strong></div>
        <div>{{ __('finance.dashboard.cash_out') }}: <strong>{{ number_format($cashDrawer['totals']['out'], 2) }}</strong></div>
        <div>{{ __('finance.common.closing_balance') }}: <strong>{{ $cashDrawer['closing_balance'] === null ? '—' : number_format($cashDrawer['closing_balance'], 2) }}</strong></div>
    </div>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        @foreach (collect($cashDrawer['rows'])->groupBy('category') as $category => $rows)
            <div class="flex justify-between gap-3 rounded-lg bg-gray-50 p-3 text-sm">
                <span>{{ __('finance.cash_drawer.categories.' . $category) }}</span>
                <strong>+{{ number_format($rows->sum('amount_in'), 2) }} / -{{ number_format($rows->sum('amount_out'), 2) }}</strong>
            </div>
        @endforeach
    </div>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr>
                @foreach (['date' => 'finance.common.date', 'category' => 'finance.cash_drawer.category', 'method' => 'finance.cash_drawer.method', 'document' => 'finance.cash_drawer.document', 'in' => 'finance.cash_drawer.amount_in', 'out' => 'finance.cash_drawer.amount_out', 'balance' => 'finance.cash_drawer.running_balance'] as $label)
                    <th class="p-3 text-start">{{ __($label) }}</th>
                @endforeach
            </tr></thead>
            <tbody>@forelse ($cashDrawer['rows'] as $row)
                <tr class="border-t">
                    <td class="p-3">{{ \App\Support\ShopTime::local($row['occurred_at'], auth()->user()->ownerId())->format('Y-m-d H:i') }}</td>
                    <td class="p-3">{{ __('finance.cash_drawer.categories.' . $row['category']) }}</td>
                    <td class="p-3">{{ __('finance.methods.' . $row['method']) }}</td>
                    <td class="p-3">{{ $row['document_label'] }}</td>
                    <td class="p-3">{{ number_format($row['amount_in'], 2) }}</td>
                    <td class="p-3">{{ number_format($row['amount_out'], 2) }}</td>
                    <td class="p-3">{{ $row['running_balance'] === null ? '—' : number_format($row['running_balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-3">{{ __('finance.common.no_data') }}</td></tr>
            @endforelse</tbody>
        </table>
    </div>
</x-ui.card>
