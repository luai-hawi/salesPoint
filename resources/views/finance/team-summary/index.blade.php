<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.team_summary.title')" :subtitle="__('finance.team_summary.subtitle')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <x-ui.card>
                <form method="GET" action="{{ route('finance.team-summary.index') }}" class="grid gap-4 lg:grid-cols-3">
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label><input type="date" name="from" value="{{ $fromDate }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label><input type="date" name="to" value="{{ $toDate }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div class="flex items-end"><button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.common.filter') }}</button></div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('finance.team_summary.title')">
                <div class="grid gap-4 lg:hidden">
                    @foreach ($summary['rows'] as $row)
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-center justify-between">
                                <div class="font-semibold text-gray-900">{{ $row['user']->name }}</div>
                                <a href="{{ route('finance.team-summary.index', ['from' => $fromDate, 'to' => $toDate, 'user_id' => $row['user']->id]) }}" class="text-sm font-semibold text-indigo-600">{{ __('finance.team_summary.drill_down') }}</a>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-gray-600">
                                <div>{{ __('finance.team_summary.bills_count') }}: {{ $row['bills_count'] }}</div>
                                <div>{{ __('finance.team_summary.sales_total') }}: ₪{{ number_format($row['sales_total'], 2) }}</div>
                                <div>{{ __('finance.team_summary.collections') }}: ₪{{ number_format($row['collections'], 2) }}</div>
                                <div>{{ __('finance.team_summary.deleted_bills') }}: {{ $row['deleted_bills'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.user') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.bills_count') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.sales_total') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.returns') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.discounts') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.damaged') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.deleted_bills') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.collections') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.expense_entries') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.team_summary.drill_down') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($summary['rows'] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['user']->name }}</td>
                                    <td class="px-3 py-2">{{ $row['bills_count'] }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['sales_total'], 2) }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['returns_total'], 2) }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['discounts'], 2) }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['damaged_total'], 2) }}</td>
                                    <td class="px-3 py-2">{{ $row['deleted_bills'] }}</td>
                                    <td class="px-3 py-2">₪{{ number_format($row['collections'], 2) }}</td>
                                    <td class="px-3 py-2">{{ $row['expense_entries'] }}</td>
                                    <td class="px-3 py-2"><a href="{{ route('finance.team-summary.index', ['from' => $fromDate, 'to' => $toDate, 'user_id' => $row['user']->id]) }}" class="font-semibold text-indigo-600">{{ __('finance.team_summary.drill_down') }}</a></td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 font-semibold">
                                <td class="px-3 py-2">{{ __('finance.team_summary.totals_row') }}</td>
                                <td class="px-3 py-2">{{ $summary['totals']['bills_count'] }}</td>
                                <td class="px-3 py-2">₪{{ number_format($summary['totals']['sales_total'], 2) }}</td>
                                <td class="px-3 py-2">₪{{ number_format($summary['totals']['returns_total'], 2) }}</td>
                                <td class="px-3 py-2">₪{{ number_format($summary['totals']['discounts'], 2) }}</td>
                                <td class="px-3 py-2">₪{{ number_format($summary['totals']['damaged_total'], 2) }}</td>
                                <td class="px-3 py-2">{{ $summary['totals']['deleted_bills'] }}</td>
                                <td class="px-3 py-2">₪{{ number_format($summary['totals']['collections'], 2) }}</td>
                                <td class="px-3 py-2">{{ $summary['totals']['expense_entries'] }}</td>
                                <td class="px-3 py-2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            @if ($drillUser)
                <div class="grid gap-6 xl:grid-cols-2">
                    <x-ui.card :title="__('finance.team_summary.recent_bills') . ' - ' . $drillUser->name">
                        <div class="space-y-2 text-sm">
                            @forelse ($drillBills as $bill)
                                <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2">
                                    <span>#{{ $bill->id }}</span>
                                    <span>₪{{ number_format((float) $bill->total_price, 2) }}</span>
                                </div>
                            @empty
                                <x-ui.empty :title="__('finance.common.no_data')" />
                            @endforelse
                        </div>
                    </x-ui.card>
                    <x-ui.card :title="__('finance.team_summary.recent_activity') . ' - ' . $drillUser->name">
                        <div class="space-y-2 text-sm">
                            @forelse ($drillActivity as $entry)
                                <div class="rounded-lg bg-gray-50 px-3 py-2">
                                    <div class="font-medium text-gray-900">{{ $entry->describe() }}</div>
                                    <div class="text-xs text-gray-500">{{ $entry->created_at }}</div>
                                </div>
                            @empty
                                <x-ui.empty :title="__('finance.common.no_data')" />
                            @endforelse
                        </div>
                    </x-ui.card>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
