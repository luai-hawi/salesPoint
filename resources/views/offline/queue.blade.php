<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('sync.offline_queue_title')" :subtitle="__('sync.offline_queue_subtitle')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('sync.offline_queue_title')" :subtitle="__('sync.offline_queue_subtitle')">
                <div id="offline-queue-page" data-mode="queue" class="space-y-4"></div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
