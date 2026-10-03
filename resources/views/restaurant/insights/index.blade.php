<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('restaurant.titles.insights')" :subtitle="__('restaurant.subtitles.insights')">
            <form method="GET" action="{{ route('restaurant.insights.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('restaurant.fields.from') }}</label>
                    <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('restaurant.fields.to') }}</label>
                    <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-gray-300">
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('restaurant.buttons.refresh') }}
                </button>
                <a href="{{ route('restaurant.insights.export', ['from' => $from, 'to' => $to]) }}"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('restaurant.buttons.export_csv') }}
                </a>
            </form>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('restaurant.insights.prep_time')" :value="$avgPreparationMinutes . ' ' . __('restaurant.insights.minutes')" />
                <x-ui.stat :label="__('restaurant.insights.service_time')" :value="$avgServiceMinutes . ' ' . __('restaurant.insights.minutes')" />
                <x-ui.stat :label="__('restaurant.insights.busiest_hour')" :value="data_get($busiestHour, 'label', '—')" />
                <x-ui.stat :label="__('restaurant.insights.busiest_weekday')" :value="data_get($busiestWeekday, 'label', '—')" />
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('restaurant.insights.sales_by_type')">
                    <div class="space-y-3">
                        @foreach ($salesByType as $row)
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                <span>{{ __('restaurant.order_types.' . $row->order_type) }}</span>
                                <span class="font-semibold">₪{{ number_format((float) $row->total_sales, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('restaurant.insights.sales_by_table')">
                    <div class="space-y-3">
                        @foreach ($salesByTable as $row)
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                <span>{{ $row->label }}</span>
                                <span class="font-semibold">₪{{ number_format((float) $row->total_sales, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('restaurant.insights.top_items')">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('restaurant.fields.name') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('restaurant.insights.quantity_sold') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('restaurant.insights.gross_sales') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($topItems as $row)
                                    <tr>
                                        <td class="px-4 py-3">{{ $row->name }}</td>
                                        <td class="px-4 py-3">{{ number_format((float) $row->quantity_sold, 0) }}</td>
                                        <td class="px-4 py-3">₪{{ number_format((float) $row->gross_sales, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('restaurant.insights.bottom_items')">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('restaurant.fields.name') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('restaurant.insights.quantity_sold') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('restaurant.insights.gross_sales') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($bottomItems as $row)
                                    <tr>
                                        <td class="px-4 py-3">{{ $row->name }}</td>
                                        <td class="px-4 py-3">{{ number_format((float) $row->quantity_sold, 0) }}</td>
                                        <td class="px-4 py-3">₪{{ number_format((float) $row->gross_sales, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('restaurant.insights.sales_by_hour')">
                    <div class="space-y-3">
                        @foreach ($salesByHour as $row)
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                <span>{{ $row->label }}</span>
                                <span class="font-semibold">{{ $row->orders_count }} / ₪{{ number_format((float) $row->total_sales, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('restaurant.insights.sales_by_weekday')">
                    <div class="space-y-3">
                        @foreach ($salesByWeekday as $row)
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                <span>{{ $row->label }}</span>
                                <span class="font-semibold">{{ $row->orders_count }} / ₪{{ number_format((float) $row->total_sales, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('restaurant.insights.cancelled_orders')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.table') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.order_type') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.reason') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.opened') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($cancelledOrders as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row->label }}</td>
                                    <td class="px-4 py-3">{{ __('restaurant.order_types.' . $row->order_type) }}</td>
                                    <td class="px-4 py-3">{{ $row->cancel_reason ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ \App\Support\ShopTime::local($row->updated_at, auth()->user())->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
