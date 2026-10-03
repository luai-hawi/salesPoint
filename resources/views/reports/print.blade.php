<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('finance.reports.title') }}</title>
    @vite('resources/css/app.css')
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-white p-6 text-gray-900">
    <x-report-actions :return-url="route('reports.index', request()->query())" />

    <div class="mb-6 rounded-xl border border-gray-200 p-6">
        <h1 class="text-2xl font-bold">{{ __('finance.reports.' . $type) }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $from }} → {{ $to }}</p>
    </div>

    @switch($type)
        @case('profit_loss')
            @php
                $current = $report;
                $compare = $report['compare'] ?? [];
            @endphp
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <h2 class="text-lg font-semibold">{{ __('finance.reports.current_period') }}</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('finance.common.item') }}</th>
                                <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.common.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr><td class="px-4 py-2">{{ __('finance.reports.revenue') }}</td><td class="px-4 py-2 text-end">{{ number_format($current['revenue'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.returns') }}</td><td class="px-4 py-2 text-end text-red-600">-{{ number_format($current['returns'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2 font-semibold">{{ __('finance.reports.net_revenue') }}</td><td class="px-4 py-2 text-end font-semibold">{{ number_format($current['net_revenue'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.cogs') }}</td><td class="px-4 py-2 text-end text-red-600">-{{ number_format($current['cogs'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.day_close.discounts') }}</td><td class="px-4 py-2 text-end">{{ number_format($current['discounts'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.gross_profit') }}</td><td class="px-4 py-2 text-end font-semibold {{ ($current['gross_profit'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($current['gross_profit'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.expenses') }}</td><td class="px-4 py-2 text-end text-red-600">-{{ number_format($current['expenses_total'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.staff_payments') }}</td><td class="px-4 py-2 text-end text-red-600">-{{ number_format($current['staff_payments'] ?? 0, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.damaged_loss') }}</td><td class="px-4 py-2 text-end text-red-600">-{{ number_format($current['damaged_loss'] ?? 0, 2) }}</td></tr>
                            <tr class="bg-gray-100 font-bold">
                                <td class="px-4 py-2">{{ __('finance.reports.net_profit') }}</td>
                                <td class="px-4 py-2 text-end {{ ($current['net_profit'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($current['net_profit'] ?? 0, 2) }}</td>
                            </tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.gross_margin') }}</td><td class="px-4 py-2 text-end">{{ number_format($current['gross_margin'] ?? 0, 2) }}%</td></tr>
                            <tr><td class="px-4 py-2">{{ __('finance.reports.net_margin') }}</td><td class="px-4 py-2 text-end">{{ number_format($current['net_margin'] ?? 0, 2) }}%</td></tr>
                        </tbody>
                    </table>
                </div>

                @if(!empty($compare))
                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">{{ __('finance.reports.previous_period') }}</h2>
                            <p class="text-sm">{{ $compare['from_date'] }} — {{ $compare['to_date'] }}</p>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('finance.common.item') }}</th>
                                    <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.common.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="px-4 py-2">{{ __('finance.reports.revenue') }}</td><td class="px-4 py-2 text-end">{{ number_format($compare['revenue'] ?? 0, 2) }}</td></tr>
                                <tr><td class="px-4 py-2">{{ __('finance.reports.net_revenue') }}</td><td class="px-4 py-2 text-end">{{ number_format($compare['net_revenue'] ?? 0, 2) }}</td></tr>
                                <tr><td class="px-4 py-2">{{ __('finance.reports.gross_profit') }}</td><td class="px-4 py-2 text-end">{{ number_format($compare['gross_profit'] ?? 0, 2) }}</td></tr>
                                <tr class="bg-gray-100 font-bold"><td class="px-4 py-2">{{ __('finance.reports.net_profit') }}</td><td class="px-4 py-2 text-end">{{ number_format($compare['net_profit'] ?? 0, 2) }}</td></tr>
                                <tr><td class="px-4 py-2">{{ __('finance.reports.gross_margin') }}</td><td class="px-4 py-2 text-end">{{ number_format($compare['gross_margin'] ?? 0, 2) }}%</td></tr>
                                <tr><td class="px-4 py-2">{{ __('finance.reports.net_margin') }}</td><td class="px-4 py-2 text-end">{{ number_format($compare['net_margin'] ?? 0, 2) }}%</td></tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @break

        @case('receivables_aging')
        @case('payables_aging')
            @php
                $rows = $report['rows'] ?? collect();
                $totals = $report['totals'] ?? [];
                $isCustomer = $type === 'receivables_aging';
            @endphp
            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ $isCustomer ? __('finance.reports.customer') : __('finance.reports.supplier') }}</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">0-30</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">31-60</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">61-90</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">90+</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.reports.unallocated') }}</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.common.total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            @php
                                $entity = $row[$isCustomer ? 'customer' : 'supplier'] ?? null;
                                $buckets = $row['buckets'] ?? [];
                                $total = $row['total'] ?? 0;
                            @endphp
                            <tr>
                                <td class="px-4 py-2">{{ $entity->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($buckets['0_30'] ?? 0, 2) }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($buckets['31_60'] ?? 0, 2) }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($buckets['61_90'] ?? 0, 2) }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($buckets['90_plus'] ?? 0, 2) }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($buckets['unallocated'] ?? 0, 2) }}</td>
                                <td class="px-4 py-2 text-end font-semibold">{{ number_format($total, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-100 font-bold">
                            <td class="px-4 py-2">{{ __('finance.common.total') }}</td>
                            <td class="px-4 py-2 text-end">{{ number_format($totals['0_30'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-end">{{ number_format($totals['31_60'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-end">{{ number_format($totals['61_90'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-end">{{ number_format($totals['90_plus'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-end">{{ number_format($totals['unallocated'] ?? 0, 2) }}</td>
                            <td class="px-4 py-2 text-end">{{ number_format($totals['total'] ?? 0, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @break

        @case('inventory_valuation')
            @php
                $categories = $report['categories'] ?? collect();
                $totalValue = $report['total_value'] ?? 0;
                $deadStock = $report['dead_stock'] ?? collect();
            @endphp
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                        <h2 class="text-lg font-semibold">{{ __('finance.reports.inventory_valuation') }}</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('finance.reports.category') }}</th>
                                <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.reports.quantity') }}</th>
                                <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.reports.value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($categories as $cat)
                                <tr>
                                    <td class="px-4 py-2">{{ $cat->category ?? '—' }}</td>
                                    <td class="px-4 py-2 text-end">{{ number_format($cat->qty ?? 0, 0) }}</td>
                                    <td class="px-4 py-2 text-end">{{ number_format($cat->value ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-100 font-bold">
                                <td class="px-4 py-2">{{ __('finance.common.total') }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($categories->sum('qty'), 0) }}</td>
                                <td class="px-4 py-2 text-end">{{ number_format($totalValue, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if($deadStock->count() > 0)
                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">{{ __('finance.reports.dead_stock') }}</h2>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('finance.reports.product') }}</th>
                                    <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.reports.quantity') }}</th>
                                    <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.reports.value') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($deadStock as $product)
                                    <tr>
                                        <td class="px-4 py-2">{{ $product->name }}</td>
                                        <td class="px-4 py-2 text-end">{{ number_format($product->quantity ?? 0, 0) }}</td>
                                        <td class="px-4 py-2 text-end">{{ number_format(($product->quantity ?? 0) * ($product->cost_price ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @break

        @case('balances_summary')
            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('finance.common.item') }}</th>
                            <th class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('finance.common.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr><td class="px-4 py-2">{{ __('finance.reports.cash_estimate') }}</td><td class="px-4 py-2 text-end">{{ number_format($report['cash_estimate'] ?? 0, 2) }}</td></tr>
                        <tr><td class="px-4 py-2">{{ __('finance.reports.receivables') }}</td><td class="px-4 py-2 text-end">{{ number_format($report['receivables'] ?? 0, 2) }}</td></tr>
                        <tr><td class="px-4 py-2">{{ __('finance.reports.payables') }}</td><td class="px-4 py-2 text-end text-red-600">-{{ number_format($report['payables'] ?? 0, 2) }}</td></tr>
                        <tr><td class="px-4 py-2">{{ __('finance.reports.inventory_value') }}</td><td class="px-4 py-2 text-end">{{ number_format($report['inventory_value'] ?? 0, 2) }}</td></tr>
                        <tr><td class="px-4 py-2">{{ __('finance.reports.capital') }}</td><td class="px-4 py-2 text-end">{{ number_format($report['capital'] ?? 0, 2) }}</td></tr>
                    </tbody>
                </table>
            </div>
        @break

        @default
            <div class="rounded-xl border border-gray-200 p-6">
                <p class="text-sm text-gray-500">{{ __('finance.reports.invalid_report_type') }}</p>
            </div>
    @endswitch
</body>
</html>
