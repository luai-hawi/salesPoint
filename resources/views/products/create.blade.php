@php
    $variantRows = old('variants', [['name' => '', 'quantity' => '0', 'barcode' => '']]);
    $additionalBarcodes = old('additional_barcodes', []);
    $imeiRows = old('new_imeis', ['']);
    $intakeClientUuid = old('intake_client_uuid', (string) \Illuminate\Support\Str::uuid());
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('products_ui.page.create_title')" :subtitle="__('products_ui.page.create_subtitle')">
            <a href="{{ route('products.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.back') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div
            class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8"
            x-data="productForm({
                hasVariants: @js(old('has_variants', '0')),
                trackImeis: @js(old('has_imeis', 0)),
                additionalBarcodes: @js(array_values($additionalBarcodes)),
                variants: @js(array_values($variantRows)),
                initialImeis: @js(array_values($imeiRows)),
                initialImeiSupplierId: @js(old('new_imeis_supplier', '')),
                initialImeiDate: @js(old('new_imeis_date', now()->toDateString())),
                duplicateCheckUrl: @js(route('products.check-barcodes')),
                strings: {
                    duplicateWarningTitle: @js(__('products_ui.validation.duplicate_warning_title')),
                    duplicateWarning: @js(__('products_ui.validation.duplicate_barcode_warning')),
                    duplicateClear: @js(__('products_ui.flash.duplicate_clear')),
                    stockError: @js(__('products_ui.flash.stock_error')),
                    duplicateLookupFailed: @js(__('products_ui.validation.duplicate_lookup_failed')),
                    duplicateConfirmText: @js(__('products_ui.buttons.create')),
                }
            })"
        >
            <x-ui.flash />

            <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6" data-product-form-body @submit="submitProductForm($event)">
                @csrf
                <input type="hidden" name="intake_client_uuid" value="{{ $intakeClientUuid }}">

                <div class="sticky top-4 z-30 rounded-xl border border-gray-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a href="{{ route('products.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('products_ui.buttons.cancel') }}
                        </a>
                        <button type="submit" name="save_action" value="save_add_another" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="productFormBusy">
                            {{ __('products_ui.buttons.save_add_another') }}
                        </button>
                        <button type="submit" name="save_action" value="save" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="productFormBusy">
                            {{ __('products_ui.buttons.create') }}
                        </button>
                    </div>
                </div>

                <x-ui.card :title="__('products_ui.sections.basics')" :subtitle="__('products_ui.help.category')">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="name" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.name') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.name') }}" required>
                        </div>

                        <div>
                            <label for="category" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.category') }}</label>
                            <input id="category" name="category" type="text" value="{{ old('category') }}" list="product-categories" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.category') }}">
                            <datalist id="product-categories">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}"></option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="rounded-xl border border-purple-200 bg-purple-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-purple-900">{{ __('products_ui.sections.variants') }}</p>
                                    <p class="text-xs text-purple-700">{{ __('products_ui.help.variants') }}</p>
                                </div>
                                <label class="inline-flex items-center gap-2">
                                    <input type="hidden" name="has_variants" :value="hasVariants ? 1 : 0">
                                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="hasVariants">
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="barcode" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.barcode') }}</label>
                            <input x-ref="mainBarcode" id="barcode" name="barcode" type="text" value="{{ old('barcode') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.barcode') }}" :disabled="hasVariants">
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
                                    <input x-model="barcodeRows[index]" type="text" name="additional_barcodes[]" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" :placeholder="$el.dataset.placeholder" data-placeholder="{{ __('products_ui.placeholders.additional_barcode') }}" :disabled="hasVariants">
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
                            <input x-ref="costPrice" @input="updateProfitPreview()" id="cost_price" name="cost_price" type="number" min="0" step="0.01" value="{{ old('cost_price') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.cost_price') }}" required>
                        </div>
                        <div>
                            <label for="selling_price" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.selling_price') }}</label>
                            <input x-ref="sellingPrice" @input="updateProfitPreview()" id="selling_price" name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.selling_price') }}" required>
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
                            <input type="checkbox" name="has_tags" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('has_tags'))>
                            {{ __('products_ui.labels.has_tags') }}
                        </label>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('products_ui.sections.stock')" :subtitle="__('products_ui.help.imeis')">
                    <div class="grid gap-4 md:grid-cols-2" x-show="!hasVariants">
                        <div>
                            <label for="quantity" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.quantity') }}</label>
                            <input id="quantity" name="quantity" type="number" min="0" step="0.01" value="{{ old('quantity', '0') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="low_stock_threshold" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.low_stock_threshold') }}</label>
                            <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="1" step="1" value="{{ old('low_stock_threshold', '10') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="space-y-4" x-show="hasVariants">
                        <template x-for="(variant, index) in variantRows" :key="index">
                            <div class="grid gap-3 rounded-xl border border-gray-200 p-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.variant_name') }}</label>
                                    <input :name="`variants[${index}][name]`" x-model="variantRows[index].name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.quantity') }}</label>
                                    <input :name="`variants[${index}][quantity]`" x-model="variantRows[index].quantity" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.barcode') }}</label>
                                    <input :name="`variants[${index}][barcode]`" x-model="variantRows[index].barcode" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div class="flex items-end">
                                    <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100" @click="removeVariantRow(index)">
                                        {{ __('products_ui.buttons.remove') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="addVariantRow()">
                            {{ __('products_ui.buttons.add_another_variant') }}
                        </button>
                    </div>

                    <div class="mt-4 rounded-xl border border-gray-200 p-4" x-show="!hasVariants" x-cloak>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="has_imeis" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="trackImeis" @checked(old('has_imeis'))>
                            {{ __('products_ui.labels.has_imeis') }}
                        </label>
                        <div class="mt-4 grid gap-4 md:grid-cols-2" x-show="trackImeis" x-cloak>
                            <div class="md:col-span-2 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.has_imeis') }}</label>
                                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="addImeiRow()">
                                        {{ __('products_ui.buttons.add_imei') }}
                                    </button>
                                </div>
                                <template x-for="(imei, index) in imeiRows" :key="index">
                                    <div class="flex items-center gap-2">
                                        <input x-model="imeiRows[index]" name="new_imeis[]" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="{{ __('products_ui.placeholders.imei') }}" :disabled="!trackImeis || hasVariants">
                                        <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100" @click="removeImeiRow(index)">
                                            {{ __('products_ui.buttons.remove') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div>
                                <label for="new_imeis_supplier" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.supplier') }}</label>
                                <select id="new_imeis_supplier" name="new_imeis_supplier" x-model="imeiSupplierId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" :disabled="!trackImeis || hasVariants">
                                    <option value="">—</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) old('new_imeis_supplier') === (string) $supplier->id)>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="new_imeis_date" class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.labels.date') }}</label>
                                <input id="new_imeis_date" name="new_imeis_date" type="date" value="{{ old('new_imeis_date', now()->toDateString()) }}" x-model="imeiPurchasedAt" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" :disabled="!trackImeis || hasVariants">
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('products_ui.sections.images')" :subtitle="__('products_ui.help.drag_images')">
                    <div class="mb-4 flex flex-wrap gap-4 text-sm text-gray-600">
                        <span>{{ __('products_ui.labels.total_images_used') }}: <strong>{{ $usedImageCount }}</strong></span>
                        <span>{{ __('products_ui.labels.remaining_images') }}: <strong>{{ $remainingImageSlots }}</strong></span>
                    </div>
                    @include('products.partials.image-uploader', [
                        'inputId' => 'create_pictures',
                        'existing' => [],
                        'remainingSlots' => $remainingImageSlots,
                        'maxTotal' => $usedImageCount + $remainingImageSlots,
                    ])
                </x-ui.card>

                <x-ui.card :title="__('products_ui.sections.funding')" :subtitle="__('products_ui.help.funding')">
                    @include('products.partials.stock-funding', ['prefix' => 'create_funding', 'suppliers' => $suppliers])
                </x-ui.card>
            </form>
        </div>
    </div>

    @include('products.partials.barcode-label-dialog')

    @push('scripts')
        <script src="{{ \App\Support\Assets::versioned('js/image-editor.js') }}"></script>
        <script src="{{ \App\Support\Assets::versioned('js/product-forms.js') }}"></script>
    @endpush
</x-app-layout>
