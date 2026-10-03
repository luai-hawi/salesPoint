<div id="pos-layout-drawer" class="pointer-events-none fixed inset-y-0 end-0 z-50 hidden w-full max-w-xl">
    <div class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity" id="pos-layout-backdrop"></div>
    <div
        class="relative ms-auto flex h-full w-full max-w-xl translate-x-full flex-col border-s border-gray-200 bg-white shadow-2xl transition-transform">
        <div class="sticky top-0 border-b border-gray-100 bg-white px-5 py-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('pos.customize') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('pos.customizer_subtitle') }}</p>
                    @if ($posLayoutLocked)
                        <p class="mt-2 text-xs font-medium text-amber-700">{{ __('pos.team_default_locked_note') }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="pos-layout-reset"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('pos.reset') }}
                    </button>
                    <button type="button" id="close-pos-layout-drawer"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('pos.cancel') }}
                    </button>
                </div>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4">
            <div class="space-y-6">
                <section>
                    <h4 class="mb-3 text-sm font-semibold text-gray-900">{{ __('pos.presets') }}</h4>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (['classic', 'focus', 'visual', 'cashier', 'custom'] as $preset)
                            <button type="button"
                                class="pos-preset rounded-xl border border-gray-200 px-4 py-4 text-start hover:border-indigo-400 hover:bg-indigo-50"
                                data-preset="{{ $preset }}">
                                <div class="text-sm font-semibold text-gray-900">{{ __("pos.preset_{$preset}") }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ __("pos.preset_{$preset}_desc") }}</div>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-products-width">
                            {{ __('pos.products_width') }}
                        </label>
                        <input id="layout-products-width" data-layout-input="products_width" type="range" min="25" max="75" step="1"
                            class="w-full">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-card-size">{{ __('pos.product_card_size') }}</label>
                            <select id="layout-card-size" data-layout-input="product_card_size"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="s">S</option>
                                <option value="m">M</option>
                                <option value="l">L</option>
                                <option value="xl">XL</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-price-badge">{{ __('pos.price_badge_size') }}</label>
                            <select id="layout-price-badge" data-layout-input="price_badge_size"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="sm">S</option>
                                <option value="md">M</option>
                                <option value="lg">L</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-image-aspect">{{ __('pos.image_aspect') }}</label>
                            <select id="layout-image-aspect" data-layout-input="image_aspect"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="square">1:1</option>
                                <option value="4:3">4:3</option>
                                <option value="16:9">16:9</option>
                                <option value="cover">{{ __('pos.image_cover') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-grid-columns">{{ __('pos.grid_columns') }}</label>
                            <select id="layout-grid-columns" data-layout-input="grid_columns"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="auto">{{ __('pos.grid_auto') }}</option>
                                @for ($i = 2; $i <= 8; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-summary-position">{{ __('pos.summary_panel') }}</label>
                            <select id="layout-summary-position" data-layout-input="summary_position"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="side">{{ __('pos.summary_side') }}</option>
                                <option value="under">{{ __('pos.summary_under') }}</option>
                                <option value="hidden">{{ __('pos.summary_hidden') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-bill-side">{{ __('pos.bill_side') }}</label>
                            <select id="layout-bill-side" data-layout-input="bill_side"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="start">{{ __('pos.side_start') }}</option>
                                <option value="end">{{ __('pos.side_end') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-density">{{ __('pos.density') }}</label>
                            <select id="layout-density" data-layout-input="density"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="comfortable">{{ __('pos.comfortable') }}</option>
                                <option value="compact">{{ __('pos.compact') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700" for="layout-font-scale">{{ __('pos.font_scale') }}</label>
                            <input id="layout-font-scale" data-layout-input="font_scale" type="range" min="90" max="130" step="5"
                                class="w-full">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ([
                            'show_image' => __('pos.show_images'),
                            'show_stock' => __('pos.show_stock'),
                            'show_category_badge' => __('pos.show_category_badge'),
                            'category_bar' => __('pos.category_bar'),
                            'products_tall' => __('pos.products_tall'),
                            'quick_actions' => __('pos.quick_actions'),
                            'kiosk_mode' => __('pos.kiosk_mode'),
                        ] as $key => $label)
                            <label class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700">
                                <span>{{ $label }}</span>
                                <input type="checkbox" data-layout-input="{{ $key }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            </label>
                        @endforeach
                    </div>
                </section>

                <p class="text-xs text-gray-500">{{ __('pos.preview_only_mobile') }}</p>
            </div>
        </div>
        <div class="sticky bottom-0 border-t border-gray-100 bg-white px-5 py-4">
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if (auth()->user()->isOwnerAccount())
                    <label class="me-auto flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" id="layout-lock-team" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <span>{{ __('pos.lock_team') }}</span>
                    </label>
                    <button type="button" id="pos-layout-apply-team"
                        class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                        {{ __('pos.apply_to_team') }}
                    </button>
                @endif
                <button type="button" id="pos-layout-save"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('pos.save') }}
                </button>
            </div>
        </div>
    </div>
</div>
