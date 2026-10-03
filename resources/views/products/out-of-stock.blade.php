@php
    $productIds = $products->pluck('id')->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('products_ui.page.out_of_stock_title')" :subtitle="__('products_ui.page.out_of_stock_subtitle')">
            <a href="{{ route('products.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.back') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="outOfStockPage()">
            <x-ui.flash />

            <x-ui.card :title="__('products_ui.sections.filters')">
                <form method="GET" class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.filters.warning') }}</label>
                        <input type="number" name="warning_months" min="1" max="24" value="{{ $warningMonths }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.filters.deactivation') }}</label>
                        <input type="number" name="deactivation_months" min="1" max="36" value="{{ $deactivationMonths }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.status') }}</label>
                        <select name="filter" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="">{{ __('products_ui.filters.all_statuses') }}</option>
                            <option value="warning" @selected(request('filter') === 'warning')>{{ __('products_ui.filters.warning') }}</option>
                            <option value="deactivation" @selected(request('filter') === 'deactivation')>{{ __('products_ui.filters.deactivation') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('products_ui.buttons.apply') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('products_ui.sections.results')">
                @if ($products->isEmpty())
                    <x-ui.empty :title="__('products_ui.table.empty_out_of_stock')" />
                @else
                    <form method="POST" action="{{ route('products.out-of-stock.bulk') }}" class="space-y-4">
                        @csrf
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300" @change="toggleAll($event.target.checked, @js($productIds))">
                                {{ __('products_ui.buttons.select_all') }}
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="product_ids[]" :value="id">
                                </template>
                                <input type="hidden" name="warning_months" value="{{ $warningMonths }}">
                                <input type="hidden" name="deactivation_months" value="{{ $deactivationMonths }}">
                                <input type="hidden" name="filter" value="{{ request('filter') }}">
                                <button type="submit" name="action" value="extend" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" :disabled="selected.length === 0">
                                    {{ __('products_ui.buttons.extend_selected') }}
                                </button>
                                <button type="submit" name="action" value="deactivate" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" :disabled="selected.length === 0">
                                    {{ __('products_ui.buttons.deactivate') }}
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3 text-start"></th>
                                        <th class="px-4 py-3 text-start">{{ __('products_ui.table.product') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('products_ui.labels.quantity') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('products_ui.table.last_sale') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('products_ui.labels.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($products as $product)
                                        <tr>
                                            <td class="px-4 py-4 text-start">
                                                <input type="checkbox" class="rounded border-gray-300" value="{{ $product->id }}" x-model="selected">
                                            </td>
                                            <td class="px-4 py-4 text-start">
                                                <p class="font-semibold text-gray-900">{{ $product->name }}</p>
                                                <p class="text-xs text-gray-500">#{{ $product->id }}</p>
                                            </td>
                                            <td class="px-4 py-4 text-start">{{ number_format((float) $product->quantity, 2) }}</td>
                                            <td class="px-4 py-4 text-start">{{ optional($product->last_sale_date)->format('Y-m-d') }}</td>
                                            <td class="px-4 py-4 text-start">
                                                <x-ui.badge :tone="$product->status_color === 'red' ? 'red' : ($product->status_color === 'blue' ? 'blue' : 'amber')">
                                                    {{ __('products_ui.status.' . $product->status) }}
                                                </x-ui.badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                @endif

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script src="{{ \App\Support\Assets::versioned('js/product-forms.js') }}"></script>
    @endpush
</x-app-layout>
