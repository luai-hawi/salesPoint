@php
    $accountantTypes = [
        'profit_loss' => __('finance.reports.profit_loss'),
        'receivables_aging' => __('finance.reports.receivables_aging'),
        'payables_aging' => __('finance.reports.payables_aging'),
        'inventory_valuation' => __('finance.reports.inventory_valuation'),
        'balances_summary' => __('finance.reports.balances_summary'),
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.reports.title')" :subtitle="__('finance.reports.subtitle')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <x-ui.card :title="__('messages.Select a Report')">
                <form method="GET" action="{{ route('reports.print') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="report-type">{{ __('messages.Select a Report') }}</label>
                        <select id="report-type" name="type" class="w-full rounded-lg border-gray-300">
                            @foreach (\App\Support\ReportCatalog::rows() as $type => $definition)
                                @continue(($definition['group'] ?? null) === 'products')
                                <option value="{{ $type }}" @selected(request('type') === $type)>{{ \App\Support\ReportCatalog::label($definition['label']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach (['from', 'to'] as $date)
                        <div>
                            <label for="report-{{ $date }}">{{ __('finance.common.' . $date) }}</label>
                            <input id="report-{{ $date }}" type="date" name="{{ $date }}" value="{{ request($date, $today) }}" class="w-full rounded-lg border-gray-300">
                        </div>
                    @endforeach
                    @foreach (['customer_id' => $customers, 'supplier_id' => $suppliers, 'employee_id' => $employees, 'employee_user_id' => $employeeUsers] as $field => $options)
                        <div>
                            <label for="report-{{ $field }}">{{ __('finance.restored.' . $field) }}</label>
                            <select id="report-{{ $field }}" name="{{ $field }}" class="w-full rounded-lg border-gray-300">
                                <option value="">{{ __('messages.All') }}</option>
                                @foreach ($options as $option)
                                    <option value="{{ $option->id }}" @selected(request($field) == $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="flex flex-wrap items-end gap-2">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('messages.Generate Report') }}</button>
                        <button formaction="{{ route('reports.export') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">{{ __('finance.reports.export_csv') }}</button>
                    </div>
                </form>
                <a href="{{ route('reports.customer-bill-details') }}" class="mt-4 inline-block text-indigo-700 underline">{{ __('messages.Customer Bill Details Report') }}</a>
            </x-ui.card>

            @php
                $productTypes = collect(\App\Support\ReportCatalog::productReports());
                $selectedProductType = $productTypes->has(request('type')) ? request('type') : 'product_sales';
            @endphp
            <section id="product-reports" class="scroll-mt-24">
                <x-ui.card :title="__('charts.products.title')">
                    <p class="mb-4 text-sm text-gray-500">{{ __('charts.products.subtitle') }}</p>
                    <form method="GET" action="{{ route('reports.print') }}" x-data="{ type: @js($selectedProductType), undated: @js($productTypes->filter(fn ($d) => ! $d['dated'])->keys()->values()), setRange(days) { const to = new Date(); const from = new Date(); if (days === 'month') { from.setDate(1); } else { from.setDate(to.getDate() - days + 1); } const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); this.$refs.from.value = fmt(from); this.$refs.to.value = fmt(to); } }">
                        <fieldset>
                            <legend class="sr-only">{{ __('charts.products.choose_report') }}</legend>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                @foreach ($productTypes as $type => $definition)
                                    <label class="relative flex cursor-pointer flex-col rounded-xl border p-3 transition hover:border-indigo-300"
                                           :class="type === @js($type) ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 bg-white'">
                                        <input type="radio" name="type" value="{{ $type }}" x-model="type" class="sr-only" @checked($selectedProductType === $type)>
                                        <span class="text-sm font-semibold text-gray-900">{{ __($definition['label']) }}</span>
                                        <span class="mt-1 text-xs leading-5 text-gray-500">{{ __($definition['description']) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div x-show="! undated.includes(type)">
                                <label for="product-report-from" class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label>
                                <input id="product-report-from" x-ref="from" type="date" name="from" value="{{ request('from', \Illuminate\Support\Carbon::parse($today)->startOfMonth()->toDateString()) }}" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div x-show="! undated.includes(type)">
                                <label for="product-report-to" class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label>
                                <input id="product-report-to" x-ref="to" type="date" name="to" value="{{ request('to', $today) }}" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label for="product-report-search" class="mb-1 block text-sm font-medium text-gray-700">{{ __('charts.products.search') }}</label>
                                <input id="product-report-search" type="search" name="product_search" value="{{ request('product_search') }}" placeholder="{{ __('charts.products.search_placeholder') }}" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label for="product-report-category" class="mb-1 block text-sm font-medium text-gray-700">{{ __('charts.products.columns.category') }}</label>
                                <select id="product-report-category" name="category" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">{{ __('messages.All') }}</option>
                                    <option value="__none" @selected(request('category') === '__none')>{{ __('charts.products.uncategorized') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="type === 'product_purchases'" x-cloak>
                                <label for="product-report-supplier" class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.restored.supplier_id') }}</label>
                                <select id="product-report-supplier" name="supplier_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="type !== 'product_purchases'">
                                    <option value="">{{ __('messages.All') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-2" x-show="! undated.includes(type)">
                                <button type="button" @click="setRange(1)" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('charts.products.range_today') }}</button>
                                <button type="button" @click="setRange(7)" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('charts.products.range_7') }}</button>
                                <button type="button" @click="setRange(30)" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('charts.products.range_30') }}</button>
                                <button type="button" @click="setRange('month')" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('charts.this_month') }}</button>
                                <button type="button" @click="setRange(365)" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('charts.products.range_year') }}</button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.reports.print_report') }}</button>
                                <button formaction="{{ route('reports.export') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.reports.export_csv') }}</button>
                            </div>
                        </div>
                    </form>
                </x-ui.card>
            </section>

            <x-ui.card :title="__('finance.reports.accountant_reports')">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($accountantTypes as $type => $label)
                        <form method="GET" action="{{ route('reports.print') }}" class="rounded-xl border border-gray-200 p-4">
                            <input type="hidden" name="type" value="{{ $type }}">
                            <div class="mb-3 text-base font-semibold text-gray-900">{{ $label }}</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label>
                                    <input type="date" name="from" value="{{ request('from', $today) }}" class="w-full rounded-lg border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label>
                                    <input type="date" name="to" value="{{ request('to', $today) }}" class="w-full rounded-lg border-gray-300 text-sm">
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.reports.print_report') }}</button>
                                <button formaction="{{ route('reports.export') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.reports.export_csv') }}</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
