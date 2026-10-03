<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.dashboard.title')" :subtitle="__('finance.dashboard.subtitle')">
            <a href="{{ route('dashboard.export-data', array_merge(request()->query(), ['start_date' => $startDate, 'end_date' => $endDate])) }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('finance.common.export') }}
            </a>
            <a href="{{ route('dashboard.financial.print-report', array_merge(request()->query(), ['start_date' => $startDate, 'end_date' => $endDate, 'popup' => 1])) }}"
                target="_blank"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ __('finance.common.print') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" action="{{ route('dashboard.financial') }}" class="grid gap-4 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.period') }}</label>
                        <select name="cash_preset" class="w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['today', 'yesterday', 'this_week', 'this_month', 'custom'] as $preset)
                                <option value="{{ $preset }}" @selected($cashPeriod['preset'] === $preset)>{{ __('finance.common.' . $preset) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.cash_drawer.drawer_mode') }}</label>
                        <select name="cash_method" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="cash">{{ __('finance.cash_drawer.drawer_only') }}</option>
                            <option value="all" @selected(request('cash_method') === 'all')>{{ __('finance.cash_drawer.all_methods') }}</option>
                            @foreach (['card', 'transfer', 'check'] as $method)
                                <option value="{{ $method }}" @selected(request('cash_method') === $method)>{{ __('finance.methods.' . $method) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('finance.common.filter') }}
                        </button>
                    </div>
                    @foreach (['cash_from' => 'from', 'cash_to' => 'to'] as $field => $label)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.dashboard.cash_flow') }} · {{ __('finance.common.' . $label) }}</label>
                            <input type="date" name="{{ $field }}" value="{{ request($field, $cashPeriod[$label . '_date']) }}" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    @endforeach
                </form>
            </x-ui.card>

            @php($fmt = fn ($value) => '₪' . number_format((float) $value, 2))
            <div class="space-y-2">
                <p class="text-xs text-gray-500">{{ __('charts.finance.compare_hint', ['period' => $charts['compare_period']]) }}</p>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <x-ui.kpi :label="__('finance.dashboard.revenue')" :value="$fmt($summary['revenue'])" icon="cash" tone="green"
                        :delta="$charts['deltas']['revenue']" :hint="__('charts.admin.bills_count', ['count' => $profitLoss['bills_count']])" />
                    <x-ui.kpi :label="__('finance.dashboard.profit')" :value="$fmt($summary['profit'])" icon="trend" tone="indigo"
                        :delta="$charts['deltas']['profit']" :hint="__('charts.margin') . ': ' . $profitLoss['gross_margin'] . '%'" />
                    <x-ui.kpi :label="__('finance.dashboard.net_income')" :value="$fmt($summary['net_income'])" icon="wallet" :tone="$summary['net_income'] < 0 ? 'red' : 'purple'"
                        :delta="$charts['deltas']['net']" :hint="__('charts.finance.net_margin') . ': ' . $profitLoss['net_margin'] . '%'" />
                    <x-ui.kpi :label="__('charts.avg_bill')" :value="$fmt($profitLoss['average_bill'])" icon="receipt" tone="blue"
                        :delta="$charts['deltas']['average_bill']" />
                    <x-ui.kpi :label="__('finance.restored.purchases')" :value="$fmt($details['purchases'])" icon="box" tone="gray"
                        :delta="$charts['deltas']['purchases']" invert />
                    <x-ui.kpi :label="__('finance.dashboard.expenses')" :value="$fmt($summary['expenses'])" icon="down" tone="amber"
                        :delta="$charts['deltas']['expenses']" invert />
                    <x-ui.kpi :label="__('finance.dashboard.staff_payments')" :value="$fmt($summary['staff_payments'])" icon="users" tone="amber"
                        :delta="$charts['deltas']['staff']" invert />
                    <x-ui.kpi :label="__('finance.day_close.discounts')" :value="$fmt($summary['discounts'])" icon="percent" tone="blue"
                        :delta="$charts['deltas']['discounts']" invert />
                    <x-ui.kpi :label="__('finance.day_close.returns')" :value="$fmt($profitLoss['returns'])" icon="return" tone="red"
                        :delta="$charts['deltas']['returns']" invert :hint="$charts['return_rate'] === null ? null : __('charts.finance.return_rate') . ': ' . $charts['return_rate'] . '%'" />
                    <x-ui.kpi :label="__('finance.dashboard.damaged_loss')" :value="$fmt($summary['damaged_loss'])" icon="box" tone="red"
                        :delta="$charts['deltas']['damaged']" invert />
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card :title="__('charts.finance.revenue_profit')" :subtitle="$charts['by_month'] ? __('charts.finance.by_month') : __('charts.finance.by_day')" class="xl:col-span-2">
                    <x-ui.chart type="bar" :height="300" :label="__('charts.finance.revenue_profit')"
                        :labels="$charts['trend']['labels']"
                        :datasets="[
                            ['label' => __('finance.dashboard.revenue'), 'data' => $charts['trend']['revenue'], 'color' => '#6366f1'],
                            ['label' => __('finance.dashboard.profit'), 'type' => 'line', 'data' => $charts['trend']['profit'], 'color' => '#10b981', 'fill' => false],
                            ['label' => __('finance.day_close.returns'), 'type' => 'line', 'data' => $charts['trend']['returns'], 'color' => '#f43f5e', 'fill' => false],
                        ]" />
                </x-ui.card>
                <x-ui.card :title="__('charts.finance.where_money_went')" :subtitle="__('charts.finance.where_money_went_hint')">
                    <x-ui.chart type="bar" :height="300" horizontal :legend="false" :label="__('charts.finance.where_money_went')"
                        :labels="$charts['waterfall']['labels']"
                        :datasets="[[
                            'label' => __('finance.common.amount'),
                            'data' => $charts['waterfall']['data'],
                            'colors' => collect($charts['waterfall']['data'])->map(fn ($value, $i) => $i === 0 ? '#6366f1' : (in_array($i, [3, 7], true) ? ($value < 0 ? '#e11d48' : '#10b981') : '#f59e0b'))->all(),
                        ]]" />
                </x-ui.card>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card :title="__('charts.finance.money_in_out')" :subtitle="__('charts.finance.money_in_out_hint')" class="xl:col-span-2">
                    <x-ui.chart type="bar" :height="280" :label="__('charts.finance.money_in_out')"
                        :labels="$charts['trend']['labels']"
                        :datasets="[
                            ['label' => __('finance.restored.money_in'), 'data' => $charts['trend']['money_in'], 'color' => '#10b981'],
                            ['label' => __('finance.restored.money_out'), 'data' => $charts['trend']['money_out'], 'color' => '#f43f5e'],
                            ['label' => __('finance.restored.purchases'), 'type' => 'line', 'data' => $charts['trend']['purchases'], 'color' => '#64748b', 'fill' => false],
                        ]" />
                </x-ui.card>
                <x-ui.card :title="__('charts.finance.costs_breakdown')" :subtitle="__('charts.finance.costs_breakdown_hint')">
                    <x-ui.chart type="doughnut" :height="280" :label="__('charts.finance.costs_breakdown')"
                        :labels="$charts['costs']['labels']"
                        :datasets="[['label' => __('finance.common.amount'), 'data' => $charts['costs']['data']]]" />
                </x-ui.card>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card :title="__('charts.finance.top_products')" :subtitle="__('charts.finance.top_products_hint')" class="xl:col-span-2">
                    <x-ui.chart type="bar" :height="300" horizontal :label="__('charts.finance.top_products')"
                        :labels="$charts['top_products']['labels']"
                        :datasets="[
                            ['label' => __('finance.dashboard.profit'), 'data' => $charts['top_products']['profit'], 'color' => '#10b981'],
                            ['label' => __('finance.dashboard.revenue'), 'data' => $charts['top_products']['revenue'], 'color' => '#c7d2fe'],
                        ]" />
                    @if (Route::has('reports.index') && (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_reports')))
                        <div class="mt-3 text-end">
                            <a href="{{ route('reports.index') }}#product-reports" class="text-sm font-semibold text-indigo-700 hover:underline">{{ __('charts.finance.all_product_reports') }} →</a>
                        </div>
                    @endif
                </x-ui.card>
                <x-ui.card :title="__('charts.finance.team_sales')" :subtitle="$startDate . ' → ' . $endDate">
                    <x-ui.chart type="doughnut" :height="300" :label="__('charts.finance.team_sales')"
                        :labels="$charts['team']['labels']"
                        :datasets="[['label' => __('finance.dashboard.revenue'), 'data' => $charts['team']['data']]]" />
                </x-ui.card>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card :title="__('finance.dashboard.data_health')" :subtitle="__('finance.common.period') . ': ' . $startDate . ' → ' . $endDate">
                    <div class="space-y-3">
                        @foreach ($summary['data_health'] as $key => $count)
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm text-gray-700">{{ __('finance.health.' . $key) }}</div>
                                <x-ui.badge :tone="$count ? 'red' : 'green'">{{ $count }}</x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('finance.dashboard.cash_flow')">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span>{{ __('finance.dashboard.settlement_in') }}</span><strong>₪{{ number_format($summary['cash_flow']['settlement']['cash_in'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>{{ __('finance.dashboard.settlement_out') }}</span><strong>₪{{ number_format($summary['cash_flow']['settlement']['cash_out'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>{{ __('finance.dashboard.cash_drawer_balance') }}</span><strong>{{ $summary['cash_flow']['cash_drawer']['closing_balance'] === null ? '—' : '₪' . number_format($summary['cash_flow']['cash_drawer']['closing_balance'], 2) }}</strong></div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('finance.cash-drawer.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('finance.dashboard.cash_drawer') }}</a>
                        <a href="{{ route('finance.day-close.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('finance.day_close.title') }}</a>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('finance.dashboard.balances_summary')">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span>{{ __('finance.reports.receivables_aging') }}</span><strong>₪{{ number_format($balances['receivables'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>{{ __('finance.reports.payables_aging') }}</span><strong>₪{{ number_format($balances['payables'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>{{ __('finance.reports.inventory_valuation') }}</span><strong>₪{{ number_format($balances['inventory_value'], 2) }}</strong></div>
                        <div class="flex justify-between"><span>{{ __('finance.common.opening_balance') }}</span><strong>{{ $balances['cash_estimate'] === null ? '—' : '₪' . number_format($balances['cash_estimate'], 2) }}</strong></div>
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('finance.restored.team_today')">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($summary['team_today']['rows'] as $row)
                        <div class="rounded-lg bg-gray-50 p-3"><p>{{ $row['user']->name }}</p><strong>{{ number_format($row['sales_total'], 2) }}</strong> · {{ $row['bills_count'] }} {{ __('finance.restored.count') }}</div>
                    @endforeach
                </div>
            </x-ui.card>

            @include('dashboard.partials.financial-details')
            @include('dashboard.partials.financial-cash-flow')

            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('finance.dashboard.profit_loss')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-sm text-gray-500">{{ __('finance.reports.profit_loss') }}</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">₪{{ number_format($profitLoss['net_profit'], 2) }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-sm text-gray-500">{{ __('finance.day_close.discounts') }}</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">₪{{ number_format($profitLoss['discounts'], 2) }}</div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('reports.index') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('finance.reports.title') }}
                        </a>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('finance.dashboard.recent_closings')">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ __('finance.common.date') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('finance.day_close.expected_cash') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('finance.day_close.counted_cash') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('finance.day_close.variance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse ($recentClosings as $closing)
                                    <tr>
                                        <td class="px-3 py-2">{{ $closing->closing_date?->format('Y-m-d') }}</td>
                                        <td class="px-3 py-2">₪{{ number_format((float) $closing->expected_cash, 2) }}</td>
                                        <td class="px-3 py-2">₪{{ number_format((float) $closing->counted_cash, 2) }}</td>
                                        <td class="px-3 py-2">
                                            <x-ui.badge :tone="$closing->variance == 0 ? 'green' : 'amber'">
                                                ₪{{ number_format((float) $closing->variance, 2) }}
                                            </x-ui.badge>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>
