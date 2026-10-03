<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$shopOwner->name" :subtitle="__('admin.titles.shop_details')">
            <x-ui.badge :tone="$status['tone']">{{ $status['label'] }}</x-ui.badge>
            <a href="{{ route('admin.shop-owners.edit', $shopOwner) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.edit') }}</a>
            <form method="POST" action="{{ route('admin.shop-owners.impersonate', $shopOwner) }}">@csrf<button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.login_as') }}</button></form>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-ui.card :title="__('admin.fields.status')">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span>{{ __('admin.fields.business_type') }}</span><span>{{ __('admin.types.' . $shopOwner->businessRole()) }}</span></div>
                        <div class="flex justify-between"><span>{{ __('admin.fields.next_payment') }}</span><span>{{ $status['next_payment_date'] ?: '—' }}</span></div>
                        <div class="flex justify-between"><span>{{ __('admin.fields.subscription_cost') }}</span><span>{{ $currencies->format($status['amount'], $status['currency']) }}</span></div>
                    </div>
                </x-ui.card>
                <x-ui.card :title="__('admin.fields.usage')">
                    <div class="space-y-2 text-sm">
                        @foreach ($usage as $key => $count)
                            <div class="flex justify-between"><span>{{ __('admin.entry_limit.resources.' . $key) }}</span><span>{{ $count }}</span></div>
                        @endforeach
                        <div class="flex justify-between font-semibold"><span>{{ __('admin.fields.entry_limit') }}</span><span>{{ $shopOwner->entry_limit ?: '∞' }}</span></div>
                    </div>
                </x-ui.card>
                <x-ui.card :title="__('admin.fields.images')">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span>{{ __('admin.fields.images') }}</span><span>{{ $imageStats['count'] }}</span></div>
                        <div class="flex justify-between"><span>{{ __('admin.fields.size') }}</span><span>{{ \App\Services\Admin\ShopStorageService::humanBytes($imageStats['bytes']) }}</span></div>
                        <div class="flex justify-between"><span>{{ __('admin.fields.image_limit') }}</span><span>{{ $shopOwner->image_limit ?: '—' }}</span></div>
                    </div>
                </x-ui.card>
            </div>

            @php($money = fn ($value) => number_format((float) $value, 2))
            <section class="space-y-4" aria-labelledby="shop-performance-title">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h3 id="shop-performance-title" class="text-lg font-semibold text-gray-900">{{ __('charts.admin.shop_profit_title') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('charts.admin.shop_profit_hint') }}</p>
                    </div>
                    @if ($performance['lifetime']['last_bill_at'])
                        <span class="text-xs text-gray-500">{{ __('charts.admin.last_sale') }}: {{ \Carbon\Carbon::parse($performance['lifetime']['last_bill_at'], 'UTC')->diffForHumans() }}</span>
                    @endif
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui.kpi :label="__('admin.dashboard.today_sales')" :value="$money($performance['today']['revenue'])" icon="cash" tone="green"
                        :hint="__('charts.gross_profit') . ': ' . $money($performance['today']['gross_profit'])" />
                    <x-ui.kpi :label="__('admin.dashboard.month_sales')" :value="$money($performance['month']['revenue'])" icon="receipt" tone="blue"
                        :delta="$performance['month_growth']['revenue']" />
                    <x-ui.kpi :label="__('admin.dashboard.month_profit')" :value="$money($performance['month']['gross_profit'])" icon="trend" tone="indigo"
                        :delta="$performance['month_growth']['gross_profit']" :hint="__('charts.margin') . ': ' . $performance['month']['gross_margin'] . '%'" />
                    <x-ui.kpi :label="__('charts.net_profit')" :value="$money($performance['month']['net_profit'])" icon="wallet" :tone="$performance['month']['net_profit'] < 0 ? 'red' : 'purple'"
                        :delta="$performance['month_growth']['net_profit']" :hint="__('charts.this_month')" />
                    <x-ui.kpi :label="__('charts.expenses')" :value="$money($performance['month']['expenses_total'] + $performance['month']['staff_payments'])" icon="down" tone="amber"
                        :hint="__('charts.this_month')" />
                    <x-ui.kpi :label="__('charts.avg_bill')" :value="$money($performance['month_avg_bill'])" icon="receipt" tone="gray"
                        :hint="__('charts.admin.bills_count', ['count' => $performance['month_bills']])" />
                    <x-ui.kpi :label="__('charts.admin.lifetime_sales')" :value="$money($performance['lifetime']['sales'])" icon="box" tone="blue"
                        :hint="__('charts.admin.bills_count', ['count' => $performance['lifetime']['bills']])" />
                    <x-ui.kpi :label="__('charts.admin.lifetime_profit')" :value="$money($performance['lifetime']['profit'])" icon="trend" tone="green"
                        :hint="$performance['lifetime']['first_bill_at'] ? __('charts.admin.since', ['date' => \Carbon\Carbon::parse($performance['lifetime']['first_bill_at'])->toDateString()]) : null" />
                </div>
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    <x-ui.card :title="__('charts.admin.sales_profit_30')" class="xl:col-span-2">
                        <x-ui.chart type="bar" :height="280" :label="__('charts.admin.sales_profit_30')"
                            :labels="$performance['series']['labels']"
                            :datasets="[
                                ['label' => __('charts.sales'), 'data' => $performance['series']['sales'], 'color' => '#6366f1'],
                                ['label' => __('charts.gross_profit'), 'type' => 'line', 'data' => $performance['series']['profit'], 'color' => '#10b981', 'fill' => false],
                            ]" />
                    </x-ui.card>
                    <x-ui.card :title="__('charts.admin.top_products_month')" :padding="false">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-start">{{ __('charts.product') }}</th>
                                        <th class="px-3 py-2 text-start">{{ __('charts.qty') }}</th>
                                        <th class="px-3 py-2 text-start">{{ __('charts.gross_profit') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($performance['top_products'] as $product)
                                        <tr>
                                            <td class="px-3 py-2">{{ $product->name }}</td>
                                            <td class="px-3 py-2 tabular-nums">{{ rtrim(rtrim(number_format((float) $product->quantity, 2), '0'), '.') }}</td>
                                            <td class="px-3 py-2 font-medium tabular-nums text-emerald-700">{{ $money($product->profit) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">{{ __('charts.no_data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('admin.actions.record_payment')">
                    <form method="POST" action="{{ route('admin.shop-owners.mark-paid', $shopOwner) }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $paymentIdempotencyKey }}">
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.months') }}</label><input type="number" min="1" max="120" name="months" class="w-full rounded-lg border-gray-300" value="1"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.subscription_cost') }}</label><input type="number" step="0.01" name="amount" class="w-full rounded-lg border-gray-300" value="{{ $shopOwner->subscription_cost }}"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.currency') }}</label><select name="currency" class="w-full rounded-lg border-gray-300">@foreach ($currencyOptions as $currency)<option value="{{ $currency['code'] }}" @selected($shopOwner->subscriptionCurrency() === $currency['code'])>{{ $currency['code'] }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.method') }}</label><select name="method" class="w-full rounded-lg border-gray-300">@foreach (['cash', 'transfer', 'card', 'check', 'other'] as $method)<option value="{{ $method }}">{{ __('admin.types.' . $method) }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.paid_at') }}</label><input type="date" name="paid_at" class="w-full rounded-lg border-gray-300" value="{{ now()->toDateString() }}"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.continue_mode') }}</label><select name="continue_mode" class="w-full rounded-lg border-gray-300">@foreach (['auto', 'current_expiry', 'today'] as $mode)<option value="{{ $mode }}">{{ __('admin.types.' . $mode) }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.reference') }}</label><input name="reference" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.note') }}</label><input name="note" class="w-full rounded-lg border-gray-300"></div>
                        <div class="md:col-span-2"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.record_payment') }}</button></div>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('admin.fields.admin_notes')" :subtitle="__('admin.messages.shop_private_note_hint')">
                    <form method="POST" action="{{ route('admin.shop-owners.note', $shopOwner) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <textarea name="admin_notes" rows="7" class="w-full rounded-lg border-gray-300">{{ $shopOwner->admin_notes }}</textarea>
                        <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.save_note') }}</button>
                    </form>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('admin.fields.next_payment')" :subtitle="__('admin.meta.records_count', ['count' => $payments->count()])">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.paid_at') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.months') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.subscription_cost') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.method') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.reference') }}</th>
                                <th class="px-3 py-2 text-start">—</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="px-3 py-2">{{ $payment->paid_at?->toDateString() }}</td>
                                    <td class="px-3 py-2">{{ $payment->months }}</td>
                                    <td class="px-3 py-2">{{ $currencies->format($payment->amount, $payment->currency) }}</td>
                                    <td class="px-3 py-2">{{ __('admin.types.' . $payment->method) }}</td>
                                    <td class="px-3 py-2">{{ $payment->reference ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <form method="POST" action="{{ route('admin.shop-owners.payments.destroy', [$shopOwner, $payment]) }}">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-700">{{ __('admin.actions.delete_payment') }}</button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('admin.fields.employees')">
                    <div class="space-y-3">
                        @forelse ($employees as $employee)
                            <div class="rounded-lg border border-gray-200 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $employee->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $employee->email }}</div>
                                    </div>
                                    <a href="{{ route('admin.employees.edit', $employee) }}" class="text-sm font-semibold text-indigo-700">{{ __('admin.actions.edit') }}</a>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty :title="__('admin.fields.employees')" />
                        @endforelse
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('admin.titles.audit')">
                    <div class="space-y-3">
                        @forelse ($activity as $row)
                            <div class="rounded-lg border border-gray-200 p-3 text-sm">
                                <div class="font-medium text-gray-900">{{ $row->action }}</div>
                                <div class="text-xs text-gray-500">{{ $row->created_at?->diffForHumans() }}</div>
                            </div>
                        @empty
                            <x-ui.empty :title="__('admin.titles.audit')" />
                        @endforelse
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('admin.actions.delete')" :subtitle="__('admin.meta.rows_count', ['count' => $preview['total']])">
                <div class="mb-4 grid grid-cols-1 gap-2 md:grid-cols-3">
                    @foreach ($preview['tables'] as $table => $count)
                        <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm"><span class="font-medium">{{ $table }}</span>: {{ $count }}</div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.shop-owners.destroy', $shopOwner) }}" class="space-y-3">
                    @csrf
                    @method('DELETE')
                    <input name="confirmation" class="w-full rounded-lg border-gray-300 md:w-96" placeholder="{{ $shopOwner->name }} / {{ $shopOwner->email }}">
                    <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('admin.actions.delete') }}</button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
