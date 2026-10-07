@php
    $existingPictures = is_array($product->pictures) ? $product->pictures : json_decode($product->pictures ?? '[]', true);
    $additionalBarcodes = old('additional_barcodes', $product->barcodes->pluck('barcode')->all());
    $allVariants = $product->variant_group_id
        ? \App\Models\Product::where('variant_group_id', $product->variant_group_id)->orderBy('variant_name')->get()
        : collect();
    $newVariantRows = old('new_variants', [['name' => '', 'quantity' => '0', 'barcode' => '']]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('products_ui.page.edit_title')" :subtitle="__('products_ui.page.edit_subtitle')">
            @if (auth()->user()->canAccessFeature('reports') && (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_reports')))
                @php
                    $reportToday = \App\Support\ShopTime::today(auth()->user()->ownerId());
                    $reportQuery = ['product_id' => $product->id, 'from' => \Illuminate\Support\Carbon::parse($reportToday)->subYear()->addDay()->toDateString(), 'to' => $reportToday, 'popup' => 1];
                @endphp
                <a href="{{ route('reports.print', ['type' => 'product_sales'] + $reportQuery) }}" target="_blank" rel="noopener" class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                    {{ __('charts.products.sales_report') }}
                </a>
                <a href="{{ route('reports.print', ['type' => 'product_movement'] + $reportQuery) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('charts.products.movement_report') }}
                </a>
            @endif
            <a href="{{ route('products.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.back') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div
            class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8"
            x-data="productForm({
                productId: {{ $product->id }},
                hasVariants: 0,
                trackImeis: @js(old('has_imeis', $product->has_imeis ? 1 : 0)),
                imeiSupported: @js(!$product->variant_group_id),
                additionalBarcodes: @js(array_values($additionalBarcodes)),
                newVariants: @js(array_values($newVariantRows)),
                initialImeis: [''],
                initialImeiSupplierId: '',
                initialImeiDate: @js(now()->toDateString()),
                imeiRoutes: {
                    index: @js(route('products.imeis.index', $product)),
                    store: @js(route('products.imeis.store', $product)),
                    destroyBase: @js(url('/products/' . $product->id . '/imeis')),
                },
                duplicateCheckUrl: @js(route('products.check-barcodes')),
                strings: {
                    duplicateWarningTitle: @js(__('products_ui.validation.duplicate_warning_title')),
                    duplicateWarning: @js(__('products_ui.validation.duplicate_barcode_warning')),
                    duplicateClear: @js(__('products_ui.flash.duplicate_clear')),
                    stockError: @js(__('products_ui.flash.stock_error')),
                    duplicateLookupFailed: @js(__('products_ui.validation.duplicate_lookup_failed')),
                    duplicateConfirmText: @js(__('products_ui.buttons.create')),
                    deleteTitle: @js(__('products_ui.flash.delete_batch_title')),
                    deleteMessage: @js(__('products_ui.flash.delete_batch_message')),
                    deleteConfirm: @js(__('products_ui.buttons.delete')),
                    imeiSaved: @js(__('products_ui.flash.imeis_saved')),
                    imeiDeleted: @js(__('products_ui.flash.imei_deleted')),
                }
            })"
        >
            <x-ui.flash />

            <div class="sticky top-4 z-30 rounded-xl border border-gray-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('products.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('products_ui.buttons.cancel') }}
                    </a>
                    <button type="submit" form="product-edit-form" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="productFormBusy">
                        {{ __('products_ui.buttons.update') }}
                    </button>
                </div>
            </div>

            <form id="product-edit-form" action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data" class="space-y-6" data-product-form-body @submit="submitProductForm($event)">
                @csrf
                @method('PUT')

                <x-ui.card :title="__('products_ui.sections.basics')" :subtitle="__('products_ui.help.category')">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="name" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label for="category" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.category') }}</label>
                            <input id="category" name="category" type="text" value="{{ old('category', $product->category) }}" list="edit-product-categories" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <datalist id="edit-product-categories">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label for="barcode" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.barcode') }}</label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input x-ref="mainBarcode" id="barcode" name="barcode" type="text" value="{{ old('barcode', $product->barcode) }}" class="w-full rounded-lg border border-gray-300 px-8 py-3 text-sm font-mono focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 transition-colors">
                                    <svg class="absolute left-3 top-3.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V9z" />
                                    </svg>
                                    <button type="button" id="scan-barcode-btn" class="absolute end-3 top-3.5 h-5 w-5 text-gray-400 hover:text-purple-500 transition-colors cursor-pointer" title="{{ __('messages.Scan with camera') }}">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <button type="button" id="generate-barcode-btn" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" title="Generate unique barcode">
                                    Generate
                                </button>
                            </div>
                        </div>
                        <div class="md:col-span-2 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <label class="block text-sm font-medium text-gray-700">{{ __('products_ui.labels.additional_barcodes') }}</label>
                                <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="addBarcodeRow()">
                                    {{ __('products_ui.buttons.add_barcode') }}
                                </button>
                            </div>
                            <template x-for="(barcode, index) in barcodeRows" :key="index">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <input x-model="barcodeRows[index]" type="text" name="additional_barcodes[]" :id="'additional_barcode_' + index" class="w-full rounded-lg border border-gray-300 px-8 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 transition-colors" :placeholder="$el.dataset.placeholder" data-placeholder="{{ __('products_ui.placeholders.additional_barcode') }}">
                                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h2M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V9z" />
                                        </svg>
                                        <button type="button" class="absolute end-3 top-2.5 h-5 w-5 text-gray-400 hover:text-purple-500 transition-colors cursor-pointer scan-barcode-btn" :data-input-id="'additional_barcode_' + index" title="{{ __('messages.Scan with camera') }}">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100" @click="removeBarcodeRow(index)">
                                        {{ __('products_ui.buttons.remove') }}
                                    </button>
                                </div>
                            </template>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="runDuplicateCheck()">
                                    {{ __('products_ui.buttons.check_barcodes') }}
                                </button>
                                <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" onclick="window.openBarcodeLabelDialog && window.openBarcodeLabelDialog()">
                                    {{ __('products_ui.buttons.print_barcode') }}
                                </button>
                            </div>
                            <template x-if="duplicateMessages.length">
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                    <ul class="list-inside list-disc space-y-1">
                                        <template x-for="message in duplicateMessages" :key="message">
                                            <li x-text="message"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('products_ui.sections.pricing')" :subtitle="__('products_ui.help.tags')">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="cost_price" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.cost_price') }}</label>
                            <input x-ref="costPrice" @input="updateProfitPreview()" id="cost_price" name="cost_price" type="number" min="0" step="0.01" value="{{ old('cost_price', $product->cost_price) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label for="selling_price" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.selling_price') }}</label>
                            <input x-ref="sellingPrice" @input="updateProfitPreview()" id="selling_price" name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price', $product->selling_price) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" required>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">{{ __('products_ui.labels.profit_per_unit') }}</p>
                            <p class="mt-2 text-xl font-bold text-gray-900">₪<span x-text="profitAmount"></span></p>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">{{ __('products_ui.labels.margin') }}</p>
                            <p class="mt-2 text-xl font-bold text-gray-900"><span x-text="profitMargin"></span>%</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                            <input type="checkbox" name="has_tags" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('has_tags', $product->has_tags))>
                            {{ __('products_ui.labels.has_tags') }}
                        </label>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('products_ui.sections.stock')" :subtitle="__('products_ui.help.low_stock')">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-xl bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">{{ __('products_ui.labels.quantity') }}</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format((float) $product->quantity, 2) }}</p>
                        </div>
                        <div>
                            <label for="low_stock_threshold" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.low_stock_threshold') }}</label>
                            <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="1" step="1" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 10) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4" x-show="imeiSupported" x-cloak>
                            @if ($product->variant_group_id)
                                <input type="hidden" name="has_imeis" value="{{ $product->has_imeis ? 1 : 0 }}">
                            @endif
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="has_imeis" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="trackImeis" :disabled="!imeiSupported" @checked(old('has_imeis', $product->has_imeis))>
                                {{ __('products_ui.labels.has_imeis') }}
                            </label>
                        </div>
                    </div>
                </x-ui.card>

                @if (! $product->variant_group_id)
                    <x-ui.card :title="__('products_ui.labels.has_imeis')" :subtitle="__('products_ui.help.imeis')" x-show="trackImeis" x-cloak>
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="rounded-xl bg-gray-50 p-4 text-center">
                                    <p class="text-xs text-gray-500">{{ __('products_ui.stats.total_imeis') }}</p>
                                    <p class="mt-2 text-2xl font-bold text-gray-900" x-text="imeiTotal"></p>
                                </div>
                                <div class="rounded-xl bg-green-50 p-4 text-center">
                                    <p class="text-xs text-green-600">{{ __('products_ui.stats.available_imeis') }}</p>
                                    <p class="mt-2 text-2xl font-bold text-green-700" x-text="imeiUnsoldCount"></p>
                                </div>
                                <div class="rounded-xl bg-red-50 p-4 text-center">
                                    <p class="text-xs text-red-600">{{ __('products_ui.stats.sold_imeis') }}</p>
                                    <p class="mt-2 text-2xl font-bold text-red-700" x-text="imeiSoldCount"></p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <label for="imei_filter" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.status') }}</label>
                                    <select id="imei_filter" x-model="imeiFilter" @change="loadImeis()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                        <option value="all">{{ __('products_ui.filters.all_statuses') }}</option>
                                        <option value="unsold">{{ __('products_ui.stats.available_imeis') }}</option>
                                        <option value="sold">{{ __('products_ui.stats.sold_imeis') }}</option>
                                    </select>
                                </div>
                                <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="loadImeis()">
                                    {{ __('products_ui.buttons.refresh') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <template x-if="imeiLoading">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                                        {{ __('products_ui.flash.loading') }}
                                    </div>
                                </template>
                                <template x-if="!imeiLoading && !imeiItems.length">
                                    <x-ui.empty :title="__('products_ui.table.empty')" :text="__('products_ui.help.imeis')" />
                                </template>
                                <template x-for="item in imeiItems" :key="item.id">
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <p class="font-mono text-sm font-semibold text-gray-900" x-text="item.imei"></p>
                                                <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                                                    <span x-show="item.purchased_at">{{ __('products_ui.labels.date') }}: <span x-text="item.purchased_at"></span></span>
                                                    <span x-show="item.supplier && item.supplier.name">{{ __('products_ui.labels.supplier') }}: <span x-text="item.supplier.name"></span></span>
                                                    <span x-show="item.unit_cost !== null">{{ __('products_ui.labels.cost_price') }}: ₪<span x-text="Number(item.unit_cost).toFixed(2)"></span></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <x-ui.badge tone="green" x-show="!item.is_sold">{{ __('products_ui.status.in_stock') }}</x-ui.badge>
                                                <x-ui.badge tone="red" x-show="item.is_sold">{{ __('products_ui.stats.sold_imeis') }}</x-ui.badge>
                                                <button type="button" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60" x-show="!item.is_sold" @click="deleteImei(item)">
                                                    {{ __('products_ui.buttons.delete') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900">{{ __('products_ui.buttons.add_imei') }}</p>
                                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="addImeiRow()">
                                        {{ __('products_ui.buttons.add_imei') }}
                                    </button>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="edit_new_imeis_supplier" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.supplier') }}</label>
                                        <select id="edit_new_imeis_supplier" x-model="imeiSupplierId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                            <option value="">—</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="edit_new_imeis_date" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.date') }}</label>
                                        <input id="edit_new_imeis_date" type="date" x-model="imeiPurchasedAt" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <template x-for="(imei, index) in imeiRows" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input x-model="imeiRows[index]" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.imei') }}">
                                            <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100" @click="removeImeiRow(index)">
                                                {{ __('products_ui.buttons.remove') }}
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="imeiSaving" @click="saveImeis()">
                                        {{ __('products_ui.buttons.save') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                @endif

                <x-ui.card :title="__('products_ui.sections.images')" :subtitle="__('products_ui.help.drag_images')">
                    <div class="mb-4 flex flex-wrap gap-4 text-sm text-gray-600">
                        <span>{{ __('products_ui.labels.total_images_used') }}: <strong>{{ $usedImageCount }}</strong></span>
                        <span>{{ __('products_ui.labels.remaining_images') }}: <strong>{{ $remainingImageSlots }}</strong></span>
                    </div>
                    @include('products.partials.image-uploader', [
                        'inputId' => 'edit_pictures',
                        'existing' => is_array($existingPictures) ? array_values(array_filter($existingPictures, 'is_string')) : [],
                        'remainingSlots' => $remainingImageSlots,
                        'maxTotal' => $usedImageCount + $remainingImageSlots,
                    ])
                </x-ui.card>
            </form>

            <x-ui.card :title="__('products_ui.sections.variants')" :subtitle="__('products_ui.help.variants')">
                @if ($allVariants->isNotEmpty())
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($allVariants as $variant)
                            <a href="{{ route('products.edit', $variant) }}" class="rounded-full px-3 py-1 text-xs font-semibold {{ $variant->id === $product->id ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' }}">
                                {{ $variant->variant_name ?: $variant->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($product->variant_group_id)
                    <form action="{{ route('products.addVariants', $product) }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="intake_client_uuid" value="{{ old('intake_client_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                        <template x-for="(variant, index) in newVariantRows" :key="index">
                            <div class="grid gap-3 rounded-xl border border-gray-200 p-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.variant_name') }}</label>
                                    <input :name="`new_variants[${index}][name]`" x-model="newVariantRows[index].name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.quantity') }}</label>
                                    <input :name="`new_variants[${index}][quantity]`" x-model="newVariantRows[index].quantity" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.barcode') }}</label>
                                    <input :name="`new_variants[${index}][barcode]`" x-model="newVariantRows[index].barcode" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div class="flex items-end">
                                    <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100" @click="removeNewVariantRow(index)">
                                        {{ __('products_ui.buttons.remove') }}
                                    </button>
                                </div>
                            </div>
                        </template>

                        @include('products.partials.stock-funding', ['prefix' => 'variants_funding', 'suppliers' => $suppliers, 'compact' => true])

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="addNewVariantRow()">
                                {{ __('products_ui.buttons.add_another_variant') }}
                            </button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ __('products_ui.buttons.add_variant') }}
                            </button>
                        </div>
                    </form>
                @else
                    <x-ui.empty :title="__('products_ui.validation.variant_group_required')" />
                @endif
            </x-ui.card>

            <x-ui.card :title="__('products_ui.buttons.add_stock')" :subtitle="__('products_ui.help.funding')">
                <form class="space-y-4" x-ref="inlineStockForm" @submit.prevent="submitInlineStockForm(@js(route('products.add-quantity', $product)))">
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">{{ __('products_ui.help.add_stock_creates_batch') }}</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="inline-stock-amount">{{ __('products_ui.labels.quantity') }}</label>
                            <input id="inline-stock-amount" name="amount" type="number" min="0.01" step="0.01" value="1" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="inline-stock-cost">{{ __('products_ui.labels.cost_price') }}</label>
                            <input id="inline-stock-cost" name="cost_price" type="number" min="0" step="0.01" required value="{{ number_format((float) $product->cost_price, 2, '.', '') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    @include('products.partials.stock-funding', ['prefix' => 'inline_stock', 'suppliers' => $suppliers, 'compact' => true])
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        {{ __('products_ui.buttons.add_stock') }}
                    </button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('products_ui.sections.batches')" :subtitle="__('products_ui.table.batches')">
                @if ($product->batches->isEmpty())
                    <x-ui.empty :title="__('products_ui.table.empty')" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('products_ui.table.id') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('products_ui.labels.quantity') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('products_ui.labels.cost_price') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('products_ui.labels.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($product->batches as $batch)
                                    <tr>
                                        <td class="px-4 py-4 text-start">
                                            <p>#{{ $batch->id }}</p>
                                            @if ($batch->purchase_bill_id)
                                                <p class="mt-1 text-xs text-amber-600">{{ __('products_ui.validation.funded_batch_locked') }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-start">
                                            <input type="number" min="0" step="0.01" value="{{ number_format((float) $batch->quantity, 2, '.', '') }}" class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm" x-ref="batchQty{{ $batch->id }}" @disabled($batch->purchase_bill_id)>
                                        </td>
                                        <td class="px-4 py-4 text-start">
                                            <input type="number" min="0" step="0.01" value="{{ number_format((float) $batch->cost_price, 2, '.', '') }}" class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm" x-ref="batchCost{{ $batch->id }}" @disabled($batch->purchase_bill_id)>
                                        </td>
                                        <td class="px-4 py-4 text-end">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60" @click="submitBatchUpdate({{ $batch->id }}, @js(route('batches.update', $batch)), $refs.batchQty{{ $batch->id }}, $refs.batchCost{{ $batch->id }})" @disabled($batch->purchase_bill_id)>
                                                    {{ __('products_ui.buttons.save') }}
                                                </button>
                                                <button type="button" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60" @click="submitBatchDelete(@js(route('batches.destroy', $batch)))" @disabled($batch->purchase_bill_id)>
                                                    {{ __('products_ui.buttons.delete') }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>

    @include('products.partials.barcode-label-dialog')

    @push('scripts')
        <script src="{{ \App\Support\Assets::versioned('js/image-editor.js') }}"></script>
        <script src="{{ \App\Support\Assets::versioned('js/product-forms.js') }}"></script>
        <script>
            async function initBarcodeScanner(inputId) {
                if (document.getElementById('barcode-scanner-modal')) {
                    return;
                }

                const scannerModal = document.createElement('div');
                scannerModal.id = 'barcode-scanner-modal';
                scannerModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90';
                scannerModal.innerHTML = `
                    <div class="bg-white rounded-lg p-4 w-full max-w-lg mx-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">{{ __('messages.Scan Barcode') }}</h3>
                            <button type="button" id="close-scanner" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div id="scanner-container" class="relative bg-black rounded-lg overflow-hidden" style="height: 350px;"></div>
                        <p class="text-sm text-gray-500 mt-2 text-center">{{ __('messages.Point camera at barcode') }}</p>
                    </div>
                `;
                document.body.appendChild(scannerModal);

                const inputElement = document.getElementById(inputId);
                let hasScanned = false;
                let html5Qrcode = null;

                if (typeof Html5Qrcode === 'undefined') {
                    if (!navigator.onLine) {
                        const notify = typeof showNotification === 'function' ? showNotification : (msg) => alert(msg);
                        notify('{{ __('messages.Barcode scanner is unavailable offline') }}', 'warning');
                        scannerModal.remove();
                        return;
                    }
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://unpkg.com/html5-qrcode';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                if (typeof Html5Qrcode === 'undefined') {
                    const notify = typeof showNotification === 'function' ? showNotification : (msg) => alert(msg);
                    notify('{{ __('messages.Error loading scanner') }}', 'error');
                    scannerModal.remove();
                    return;
                }

                try {
                    const scannerContainer = document.getElementById('scanner-container');

                    const videoElement = document.createElement('video');
                    videoElement.style.width = '100%';
                    videoElement.style.height = '100%';
                    videoElement.style.objectFit = 'cover';
                    videoElement.setAttribute('playsinline', 'true');
                    scannerContainer.appendChild(videoElement);

                    html5Qrcode = new Html5Qrcode("scanner-container");

                    html5Qrcode.start({
                            facingMode: "environment"
                        }, {
                            fps: 10,
                            qrbox: {
                                width: 250,
                                height: 150
                            },
                            aspectRatio: 1.0
                        },
                        (decodedText, decodedResult) => {
                            if (hasScanned) return;

                            const code = decodedText.trim();

                            if (code.length < 4) {
                                return;
                            }

                            hasScanned = true;

                            html5Qrcode.stop().then(() => {
                                scannerModal.remove();
                                inputElement.value = code;
                                inputElement.dispatchEvent(new Event('input', {
                                    bubbles: true
                                }));

                                const enterEvent = new KeyboardEvent('keydown', {
                                    key: 'Enter',
                                    keyCode: 13,
                                    which: 13,
                                    bubbles: true
                                });
                                inputElement.dispatchEvent(enterEvent);
                            }).catch(err => {
                                scannerModal.remove();
                                inputElement.value = code;
                                inputElement.dispatchEvent(new Event('input', {
                                    bubbles: true
                                }));
                                const enterEvent = new KeyboardEvent('keydown', {
                                    key: 'Enter',
                                    keyCode: 13,
                                    which: 13,
                                    bubbles: true
                                });
                                inputElement.dispatchEvent(enterEvent);
                            });
                        },
                        (errorMessage) => {
                        }
                    ).catch(err => {
                        console.error('Camera start error:', err);
                        alert('{{ __('messages.Camera access denied or not available') }}');
                        scannerModal.remove();
                    });

                } catch (err) {
                    console.error('HTML5 QR Code scanner error:', err);
                    alert('{{ __('messages.Camera access denied or not available') }}');
                    scannerModal.remove();
                }

                document.getElementById('close-scanner').addEventListener('click', function() {
                    if (html5Qrcode) {
                        html5Qrcode.stop().then(() => {
                            scannerModal.remove();
                        }).catch(err => {
                            scannerModal.remove();
                        });
                    } else {
                        scannerModal.remove();
                    }
                });

                scannerModal.addEventListener('click', function(e) {
                    if (e.target === scannerModal) {
                        if (html5Qrcode) {
                            html5Qrcode.stop().then(() => {
                                scannerModal.remove();
                            }).catch(err => {
                                scannerModal.remove();
                            });
                        } else {
                            scannerModal.remove();
                        }
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                const scanBtn = document.getElementById('scan-barcode-btn');
                if (scanBtn) {
                    scanBtn.addEventListener('click', function() {
                        initBarcodeScanner('barcode');
                    });
                }

                document.addEventListener('click', function(e) {
                    const scanAdditionalBtn = e.target.closest('.scan-barcode-btn');
                    if (scanAdditionalBtn) {
                        const inputId = scanAdditionalBtn.dataset.inputId;
                        if (inputId) {
                            initBarcodeScanner(inputId);
                        }
                    }
                });

                const generateBarcodeBtn = document.getElementById('generate-barcode-btn');
                if (generateBarcodeBtn) {
                    generateBarcodeBtn.addEventListener('click', function() {
                        const barcodeInput = document.getElementById('barcode');
                        if (barcodeInput) {
                            const randomSuffix = Math.floor(1000 + Math.random() * 9000);
                            barcodeInput.value = 'PRD-{{ $product->id }}-' + randomSuffix;
                            barcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
