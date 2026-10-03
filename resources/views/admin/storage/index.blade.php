<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin_storage.title')" :subtitle="__('admin_storage.subtitle')">
            <a href="{{ route('compress.cleanup.images') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('admin_storage.legacy_page_title') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6" x-data="adminStorageManager({
        overviewUrl: @js(route('admin.storage.overview')),
        previewCompressionUrl: @js(route('admin.storage.compression.preview')),
        runCompressionUrl: @js(route('admin.storage.compression.run')),
        previewCleanupUrl: @js(route('admin.storage.cleanup.preview')),
        runCleanupUrl: @js(route('admin.storage.cleanup.run')),
        translations: @js([
            'previewReady' => __('admin_storage.messages.preview_ready'),
            'cleanupDone' => __('admin_storage.messages.cleanup_complete'),
            'compressionDone' => __('admin_storage.messages.compression_complete'),
            'cleanupHint' => __('admin_storage.cleanup.preview_hint'),
            'actionLabels' => __('admin_storage.actions'),
        ]),
    })" x-init="boot()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="flex flex-wrap gap-2">
                @foreach (['overview', 'shops', 'cleanup', 'backups', 'logs'] as $tab)
                    <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold"
                        :class="activeTab === '{{ $tab }}' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                        @click="activeTab = '{{ $tab }}'">
                        {{ __('admin_storage.tabs.' . $tab) }}
                    </button>
                @endforeach
            </div>

            <div x-show="activeTab === 'overview'" class="space-y-6">
                <x-ui.card :title="__('admin_storage.cards.overview')" :subtitle="__('admin_storage.cleanup.preview_hint')">
                    <x-slot name="actions">
                        <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="loadOverview(true)">
                            {{ __('admin_storage.buttons.recalculate') }}
                        </button>
                    </x-slot>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <template x-for="card in overviewCards" :key="card.label + '-render'">
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500" x-text="card.label"></p>
                                <p class="mt-1 text-2xl font-bold text-gray-900" x-text="card.value"></p>
                            </div>
                        </template>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('admin_storage.cards.compression')" :subtitle="__('admin_storage.subtitle')">
                    <form class="grid gap-4 md:grid-cols-4" @submit.prevent="previewCompression()">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin_storage.fields.max_width') }}</label>
                            <input type="number" min="1" max="5000" x-model="compression.max_width" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin_storage.fields.max_height') }}</label>
                            <input type="number" min="1" max="5000" x-model="compression.max_height" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin_storage.fields.quality') }}</label>
                            <input type="number" min="30" max="95" x-model="compression.quality" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('admin_storage.buttons.preview') }}
                            </button>
                            <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="runCompression()">
                                {{ __('admin_storage.buttons.run_now') }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-700" x-show="compression.preview" x-cloak>
                        <p class="font-semibold" x-text="compression.previewText"></p>
                        <div class="mt-3 h-3 overflow-hidden rounded-full bg-gray-200">
                            <div class="h-3 rounded-full bg-indigo-600 transition-all" :style="`width: ${compression.progress}%`"></div>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div x-show="activeTab === 'shops'" class="space-y-6">
                <x-ui.card :title="__('admin_storage.cards.shops')">
                    <form method="GET" action="{{ route('admin.storage.index') }}" class="grid gap-4 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin_storage.shops.search') }}</label>
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin_storage.shops.sort') }}</label>
                            <select name="sort" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($sortOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'shop') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin_storage.shops.direction') }}</label>
                            <select name="direction" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>ASC</option>
                                <option value="desc" @selected(($filters['direction'] ?? 'asc') === 'desc')>DESC</option>
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin_storage.buttons.run') }}</button>
                        </div>
                    </form>

                    @if ($shops->count())
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.shop') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.status') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.entries') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.images') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.image_limit') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.potential_savings') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.last_compressed') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($shops as $shop)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-gray-900">{{ $shop['name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $shop['email'] }}</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-ui.badge tone="{{ $shop['status'] === 'disabled' ? 'red' : 'green' }}">
                                                    {{ __('admin_storage.status.' . $shop['status']) }}
                                                </x-ui.badge>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">
                                                {{ $shop['entries_used'] }} / {{ $shop['entry_limit'] ?? '∞' }} / {{ $shop['entry_remaining'] ?? '∞' }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">
                                                <div>{{ $shop['images_unique'] }} {{ __('admin_storage.shops.unique') }}</div>
                                                <div>{{ \App\Services\Admin\ShopStorageService::humanBytes($shop['images_bytes']) }}</div>
                                                <div class="text-xs text-gray-500">{{ __('admin_storage.shops.average') }}: {{ \App\Services\Admin\ShopStorageService::humanBytes($shop['images_average']) }}</div>
                                                <div class="text-xs text-red-600">{{ __('admin_storage.shops.missing') }}: {{ $shop['images_missing'] }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">
                                                {{ $shop['image_slots_used'] }} / {{ $shop['image_limit'] ?? '∞' }} / {{ $shop['image_slots_remaining'] ?? '∞' }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">{{ \App\Services\Admin\ShopStorageService::humanBytes($shop['potential_savings']) }}</td>
                                            <td class="px-4 py-3 text-gray-700">{{ $shop['last_compressed']?->format('Y-m-d H:i') ?? '—' }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ route('admin.storage.shop', $shop['id']) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                        {{ __('admin_storage.buttons.open') }}
                                                    </a>
                                                    <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700" @click="runCompression('shop', {{ $shop['id'] }})">
                                                        {{ __('admin_storage.buttons.compress') }}
                                                    </button>
                                                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="recalculateShop(@js(route('admin.storage.shop.recalculate', $shop['id'])))">{{ __('admin_storage.buttons.recalculate') }}</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $shops->links() }}
                        </div>
                    @else
                        <x-ui.empty :title="__('admin_storage.shops.empty')" />
                    @endif
                </x-ui.card>
            </div>

            <div x-show="activeTab === 'cleanup'">
                <x-ui.card :title="__('admin_storage.cards.cleanup')" :subtitle="__('admin_storage.cleanup.preview_hint')">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ([
                            'orphan-images' => __('admin_storage.cleanup.orphan_images'),
                            'missing-references' => __('admin_storage.cleanup.missing_references'),
                            'empty-folders' => __('admin_storage.cleanup.empty_folders'),
                            'old-backups' => __('admin_storage.cleanup.old_backups'),
                            'logs' => __('admin_storage.cleanup.logs'),
                            'expired-sessions' => __('admin_storage.cleanup.expired_sessions'),
                            'failed-jobs' => __('admin_storage.cleanup.failed_jobs'),
                            'cache' => __('admin_storage.cleanup.cache'),
                            'compiled-views' => __('admin_storage.cleanup.compiled_views'),
                            'temp-uploads' => __('admin_storage.cleanup.temp_uploads'),
                        ] as $action => $label)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <p class="font-semibold text-gray-900">{{ $label }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="previewCleanup('{{ $action }}')">
                                        {{ __('admin_storage.buttons.preview') }}
                                    </button>
                                    <button type="button" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700" @click="runCleanup('{{ $action }}')">
                                        {{ __('admin_storage.buttons.run_now') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div x-show="cleanup.previewItems.length" class="mt-6 rounded-xl border border-gray-200 bg-white p-4" x-cloak>
                        <h4 class="text-sm font-semibold text-gray-900" x-text="cleanup.previewTitle"></h4>
                        <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto text-sm text-gray-700">
                            <template x-for="(item, index) in cleanup.previewItems" :key="index">
                                <li class="rounded-lg bg-gray-50 px-3 py-2" x-text="formatCleanupItem(item)"></li>
                            </template>
                        </ul>
                    </div>
                </x-ui.card>
            </div>

            <div x-show="activeTab === 'backups'">
                <x-ui.card :title="__('admin_storage.cards.backups')">
                    <x-slot name="actions">
                        <form method="POST" action="{{ route('admin.storage.backups.create') }}">
                            @csrf
                            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ __('admin_storage.buttons.create_backup') }}
                            </button>
                        </form>
                    </x-slot>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.fields.file') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.fields.size') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.fields.modified') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse ($backups as $backup)
                                    <tr>
                                        <td class="px-4 py-3">{{ $backup['filename'] }}</td>
                                        <td class="px-4 py-3">{{ \App\Services\Admin\ShopStorageService::humanBytes($backup['bytes']) }}</td>
                                        <td class="px-4 py-3">{{ \Carbon\Carbon::createFromTimestamp($backup['modified_at'])->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.storage.backups.download', $backup['filename']) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                {{ __('admin_storage.buttons.download') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">{{ __('admin_storage.messages.no_backups') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>

            <div x-show="activeTab === 'logs'">
                <x-ui.card :title="__('admin_storage.cards.logs')">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.fields.file') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.fields.size') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('admin_storage.shops.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="px-4 py-3">{{ $log['filename'] }}</td>
                                        <td class="px-4 py-3">{{ \App\Services\Admin\ShopStorageService::humanBytes($log['bytes']) }}</td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.storage.logs.download', $log['filename']) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                {{ __('admin_storage.buttons.download') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">{{ __('admin_storage.messages.no_logs') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('adminStorageManager', (config) => ({
                    activeTab: 'overview',
                    overviewCards: [],
                    compression: { max_width: 1200, max_height: 1200, quality: 78, preview: null, previewText: '', progress: 0 },
                    cleanup: { previewAction: null, previewTitle: '', previewItems: [] },
                    async boot() {
                        await this.ensureSp();
                        await this.loadOverview();
                    },
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
                    async loadOverview(fresh = false) {
                        const sp = await this.ensureSp();
                        const data = await sp.fetchJson(`${config.overviewUrl}${fresh ? '?fresh=1' : ''}`);
                        const overview = data.overview;
                        this.overviewCards = [
                            { label: @js(__('admin_storage.stats.disk_total')), value: this.formatBytes(overview.disk.total) },
                            { label: @js(__('admin_storage.stats.disk_used')), value: this.formatBytes(overview.disk.used) },
                            { label: @js(__('admin_storage.stats.disk_free')), value: this.formatBytes(overview.disk.free) },
                            { label: @js(__('admin_storage.stats.public_size')), value: this.formatBytes(overview.sizes.public) },
                            { label: @js(__('admin_storage.stats.backups_size')), value: this.formatBytes(overview.sizes.backups) },
                            { label: @js(__('admin_storage.stats.logs_size')), value: this.formatBytes(overview.sizes.logs) },
                            { label: @js(__('admin_storage.stats.views_size')), value: this.formatBytes(overview.sizes.compiled_views) },
                            { label: @js(__('admin_storage.stats.cache_size')), value: this.formatBytes(overview.sizes.framework_cache) },
                            { label: @js(__('admin_storage.stats.db_size')), value: this.formatBytes(overview.sizes.database) },
                            { label: @js(__('admin_storage.stats.sessions')), value: overview.counts.sessions ?? 0 },
                            { label: @js(__('admin_storage.stats.cache_rows')), value: overview.counts.cache ?? 0 },
                            { label: @js(__('admin_storage.stats.jobs')), value: overview.counts.jobs ?? 0 },
                            { label: @js(__('admin_storage.stats.failed_jobs')), value: overview.counts.failed_jobs ?? 0 },
                            { label: @js(__('admin_storage.stats.activity_logs')), value: overview.counts.activity_logs ?? 0 },
                        ];
                    },
                    async previewCompression(scope = 'all', shopId = null, paths = []) {
                        const sp = await this.ensureSp();
                        const payload = { scope, shop_id: shopId, paths, ...this.compression };
                        const data = await sp.fetchJson(config.previewCompressionUrl, { method: 'POST', body: payload });
                        this.compression.preview = data.estimate;
                        this.compression.previewText = `${config.translations.previewReady} — ${data.total_paths} files, ${this.formatBytes(data.estimate.estimated_saved_bytes)}`;
                        this.compression.progress = 0;
                    },
                    async runCompression(scope = 'all', shopId = null, paths = []) {
                        const sp = await this.ensureSp();
                        let offset = 0;
                        this.compression.progress = 0;
                        while (true) {
                            const payload = { scope, shop_id: shopId, paths, offset, limit: 25, ...this.compression };
                            const data = await sp.fetchJson(config.runCompressionUrl, { method: 'POST', body: payload });
                            this.compression.progress = data.progress;
                            this.compression.previewText = `${config.translations.compressionDone} — ${data.results.optimized} / ${data.totalPaths}`;
                            if (!data.hasMore) {
                                sp.toast(config.translations.compressionDone, 'success');
                                break;
                            }
                            offset = data.nextOffset;
                        }
                    },
                    async previewCleanup(action) {
                        const sp = await this.ensureSp();
                        const data = await sp.fetchJson(config.previewCleanupUrl, { method: 'POST', body: { action } });
                        this.cleanup.previewAction = action;
                        this.cleanup.previewTitle = config.translations.actionLabels[action] || action;
                        this.cleanup.previewItems = data.items || [];
                    },
                    async runCleanup(action) {
                        const sp = await this.ensureSp();
                        const confirmed = await sp.confirm({
                            title: config.translations.actionLabels[action] || action,
                            message: @js(__('admin_storage.cleanup.confirm_cleanup')),
                            confirmText: @js(__('admin_storage.buttons.run_now')),
                            danger: true,
                        });
                        if (!confirmed) return;
                        const items = this.cleanup.previewAction === action ? this.cleanup.previewItems : [];
                        const body = { action };
                        if (items.length) {
                            body.paths = items.map(item => item.path).filter(Boolean);
                            body.filenames = items.map(item => item.filename).filter(Boolean);
                        }
                        await sp.fetchJson(config.runCleanupUrl, { method: 'POST', body });
                        sp.toast(config.translations.cleanupDone, 'success');
                    },
                    async recalculateShop(url) {
                        const sp = await this.ensureSp();
                        await sp.fetchJson(url, { method: 'POST', body: {} });
                        window.location.reload();
                    },
                    formatBytes(value) {
                        if (value === null || value === undefined) return '—';
                        const bytes = Number(value);
                        if (!bytes) return '0 B';
                        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                        let amount = bytes;
                        let index = 0;
                        while (amount >= 1024 && index < units.length - 1) {
                            amount /= 1024;
                            index++;
                        }
                        return `${index === 0 ? Math.round(amount) : amount.toFixed(1)} ${units[index]}`;
                    },
                    formatCleanupItem(item) {
                        return item.path || item.filename || item.product_name || JSON.stringify(item);
                    }
                }));
            });
        </script>
    @endpush
</x-app-layout>
