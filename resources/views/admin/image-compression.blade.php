<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin_storage.legacy_page_title')" :subtitle="__('admin_storage.legacy_page_subtitle')" />
    </x-slot>

    <div class="py-6" x-data="legacyCompression({
        mutateUrl: @js($legacyCompressionUrl ?? url('/compress-and-cleanup-images')),
        quickUrl: @js($legacyQuickUrl ?? url('/quick-compress-images')),
        translations: @js([
            'confirmCleanup' => __('admin_storage.cleanup.confirm_cleanup'),
            'compressedFiles' => __('admin_storage.legacy.compressed_files', ['count' => '__COUNT__']),
            'deletedFiles' => __('admin_storage.legacy.deleted_files', ['count' => '__COUNT__']),
        ]),
    })" x-init="reset()">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('admin_storage.legacy_page_title')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.buttons.compress') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900" x-text="compressed"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.buttons.delete') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900" x-text="deleted"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('admin_storage.fields.errors') }}</p>
                        <p class="mt-2 text-2xl font-bold text-red-600" x-text="errors.length"></p>
                    </div>
                </div>

                <div class="mt-6 h-3 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-3 rounded-full bg-indigo-600 transition-all" :style="`width: ${progress}%`"></div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="runFull()">
                        {{ __('admin_storage.buttons.start_compression') }}
                    </button>
                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="runQuick()">
                        {{ __('admin_storage.buttons.quick_compress') }}
                    </button>
                    <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" @click="cleanupOnly()">
                        {{ __('admin_storage.buttons.cleanup_only') }}
                    </button>
                    <a href="{{ route('admin.storage.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('admin_storage.buttons.back_to_manager') }}
                    </a>
                </div>

                <div class="mt-6 rounded-xl bg-gray-50 p-4">
                    <ul class="max-h-72 space-y-2 overflow-y-auto text-sm text-gray-700">
                        <template x-for="(line, index) in log" :key="index">
                            <li x-text="line"></li>
                        </template>
                    </ul>
                </div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('legacyCompression', (config) => ({
                    compressed: 0,
                    deleted: 0,
                    errors: [],
                    progress: 0,
                    log: [],
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
                    reset() {
                        this.compressed = 0;
                        this.deleted = 0;
                        this.errors = [];
                        this.progress = 0;
                        this.log = [];
                    },
                    async runFull() {
                        const sp = await this.ensureSp();
                        const confirmed = await sp.confirm({
                            title: @js(__('admin_storage.buttons.start_compression')),
                            message: config.translations.confirmCleanup,
                            confirmText: @js(__('admin_storage.buttons.start_compression')),
                            danger: false,
                        });
                        if (!confirmed) return;
                        this.reset();
                        let offset = 0;
                        while (true) {
                            const data = await sp.fetchJson(config.mutateUrl, {
                                method: 'POST',
                                body: { step: 'compress', offset, batch: 10, confirm: true }
                            });
                            this.compressed += data.results.optimized;
                            this.progress = data.progress;
                            this.log.push(config.translations.compressedFiles.replace('__COUNT__', data.results.optimized));
                            if (!data.hasMore) break;
                            offset = data.nextOffset;
                        }
                        await this.cleanupOnly();
                    },
                    async runQuick() {
                        const sp = await this.ensureSp();
                        const confirmed = await sp.confirm({
                            title: @js(__('admin_storage.buttons.quick_compress')),
                            message: config.translations.confirmCleanup,
                            confirmText: @js(__('admin_storage.buttons.quick_compress')),
                            danger: false,
                        });
                        if (!confirmed) return;
                        const data = await sp.fetchJson(config.quickUrl, { method: 'POST', body: {} });
                        this.compressed += data.compressed;
                        this.errors = this.errors.concat(data.errors || []);
                    },
                    async cleanupOnly() {
                        const sp = await this.ensureSp();
                        const confirmed = await sp.confirm({
                            title: @js(__('admin_storage.buttons.cleanup_only')),
                            message: config.translations.confirmCleanup,
                            confirmText: @js(__('admin_storage.buttons.cleanup_only')),
                            danger: true,
                        });
                        if (!confirmed) return;
                        const data = await sp.fetchJson(config.mutateUrl, {
                            method: 'POST',
                            body: { step: 'cleanup', confirm: true }
                        });
                        this.deleted += data.deleted;
                        this.log.push(config.translations.deletedFiles.replace('__COUNT__', data.deleted));
                    }
                }));
            });
        </script>
    @endpush
</x-app-layout>
