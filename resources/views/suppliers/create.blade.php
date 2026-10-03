<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('payables.titles.create_supplier')" :subtitle="__('payables.subtitles.create_supplier')">
            <a href="{{ route('suppliers.index') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('payables.actions.back_to_suppliers') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('payables.sections.supplier_details')">
                <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="name" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.name') }}
                            </label>
                            <input id="name" name="name" type="text" required value="{{ old('name') }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.phone') }}
                            </label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone') }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.email') }}
                            </label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.address') }}
                            </label>
                            <textarea id="address" name="address" rows="3"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">{{ old('address') }}</textarea>
                        </div>

                        <div>
                            <label for="initial_balance" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.initial_balance') }}
                            </label>
                            <input id="initial_balance" name="initial_balance" type="number" step="0.01"
                                value="{{ old('initial_balance', 0) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">{{ __('payables.fields.opening_balance_hint') }}</p>
                        </div>

                        <div class="md:col-span-1">
                            <label for="notes" class="mb-1 block text-sm font-medium text-gray-700">
                                {{ __('payables.fields.notes') }}
                            </label>
                            <textarea id="notes" name="notes" rows="4"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a href="{{ route('suppliers.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('payables.actions.cancel') }}
                        </a>
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ __('payables.actions.create_supplier') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
