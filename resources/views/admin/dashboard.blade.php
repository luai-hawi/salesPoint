<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.dashboard')" :subtitle="__('charts.admin.subtitle')">
            <a href="{{ route('admin.shop-owners.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.titles.create_shop') }}</a>
            <a href="{{ route('admin.dashboard', ['refresh' => 1]) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.refresh') }}</a>
            <a href="{{ route('admin.dashboard.download-backup') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.download_backup') }}</a>
            @if (Route::has('admin.storage.index'))
                <a href="{{ route('admin.storage.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.open_storage') }}</a>
            @endif
        </x-ui.page-header>
    </x-slot>

    @php($money = fn ($value) => number_format((float) $value, 2))

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                @foreach ([
                    ['key' => 'all', 'value' => $statusCounts['all'], 'tone' => 'gray'],
                    ['key' => 'active', 'value' => $statusCounts['active'], 'tone' => 'green'],
                    ['key' => 'has_to_pay', 'value' => $statusCounts['has_to_pay'], 'tone' => 'amber'],
                    ['key' => 'trial', 'value' => $statusCounts['trial'], 'tone' => 'blue'],
                    ['key' => 'trial_ended', 'value' => $statusCounts['trial_ended'], 'tone' => 'red'],
                    ['key' => 'needs_attention', 'value' => $statusCounts['needs_attention'], 'tone' => 'purple'],
                ] as $card)
                    <a href="{{ route('admin.shop-owners.index', ['status' => $card['key'] === 'all' ? null : $card['key']]) }}" class="block">
                        <x-ui.stat :label="__('admin.filters.' . $card['key'])" :value="$card['value']" :tone="$card['tone']" />
                    </a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.kpi :label="__('admin.dashboard.today_sales')" :value="$money($kpis['sales_today'])" icon="cash" tone="green"
                    :hint="__('charts.admin.bills_count', ['count' => $kpis['bills_today']])" />
                <x-ui.kpi :label="__('admin.dashboard.today_profit')" :value="$money($kpis['profit_today'])" icon="trend" tone="indigo" />
                <x-ui.kpi :label="__('admin.dashboard.month_sales')" :value="$money($kpis['sales_month'])" icon="receipt" tone="blue"
                    :delta="$kpis['sales_growth']" />
                <x-ui.kpi :label="__('admin.dashboard.month_profit')" :value="$money($kpis['profit_month'])" icon="wallet" tone="purple"
                    :delta="$kpis['profit_growth']" />
                <x-ui.kpi :label="__('charts.gross_margin')" :value="$kpis['margin_month'] === null ? '—' : $kpis['margin_month'] . '%'" icon="percent" tone="amber"
                    :hint="__('charts.this_month')" />
                <x-ui.kpi :label="__('charts.avg_bill')" :value="$money($kpis['avg_bill_month'])" icon="receipt" tone="gray"
                    :hint="__('charts.admin.bills_count', ['count' => $kpis['bills_month']])" />
                <x-ui.kpi :label="__('charts.returns')" :value="$money($kpis['returns_month'])" icon="return" tone="red"
                    :hint="__('charts.this_month')" />
                <x-ui.kpi :label="__('charts.admin.selling_shops')" :value="$kpis['selling_shops'] . ' / ' . $statusCounts['all']" icon="users" tone="green"
                    :hint="__('charts.admin.selling_shops_hint')" />
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <x-ui.card :title="__('charts.admin.sales_profit_30')" :subtitle="__('charts.admin.all_shops')" class="xl:col-span-2">
                    <x-ui.chart type="bar" :height="300" :label="__('charts.admin.sales_profit_30')"
                        :labels="collect($salesChartData)->pluck('date')->all()"
                        :datasets="[
                            ['label' => __('charts.sales'), 'data' => collect($salesChartData)->pluck('sales')->all(), 'color' => '#6366f1'],
                            ['label' => __('charts.gross_profit'), 'type' => 'line', 'data' => collect($salesChartData)->pluck('profit')->all(), 'color' => '#10b981', 'fill' => false],
                        ]" />
                </x-ui.card>
                <x-ui.card :title="__('charts.admin.sales_share')" :subtitle="__('charts.this_month')">
                    <x-ui.chart type="doughnut" :height="300" :label="__('charts.admin.sales_share')"
                        :labels="$salesShare['labels']"
                        :datasets="[['label' => __('charts.sales'), 'data' => $salesShare['data']]]" />
                </x-ui.card>
            </div>

            <x-ui.card :title="__('charts.admin.shop_performance')" :subtitle="__('charts.admin.shop_performance_hint')" :padding="false">
                <div x-data="adminShopPerformance()" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3 px-4 pt-4 sm:px-5">
                        <input type="search" x-model="query" placeholder="{{ __('charts.admin.search_shop') }}" aria-label="{{ __('charts.admin.search_shop') }}"
                            class="w-full rounded-lg border-gray-300 text-sm sm:w-72">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" x-model="onlySelling" class="rounded border-gray-300 text-indigo-600">
                            {{ __('charts.admin.only_selling') }}
                        </label>
                        <span class="text-xs text-gray-400">{{ __('charts.admin.click_to_sort') }}</span>
                    </div>
                    <div class="max-h-[34rem] overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ __('admin.fields.shop_name') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('admin.fields.status') }}</th>
                                    @foreach ([
                                        'sales_today' => __('admin.dashboard.today_sales'),
                                        'profit_today' => __('admin.dashboard.today_profit'),
                                        'sales_month' => __('admin.dashboard.month_sales'),
                                        'profit_month' => __('admin.dashboard.month_profit'),
                                        'margin' => __('charts.margin'),
                                        'bills_month' => __('admin.dashboard.month_bills'),
                                        'avg_bill' => __('charts.avg_bill'),
                                        'last_bill' => __('charts.admin.last_sale'),
                                    ] as $column => $label)
                                        <th class="whitespace-nowrap px-3 py-2 text-start">
                                            <button type="button" class="inline-flex items-center gap-1 uppercase hover:text-indigo-700" @click="sortBy('{{ $column }}')">
                                                {{ $label }}
                                                <span x-show="sortKey === '{{ $column }}'" x-text="sortDir === 'desc' ? '▼' : '▲'"></span>
                                            </button>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody x-ref="rows" class="divide-y divide-gray-100 bg-white">
                                @forelse ($shopPerformance as $row)
                                    @php($status = $statusService->describe($row['shop']))
                                    <tr data-name="{{ mb_strtolower($row['shop']->name . ' ' . $row['shop']->email) }}"
                                        data-sales_today="{{ $row['sales_today'] }}" data-profit_today="{{ $row['profit_today'] }}"
                                        data-sales_month="{{ $row['sales_month'] }}" data-profit_month="{{ $row['profit_month'] }}"
                                        data-margin="{{ $row['margin'] ?? -999999 }}" data-bills_month="{{ $row['bills_month'] }}"
                                        data-avg_bill="{{ $row['avg_bill'] }}" data-last_bill="{{ $row['last_bill_at'] ? strtotime($row['last_bill_at']) : 0 }}"
                                        x-show="visible($el)" class="hover:bg-indigo-50/40">
                                        <td class="px-3 py-2">
                                            <a class="font-medium text-indigo-700 hover:underline" href="{{ route('admin.shop-owners.show', $row['shop']) }}">{{ $row['shop']->name }}</a>
                                            <div class="text-xs text-gray-400">{{ __('admin.types.' . $row['shop']->businessRole()) }}</div>
                                        </td>
                                        <td class="px-3 py-2"><x-ui.badge :tone="$status['tone']">{{ $status['label'] }}</x-ui.badge></td>
                                        <td class="px-3 py-2 tabular-nums">{{ $money($row['sales_today']) }}</td>
                                        <td @class(['px-3 py-2 tabular-nums font-medium', 'text-emerald-700' => $row['profit_today'] > 0, 'text-rose-700' => $row['profit_today'] < 0])>{{ $money($row['profit_today']) }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $money($row['sales_month']) }}</td>
                                        <td @class(['px-3 py-2 tabular-nums font-semibold', 'text-emerald-700' => $row['profit_month'] > 0, 'text-rose-700' => $row['profit_month'] < 0])>{{ $money($row['profit_month']) }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $row['margin'] === null ? '—' : $row['margin'] . '%' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $row['bills_month'] }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $money($row['avg_bill']) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-500">{{ $row['last_bill_at'] ? \Carbon\Carbon::parse($row['last_bill_at'], 'UTC')->diffForHumans() : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="px-3 py-8 text-center text-gray-500">{{ __('charts.no_data') }}</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="sticky bottom-0 bg-gray-50 text-sm font-semibold text-gray-900">
                                <tr>
                                    <td class="px-3 py-2" colspan="2">{{ __('charts.total') }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $money($kpis['sales_today']) }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $money($kpis['profit_today']) }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $money($kpis['sales_month']) }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $money($kpis['profit_month']) }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $kpis['margin_month'] === null ? '—' : $kpis['margin_month'] . '%' }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $kpis['bills_month'] }}</td>
                                    <td class="px-3 py-2 tabular-nums">{{ $money($kpis['avg_bill_month']) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </x-ui.card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-ui.card :title="__('admin.dashboard.collections')" class="lg:col-span-1">
                    <div class="space-y-3">
                        @forelse ($collectionGroups as $currency => $totals)
                            <div class="rounded-xl border border-gray-200 p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <x-ui.badge tone="indigo">{{ $currency }}</x-ui.badge>
                                    <span class="text-xs text-gray-500">{{ $currencies->symbol($currency) }}</span>
                                </div>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between"><span>{{ __('admin.dashboard.overdue') }}</span><span class="font-semibold text-rose-700">{{ number_format($totals['overdue'], 2) }}</span></div>
                                    <div class="flex justify-between"><span>{{ __('admin.dashboard.next_30_days') }}</span><span class="font-semibold">{{ number_format($totals['next_30_days'], 2) }}</span></div>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty :title="__('admin.dashboard.collections')" :text="__('admin.filters.all')" />
                        @endforelse
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('admin.dashboard.alerts')" class="lg:col-span-2">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('admin.dashboard.usage_alerts') }}</h4>
                            @forelse ($usageAlerts as $alert)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <a href="{{ route('admin.shop-owners.show', $alert['shop']) }}" class="font-semibold text-amber-900">{{ $alert['shop']->name }}</a>
                                        <span class="text-xs text-amber-700">{{ $alert['percent'] }}%</span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-amber-100"><div class="h-full rounded-full bg-amber-500" style="width: {{ min(100, (int) $alert['percent']) }}%"></div></div>
                                    <p class="mt-1 text-amber-800">{{ $alert['used'] }} / {{ $alert['limit'] }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">—</p>
                            @endforelse
                        </div>
                        <div class="space-y-3">
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('admin.dashboard.image_alerts') }}</h4>
                            @forelse ($imageAlerts as $alert)
                                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <a href="{{ route('admin.shop-owners.show', $alert['shop']) }}" class="font-semibold text-blue-900">{{ $alert['shop']->name }}</a>
                                        <span class="text-xs text-blue-700">{{ \App\Services\Admin\ShopStorageService::humanBytes($alert['stats']['bytes']) }}</span>
                                    </div>
                                    <p class="mt-1 text-blue-800">{{ $alert['stats']['count'] }} {{ __('admin.fields.images') }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">—</p>
                            @endforelse
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('admin.dashboard.top_shops')" :subtitle="__('charts.this_month')">
                    <div class="space-y-4">
                        @php($topMax = max(1, (float) $topShops->max('sales')))
                        @forelse ($topShops as $index => $row)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <a class="flex items-center gap-2 font-medium text-indigo-700" href="{{ route('admin.shop-owners.show', $row['shop']) }}">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-700">{{ $index + 1 }}</span>
                                        {{ $row['shop']->name }}
                                    </a>
                                    <span class="text-xs text-gray-500">{{ __('charts.admin.bills_count', ['count' => $row['bills']]) }}</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" style="width: {{ round($row['sales'] / $topMax * 100) }}%"></div></div>
                                <div class="mt-1 flex justify-between text-xs text-gray-600">
                                    <span>{{ __('charts.sales') }}: <strong class="text-gray-900">{{ $money($row['sales']) }}</strong></span>
                                    <span>{{ __('charts.gross_profit') }}: <strong class="text-emerald-700">{{ $money($row['profit']) }}</strong></span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('charts.no_data') }}</p>
                        @endforelse
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('admin.dashboard.recent_signups')">
                    <div class="space-y-3">
                        @foreach ($recentSignups as $shop)
                            @php($status = $statusService->describe($shop))
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                                <div>
                                    <a class="font-medium text-indigo-700" href="{{ route('admin.shop-owners.show', $shop) }}">{{ $shop->name }}</a>
                                    <p class="text-xs text-gray-500">{{ $shop->email }} · {{ $shop->created_at?->diffForHumans() }}</p>
                                </div>
                                <x-ui.badge :tone="$status['tone']">{{ $status['label'] }}</x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('admin.dashboard.system_health')">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4 text-sm"><span class="font-semibold">{{ __('admin.dashboard.free_disk') }}:</span> {{ $systemHealth['free_disk'] }}</div>
                    <div class="rounded-lg bg-gray-50 p-4 text-sm"><span class="font-semibold">{{ __('admin.dashboard.backup_age') }}:</span> {{ $systemHealth['backup_age'] }}</div>
                    <div class="rounded-lg bg-gray-50 p-4 text-sm"><span class="font-semibold">{{ __('admin.dashboard.failed_jobs') }}:</span> {{ $systemHealth['failed_jobs'] }}</div>
                </div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            function adminShopPerformance() {
                return {
                    query: '',
                    onlySelling: false,
                    sortKey: 'profit_month',
                    sortDir: 'desc',
                    visible(row) {
                        const q = this.query.trim().toLowerCase();
                        if (q && !(row.dataset.name || '').includes(q)) return false;
                        if (this.onlySelling && Number(row.dataset.bills_month || 0) <= 0) return false;
                        return true;
                    },
                    sortBy(key) {
                        this.sortDir = this.sortKey === key && this.sortDir === 'desc' ? 'asc' : 'desc';
                        this.sortKey = key;
                        const body = this.$refs.rows;
                        const rows = Array.from(body.querySelectorAll('tr[data-name]'));
                        const direction = this.sortDir === 'desc' ? -1 : 1;
                        rows.sort((a, b) => (Number(a.dataset[key] || 0) - Number(b.dataset[key] || 0)) * direction);
                        rows.forEach((row) => body.appendChild(row));
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
