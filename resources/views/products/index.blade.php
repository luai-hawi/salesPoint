@php
    $user = auth()->user();
    $canManage = $user->role !== 'employee' || $user->hasPermission('edit_products');
    $canDelete = $user->role !== 'employee' || $user->hasPermission('delete_products');
    $productIds = $products->pluck('id')->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('products_ui.page.index_title')" :subtitle="__('products_ui.page.index_subtitle')">
            @if ($user->role !== 'employee' || $user->hasPermission('create_products'))
                <a href="{{ route('products.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('products_ui.buttons.create_product') }}
                </a>
            @endif
            <a href="{{ route('products.export') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.export') }}
            </a>
            <a href="{{ route('barcode.search') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.barcode_search') }}
            </a>
            <a href="{{ route('products.out-of-stock') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.out_of_stock') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div
            class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8"
            x-data="productIndex({
                productIds: @js($productIds),
                quickStockUrl: @js(route('products.add-quantity', '__PRODUCT__')),
                strings: {
                    saving: @js(__('products_ui.buttons.save')),
                    submitStock: @js(__('products_ui.buttons.add_stock')),
                    stockError: @js(__('products_ui.flash.stock_error')),
                }
            })"
        >
            <x-ui.flash />

            <div class="grid gap-4 md:grid-cols-4">
                <x-ui.card>
                    <p class="text-xs text-gray-500">{{ __('products_ui.stats.products') }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ $products->total() }}</p>
                </x-ui.card>
                <x-ui.card>
                    <p class="text-xs text-gray-500">{{ __('products_ui.stats.active_products') }}</p>
                    <p class="mt-2 text-2xl font-bold text-green-700">{{ $products->getCollection()->where('is_active', true)->count() }}</p>
                </x-ui.card>
                <x-ui.card>
                    <p class="text-xs text-gray-500">{{ __('products_ui.stats.inactive_products') }}</p>
                    <p class="mt-2 text-2xl font-bold text-amber-700">{{ $products->getCollection()->where('is_active', false)->count() }}</p>
                </x-ui.card>
                <x-ui.card>
                    <p class="text-xs text-gray-500">{{ __('products_ui.stats.stock_total') }}</p>
                    <p class="mt-2 text-2xl font-bold text-indigo-700">{{ number_format((float) $products->getCollection()->sum('quantity'), 2) }}</p>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('products_ui.sections.filters')">
                <form method="GET" class="grid gap-4 md:grid-cols-5">
                    <div class="md:col-span-2">
                        <label for="search" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.search') }}</label>
                        <input id="search" type="search" name="search" value="{{ request('search') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.search') }}">
                    </div>
                    <div>
                        <label for="category" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.category') }}</label>
                        <select id="category" name="category" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="">{{ __('products_ui.filters.all_categories') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.status') }}</label>
                        <select id="status" name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="">{{ __('products_ui.filters.all_statuses') }}</option>
                            <option value="active" @selected(request('status') === 'active')>{{ __('products_ui.filters.active') }}</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>{{ __('products_ui.filters.inactive') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="view" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.view') }}</label>
                        <select id="view" name="view" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <option value="table" @selected($viewMode === 'table')>{{ __('products_ui.filters.table') }}</option>
                            <option value="cards" @selected($viewMode === 'cards')>{{ __('products_ui.filters.cards') }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                        <input type="checkbox" name="low_stock" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(request()->boolean('low_stock'))>
                        {{ __('products_ui.filters.low_stock_only') }}
                    </label>
                    <div class="flex flex-wrap gap-2 md:col-span-3 md:justify-end">
                        <a href="{{ route('products.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('products_ui.buttons.clear') }}
                        </a>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('products_ui.buttons.apply') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('products_ui.sections.results')">
                @if ($canManage)
                    <form method="POST" action="{{ route('products.bulk-status') }}" class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                        @csrf
                        <div class="text-sm text-gray-500">
                            <span x-text="selectedIds.length"></span> {{ __('products_ui.labels.search_results') }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="id in selectedIds" :key="id">
                                <input type="hidden" name="product_ids[]" :value="id">
                            </template>
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="category" value="{{ request('category') }}">
                            <input type="hidden" name="status" value="{{ request('status') }}">
                            <input type="hidden" name="view" value="{{ $viewMode }}">
                            <input type="hidden" name="low_stock" value="{{ request('low_stock') }}">
                            <input type="hidden" name="page" value="{{ $products->currentPage() }}">
                            <button type="submit" name="action" value="activate" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" :disabled="selectedIds.length === 0">
                                {{ __('products_ui.buttons.bulk_activate') }}
                            </button>
                            <button type="submit" name="action" value="deactivate" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600" :disabled="selectedIds.length === 0">
                                {{ __('products_ui.buttons.bulk_deactivate') }}
                            </button>
                        </div>
                    </form>
                @endif

                @if ($products->isEmpty())
                    <x-ui.empty :title="__('products_ui.table.empty')" :text="__('products_ui.help.low_stock')" />
                @elseif ($viewMode === 'cards')
                    @if ($canManage)
                        <label class="mb-4 flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" class="rounded border-gray-300" @change="toggleAll($event.target.checked)">
                            {{ __('messages.Select All') }}
                        </label>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($products as $product)
                            @include('products.partials.product-card', ['product' => $product, 'canManage' => $canManage, 'canDelete' => $canDelete])
                        @endforeach
                    </div>
                @else
                    @include('products.partials.table', ['products' => $products, 'canManage' => $canManage, 'canDelete' => $canDelete])
                @endif

                <div class="mt-4">
                    {{ $products->links() }}
                </div>
            </x-ui.card>

            <div
                class="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 p-4"
                x-show="stockFormOpen"
                style="display: none;"
                @keydown.escape.window="stockFormOpen = false"
                @click.self="stockFormOpen = false"
                x-cloak
            >
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('products_ui.buttons.add_stock') }}</h3>
                            <p class="text-sm text-gray-500" x-text="stockProductName"></p>
                        </div>
                        <button type="button" class="text-2xl leading-none text-gray-400 hover:text-gray-600" @click="stockFormOpen = false">&times;</button>
                    </div>
                    <form x-ref="quickStockForm" @submit.prevent="submitQuickStock()" class="space-y-5 px-5 py-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.quantity') }}</label>
                                <input type="number" name="amount" min="0.01" step="0.01" value="1" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.cost_price') }}</label>
                                <input type="number" name="cost_price" min="0" step="0.01" value="" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        @include('products.partials.stock-funding', ['prefix' => 'quick_stock', 'suppliers' => \App\Models\Supplier::where('user_id', $user->ownerId())->orderBy('name')->get(['id', 'name']), 'compact' => true])
                    </form>
                    <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="stockFormOpen = false">
                            {{ __('products_ui.buttons.cancel') }}
                        </button>
                        <button type="button" x-ref="quickStockSubmit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="submitQuickStock()">
                            {{ __('products_ui.buttons.add_stock') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ \App\Support\Assets::versioned('js/product-forms.js') }}"></script>
    @endpush
</x-app-layout>
