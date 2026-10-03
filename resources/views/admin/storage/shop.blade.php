<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$shop->name" :subtitle="__('admin_storage.shop_page.subtitle')">
            <a href="{{ route('admin.storage.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('admin_storage.buttons.back_to_manager') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6" x-data="shopStoragePage()" x-init="syncSelection()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.shops.unique') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $summary['count'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.shops.images') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ \App\Services\Admin\ShopStorageService::humanBytes($summary['bytes'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.shops.missing') }}</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">{{ $summary['missing'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.shops.potential_savings') }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ \App\Services\Admin\ShopStorageService::humanBytes($summary['potential_savings'] ?? 0) }}</p>
                </div>
            </div>

            <x-ui.card :title="__('admin_storage.cards.images')" :subtitle="__('admin_storage.shop_page.selection_help')">
                <x-slot name="actions">
                    <form method="GET" action="{{ route('admin.storage.shop', $shop) }}" class="flex flex-wrap items-center gap-2">
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="{{ __('admin_storage.shop_page.search_placeholder') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin_storage.buttons.run') }}</button>
                    </form>
                </x-slot>

                @if ($images->count())
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="selectAll(true)">{{ __('admin_storage.buttons.select_all') }}</button>
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="selectAll(false)">{{ __('admin_storage.buttons.clear_selection') }}</button>
                        <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="compressSelected()">{{ __('admin_storage.buttons.compress') }}</button>
                    </div>

                    <form id="delete-images-form" method="POST" action="{{ route('admin.storage.shop.images.delete', $shop) }}">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($images as $image)
                                <label class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <input type="checkbox" class="mt-1 rounded border-gray-300 text-indigo-600" name="paths[]" value="{{ $image['path'] }}" x-model="selected" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $image['path'] }}</p>
                                            <p class="text-xs text-gray-500">{{ \App\Services\Admin\ShopStorageService::humanBytes($image['bytes']) }}</p>
                                            <p class="text-xs text-gray-500">{{ __('admin_storage.shop_page.dimensions') }}: {{ $image['width'] ?? '—' }} × {{ $image['height'] ?? '—' }}</p>
                                            @if ($image['exists'])
                                                <img src="{{ $image['url'] }}" alt="" loading="lazy" class="mt-3 h-40 w-full rounded-lg object-cover" />
                                            @else
                                                <div class="mt-3 flex h-40 items-center justify-center rounded-lg bg-red-50 text-sm font-semibold text-red-700">{{ __('admin_storage.shop_page.missing_file') }}</div>
                                            @endif
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach ($image['products'] as $product)
                                                    <a href="#product-{{ $product['id'] }}" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                                        {{ $product['name'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </form>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" @click="confirmDeleteSelected()">{{ __('admin_storage.buttons.delete') }}</button>
                        <form method="POST" action="{{ route('admin.storage.shop.images.download', $shop) }}" x-ref="downloadForm">
                            @csrf
                            <template x-for="path in selected" :key="path">
                                <input type="hidden" name="paths[]" :value="path">
                            </template>
                            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin_storage.buttons.download') }}</button>
                        </form>
                    </div>

                    <div class="mt-4">
                        {{ $images->links() }}
                    </div>
                @else
                    <x-ui.empty :title="__('admin_storage.shop_page.no_images')" />
                @endif
            </x-ui.card>

            <x-ui.card :title="__('admin_storage.cards.missing')">
                @if ($missingReferences)
                    <form method="POST" action="{{ route('admin.storage.shop.references.clean', $shop) }}" class="space-y-4">
                        @csrf
                        <ul class="space-y-2 text-sm text-gray-700">
                            @foreach ($missingReferences as $missing)
                                <li class="rounded-lg bg-gray-50 px-3 py-2">
                                    {{ $missing['path'] }} — {{ $missing['product_name'] }}
                                    <input type="hidden" name="paths[]" value="{{ $missing['path'] }}">
                                </li>
                            @endforeach
                        </ul>
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin_storage.buttons.clean_references') }}</button>
                    </form>
                @else
                    <x-ui.empty :title="__('admin_storage.shop_page.no_missing')" />
                @endif
            </x-ui.card>

            <x-ui.card :title="__('admin_storage.cards.products')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('admin_storage.fields.id') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.shop') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($products as $product)
                                <tr id="product-{{ $product['id'] }}">
                                    <td class="px-4 py-3">{{ $product['id'] }}</td>
                                    <td class="px-4 py-3">{{ $product['name'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('shopStoragePage', () => ({
                    selected: [],
                    async ensureSp() {
                        if (window.SP) {
                            return window.SP;
                        }

                        await new Promise((resolve, reject) => {
                            const startedAt = Date.now();
                            const check = () => {
                                if (window.SP) {
                                    resolve(window.SP);
                                    return;
                                }

                                if (Date.now() - startedAt > 8000) {
                                    reject(new Error('SP helpers failed to load.'));
                                    return;
                                }

                                window.setTimeout(check, 25);
                            };

                            check();
                        });

                        return window.SP;
                    },
                    syncSelection() {
                        this.selected = [];
                    },
                    selectAll(state) {
                        this.selected = state ? Array.from(document.querySelectorAll('input[name="paths[]"]')).map(input => input.value) : [];
                    },
                    async compressSelected() {
                        const sp = await this.ensureSp();
                        if (!this.selected.length) {
                            sp.toast(@js(__('admin_storage.messages.no_images_selected')), 'warning');
                            return;
                        }
                        await sp.fetchJson(@js(route('admin.storage.compression.run')), {
                            method: 'POST',
                            body: { scope: 'selected', shop_id: @js($shop->id), paths: this.selected, max_width: 1200, max_height: 1200, quality: 78 }
                        });
                        sp.toast(@js(__('admin_storage.messages.compression_complete')), 'success');
                        window.location.reload();
                    },
                    async confirmDeleteSelected() {
                        const sp = await this.ensureSp();
                        if (!this.selected.length) {
                            sp.toast(@js(__('admin_storage.messages.no_images_selected')), 'warning');
                            return;
                        }

                        const confirmed = await sp.confirm({
                            title: @js(__('admin_storage.buttons.delete')),
                            message: @js(__('admin_storage.cleanup.confirm_delete_images')),
                            confirmText: @js(__('admin_storage.buttons.delete')),
                            danger: true,
                        });

                        if (confirmed) {
                            document.getElementById('delete-images-form').submit();
                        }
                    }
                }));
            });
        </script>
    @endpush
</x-app-layout>
