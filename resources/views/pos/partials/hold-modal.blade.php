<div id="hold-bill-modal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-gray-900/60" data-close-hold-modal></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-2xl">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900">{{ __('pos.hold_bill') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('pos.optional') }}</p>
            </div>
            <div class="space-y-4 px-5 py-4">
                <div>
                    <label for="hold-bill-label" class="mb-2 block text-sm font-medium text-gray-700">
                        {{ __('pos.label') }}
                    </label>
                    <input type="text" id="hold-bill-label"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                        placeholder="{{ __('pos.label_placeholder') }}">
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4">
                <button type="button" data-close-hold-modal
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('pos.cancel') }}
                </button>
                <button type="button" id="confirm-hold-bill"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    {{ __('pos.hold') }}
                </button>
            </div>
        </div>
    </div>
</div>
