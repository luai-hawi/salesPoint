<div id="held-bills-drawer" class="pointer-events-none fixed inset-y-0 end-0 z-50 hidden w-full max-w-lg">
    <div class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity" id="held-bills-backdrop"></div>
    <div
        class="relative ms-auto flex h-full w-full max-w-lg translate-x-full flex-col border-s border-gray-200 bg-white shadow-2xl transition-transform">
        <div class="border-b border-gray-100 px-5 py-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('pos.drawer_title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('pos.drawer_subtitle') }}</p>
                </div>
                <button type="button" id="close-held-bills-drawer"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('pos.cancel') }}
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4">
            <div id="held-bills-empty" class="hidden">
                <x-ui.empty :title="__('pos.held_empty')" />
            </div>
            <div id="held-bills-list" class="space-y-3"></div>
        </div>
    </div>
</div>
