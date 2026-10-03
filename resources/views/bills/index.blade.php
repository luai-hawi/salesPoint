<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('messages.Bills Management')" :subtitle="__('receivables.payment_panel')">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge tone="blue">{{ __('bills.Total Bills') }}: {{ $bills->total() }}</x-ui.badge>
            </div>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @php
                $scopeText = match (true) {
                    $allDates => __('bills.list.scope_all'),
                    $dateScope['preset'] === 'today' => __('bills.list.scope_today', ['date' => $selectedDate]),
                    $selectedDate === $selectedDateTo => __('bills.list.scope_day', ['date' => $selectedDate]),
                    default => __('bills.list.scope_range', ['from' => $selectedDate, 'to' => $selectedDateTo]),
                };
                $keepFilters = array_filter([
                    'search' => request('search'),
                    'payment_status' => $paymentStatus,
                ], fn ($value) => $value !== null && $value !== '');
                $today = \Carbon\Carbon::parse($dateScope['today']);
                $presets = [
                    'today' => ['label' => __('bills.list.today'), 'query' => ['date' => $today->toDateString()]],
                    'yesterday' => ['label' => __('bills.list.yesterday'), 'query' => ['date' => $today->copy()->subDay()->toDateString()]],
                    'week' => ['label' => __('bills.list.last_7_days'), 'query' => ['date' => $today->copy()->subDays(6)->toDateString(), 'date_to' => $today->toDateString()]],
                    'month' => ['label' => __('bills.list.this_month'), 'query' => ['date' => $today->copy()->startOfMonth()->toDateString(), 'date_to' => $today->toDateString()]],
                    'all' => ['label' => __('bills.list.all_dates'), 'query' => ['all_dates' => 1]],
                ];
            @endphp

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span data-testid="bills-scope">{{ $scopeText }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($presets as $key => $preset)
                        <a href="{{ route('bills.index', array_merge($keepFilters, $preset['query'])) }}"
                            @class([
                                'rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                'bg-indigo-600 text-white shadow-sm' => $dateScope['preset'] === $key,
                                'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $dateScope['preset'] !== $key,
                            ])>{{ $preset['label'] }}</a>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('bills.Total Sales')" :value="'₪' . number_format($totalSales, 2)" :hint="$scopeText" />
                <x-ui.stat :label="__('bills.Total Profit')" :value="'₪' . number_format($totalProfit, 2)" :hint="$scopeText" />
                <x-ui.stat :label="__('receivables.paid')" :value="'₪' . number_format($filteredPaid, 2)" :hint="$scopeText" />
                <x-ui.stat :label="__('receivables.due')" :value="'₪' . number_format($filteredDue, 2)" :hint="$scopeText" />
            </div>

            <x-ui.card>
                <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    @if ($allDates)
                        <input type="hidden" name="all_dates" value="1">
                    @endif
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Search...') }}</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-400">{{ __('bills.list.search_hint') }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('messages.Date') }} — {{ __('bills.list.from') }}</label>
                        <input type="date" name="date" value="{{ $selectedDate }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('bills.list.to') }}</label>
                        <input type="date" name="date_to" value="{{ $selectedDateTo }}"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('receivables.payment_status') }}</label>
                        <select name="payment_status"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('receivables.all_statuses') }}</option>
                            <option value="cash" @selected($paymentStatus === 'cash')>{{ __('receivables.cash_sale') }}</option>
                            <option value="paid" @selected($paymentStatus === 'paid')>{{ __('receivables.paid') }}</option>
                            <option value="partial" @selected($paymentStatus === 'partial')>{{ __('receivables.partial') }}</option>
                            <option value="unpaid" @selected($paymentStatus === 'unpaid')>{{ __('receivables.unpaid') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                            {{ __('messages.Filter') }}
                        </button>
                        <a href="{{ route('bills.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('messages.Clear') }}
                        </a>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('bills.Bill Info') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('bills.Customer') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.payment_status') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Amount') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.payment_method') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('receivables.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($bills as $bill)
                                @php
                                    $summary = $bill->payment_summary ?? ['status' => 'cash', 'paid' => $bill->total_price, 'due' => 0];
                                    $confirmText = $summary['paid'] > 0 && $summary['status'] !== 'cash'
                                        ? __('receivables.bill_payments_are_deleted_with_bill')
                                        : __('bills.Are you sure you want to delete this bill? This will restore product quantities.');
                                    $tone = match ($summary['status']) {
                                        'paid' => 'green',
                                        'partial' => 'amber',
                                        'unpaid' => 'red',
                                        default => 'blue',
                                    };
                                    $label = match ($summary['status']) {
                                        'paid' => __('receivables.paid'),
                                        'partial' => __('receivables.partial'),
                                        'unpaid' => __('receivables.unpaid'),
                                        default => __('receivables.cash_sale'),
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">#{{ $bill->id }}</div>
                                        <div class="text-xs text-gray-500">{{ $bill->products->count() }} {{ __('receivables.items') }}</div>
                                        @if ($bill->note)
                                            <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $bill->note }}">{{ $bill->note }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $bill->customer->name ?? __('bills.Walk-in') }}</div>
                                        <div class="text-xs text-gray-500">{{ $bill->creator->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-2">
                                            <x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
                                            <div class="text-xs text-gray-500">
                                                {{ __('receivables.paid') }}: ₪{{ number_format($summary['paid'], 2) }}<br>
                                                {{ __('receivables.due') }}: ₪{{ number_format($summary['due'], 2) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        ₪{{ number_format($bill->total_price, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ __('messages.' . ucfirst($bill->payment_method ?: 'cash')) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ \App\Support\ShopTime::local($bill->created_at, $bill->user_id)->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button"
                                                x-data
                                                @click="$dispatch('bill-preview', { url: '{{ route('bills.preview', $bill) }}', id: {{ $bill->id }} })"
                                                title="{{ __('bills.list.quick_review') }}"
                                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                {{ __('bills.list.quick_review') }}
                                            </button>
                                            <a href="{{ route('bills.show', $bill) }}"
                                                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                {{ __('messages.View') }}
                                            </a>
                                            @if (! $bill->is_returned)
                                                <a href="{{ route('bills.edit', $bill) }}"
                                                    class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-200">
                                                    {{ __('bills.Edit') }}
                                                </a>
                                            @endif
                                            <form action="{{ route('bills.destroy', $bill) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    data-confirm="{{ $confirmText }}"
                                                    class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">
                                                    {{ __('messages.Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12">
                                        <x-ui.empty :title="__('bills.No bills found')" :text="__('bills.Start by creating your first bill.')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $bills->links('vendor.pagination.custom-light') }}
                </div>
            </x-ui.card>
        </div>
    </div>

    <script>
        function billQuickReview() {
            return {
                visible: false,
                loading: false,
                error: false,
                bill: null,
                url: null,
                billId: null,
                requestToken: 0,
                get title() {
                    return @js(__('bills.list.bill_details', ['id' => '__ID__'])).replace('__ID__', this.billId ?? '');
                },
                get statusClass() {
                    return {
                        paid: 'bg-green-100 text-green-700',
                        partial: 'bg-amber-100 text-amber-700',
                        unpaid: 'bg-red-100 text-red-700',
                    }[this.bill?.status] ?? 'bg-blue-100 text-blue-700';
                },
                open(detail) {
                    this.url = detail.url;
                    this.billId = detail.id;
                    this.visible = true;
                    document.body.classList.add('overflow-hidden');
                    this.load();
                },
                close() {
                    if (! this.visible) return;
                    this.visible = false;
                    this.requestToken++;
                    document.body.classList.remove('overflow-hidden');
                },
                async load() {
                    const token = ++this.requestToken;
                    this.loading = true;
                    this.error = false;
                    this.bill = null;
                    try {
                        const response = await fetch(this.url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (! response.ok) throw new Error('HTTP ' + response.status);
                        const data = await response.json();
                        if (token !== this.requestToken) return;
                        this.bill = data;
                    } catch (e) {
                        if (token === this.requestToken) this.error = true;
                    } finally {
                        if (token === this.requestToken) this.loading = false;
                    }
                },
                money(value) {
                    return '₪' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                qty(value) {
                    return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 3 });
                },
            };
        }
    </script>

    <div x-data="billQuickReview()" @bill-preview.window="open($event.detail)" @keydown.escape.window="close()"
        x-show="visible" x-cloak style="display: none"
        class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 sm:items-center sm:p-4"
        role="dialog" aria-modal="true" data-testid="bill-preview-dialog">
        <div @click.outside="close()" x-show="visible" x-transition
            class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-bold text-gray-900" x-text="title"></h3>
                    <p class="text-xs text-gray-500" x-show="bill" x-text="bill ? bill.created_at : ''"></p>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="bill">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass" x-text="bill.status_label"></span>
                    </template>
                    <button type="button" @click="close()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('bills.list.close') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div x-show="loading" class="py-12 text-center text-sm text-gray-500">
                    <svg class="mx-auto mb-3 h-8 w-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    {{ __('bills.list.loading') }}
                </div>

                <div x-show="error && ! loading" class="py-10 text-center">
                    <p class="text-sm text-red-600">{{ __('bills.list.load_failed') }}</p>
                    <button type="button" @click="load()" class="mt-3 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('bills.list.retry') }}</button>
                </div>

                <template x-if="bill && ! loading">
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <span x-show="bill.is_returned" class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">{{ __('bills.list.returned') }}</span>
                            <span x-show="bill.is_damaged" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">{{ __('bills.list.damaged') }}</span>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <dt class="text-xs text-gray-500">{{ __('bills.list.customer') }}</dt>
                                <dd class="font-semibold text-gray-900" x-text="bill.customer || @js(__('bills.list.walk_in'))"></dd>
                                <dd class="text-xs text-gray-500" x-show="bill.customer_phone" x-text="bill.customer_phone" dir="ltr"></dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <dt class="text-xs text-gray-500">{{ __('bills.list.cashier') }}</dt>
                                <dd class="font-semibold text-gray-900" x-text="bill.creator || '—'"></dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <dt class="text-xs text-gray-500">{{ __('bills.list.date') }}</dt>
                                <dd class="font-semibold text-gray-900" dir="ltr" x-text="bill.created_at"></dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <dt class="text-xs text-gray-500">{{ __('bills.list.payment_method') }}</dt>
                                <dd class="font-semibold text-gray-900" x-text="bill.payment_method"></dd>
                            </div>
                        </dl>

                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-start">{{ __('bills.list.product') }}</th>
                                        <th class="px-3 py-2 text-center">{{ __('bills.list.quantity') }}</th>
                                        <th class="px-3 py-2 text-end">{{ __('bills.list.unit_price') }}</th>
                                        <th class="px-3 py-2 text-end">{{ __('bills.list.discount') }}</th>
                                        <th class="px-3 py-2 text-end">{{ __('bills.list.line_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(item, index) in bill.items" :key="index">
                                        <tr>
                                            <td class="px-3 py-2">
                                                <div class="font-medium text-gray-900" x-text="item.name"></div>
                                                <div class="text-xs text-gray-400" x-show="item.barcode" x-text="item.barcode" dir="ltr"></div>
                                                <div class="mt-1 flex flex-wrap gap-1" x-show="item.tags.length">
                                                    <template x-for="tag in item.tags">
                                                        <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] text-indigo-700" x-text="tag.name + ' +' + money(tag.price)"></span>
                                                    </template>
                                                </div>
                                                <div class="mt-1 text-[11px] text-gray-500" x-show="item.imeis.length">
                                                    {{ __('bills.list.serials') }}: <span dir="ltr" x-text="item.imeis.join(', ')"></span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-center" x-text="qty(item.quantity)"></td>
                                            <td class="px-3 py-2 text-end" x-text="money(item.unit_price)"></td>
                                            <td class="px-3 py-2 text-end" :class="item.discount > 0 ? 'text-red-600' : 'text-gray-400'" x-text="money(item.discount)"></td>
                                            <td class="px-3 py-2 text-end font-semibold text-gray-900" x-text="money(item.line_total)"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="! bill.items.length">
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('bills.list.no_items') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <template x-if="bill.note">
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                                        <div class="text-xs font-semibold text-amber-700">{{ __('bills.list.note') }}</div>
                                        <div class="mt-1 whitespace-pre-line text-gray-800" x-text="bill.note"></div>
                                    </div>
                                </template>
                            </div>
                            <dl class="space-y-1.5 rounded-lg bg-gray-50 p-3 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('bills.list.subtotal') }}</dt><dd x-text="money(bill.subtotal)"></dd></div>
                                <div class="flex justify-between" x-show="bill.discount_total > 0"><dt class="text-gray-500">{{ __('bills.list.discounts') }}</dt><dd class="text-red-600" x-text="'-' + money(bill.discount_total)"></dd></div>
                                <div class="flex justify-between border-t border-gray-200 pt-1.5 text-base font-bold"><dt>{{ __('bills.list.grand_total') }}</dt><dd x-text="money(bill.total)"></dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('bills.list.paid') }}</dt><dd class="font-semibold text-green-700" x-text="money(bill.paid)"></dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('bills.list.due') }}</dt><dd class="font-semibold" :class="bill.due > 0 ? 'text-red-600' : 'text-gray-500'" x-text="money(bill.due)"></dd></div>
                            </dl>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
                <button type="button" @click="close()" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">{{ __('bills.list.close') }}</button>
                <template x-if="bill && bill.edit_url">
                    <a :href="bill.edit_url" class="rounded-lg bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-200">{{ __('bills.list.edit') }}</a>
                </template>
                <template x-if="bill">
                    <a :href="bill.show_url" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('bills.list.open_full') }}</a>
                </template>
            </div>
        </div>
    </div>


</x-app-layout>
