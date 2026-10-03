<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('sync.offline_page_title')" :subtitle="__('sync.offline_page_subtitle')">
            <a href="{{ route('offline.queue') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                {{ __('sync.offline_page_action') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('sync.offline_page_title')" :subtitle="__('sync.offline_page_intro')">
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">{{ __('sync.offline_page_subtitle') }}</p>

                    <div id="offline-queue-page" data-mode="overview" class="space-y-4"></div>

                    <button type="button" onclick="window.location.reload()"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                        {{ __('sync.offline_page_retry') }}
                    </button>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
