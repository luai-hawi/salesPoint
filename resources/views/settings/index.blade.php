@php
    $entriesLimit = $user->getEntryLimit();
    $entryUsage = $user->getEntryUsage();
    $remainingEntries = $entriesLimit ? max($entriesLimit - $entryUsage, 0) : null;
    $visibilityOptions = __('settings.visibility.options');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('settings.title')" :subtitle="__('settings.subtitle')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="{ tab: 'plan' }">
            <x-ui.flash />

            <div class="flex flex-wrap gap-2">
                @foreach (__('settings.tabs') as $tabKey => $tabLabel)
                    <button type="button" @click="tab = '{{ $tabKey }}'"
                        class="rounded-lg border px-4 py-2 text-sm font-semibold transition"
                        :class="tab === '{{ $tabKey }}'
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>

            <section x-show="tab === 'plan'" x-cloak class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.stat :label="__('settings.plan.current_usage')" :value="number_format($entryUsage)" :hint="__('settings.plan.entries')" tone="indigo">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </x-ui.stat>
                    <x-ui.stat :label="__('settings.plan.remaining')" :value="$remainingEntries === null ? __('settings.plan.unlimited') : number_format($remainingEntries)" :hint="__('settings.plan.entries')" tone="green">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M5 13l4 4L19 7" />
                        </svg>
                    </x-ui.stat>
                    <x-ui.stat :label="__('settings.plan.image_count')" :value="number_format($imageStats['count'])" :hint="__('settings.plan.images_hint')" tone="blue">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </x-ui.stat>
                    <x-ui.stat :label="__('settings.plan.image_limit')" :value="number_format($ownerAccount?->image_limit ?? $user->image_limit ?? 0)" :hint="__('settings.plan.limit_note')" tone="amber">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M12 8c-1.657 0-3 1.343-3 3v6h6v-6c0-1.657-1.343-3-3-3zm0 0V6m0 0a2 2 0 114 0v2M12 6a2 2 0 10-4 0v2" />
                        </svg>
                    </x-ui.stat>
                </div>

                <x-ui.card :title="__('settings.plan.title')" :subtitle="__('settings.plan.subtitle')">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('settings.plan.entries') }}</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">{{ __('settings.plan.current_usage') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ number_format($entryUsage) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">{{ __('settings.plan.remaining') }}</dt>
                                    <dd class="font-semibold text-gray-900">
                                        {{ $remainingEntries === null ? __('settings.plan.unlimited') : number_format($remainingEntries) }}
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">{{ __('settings.plan.image_size') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ \App\Services\Admin\ShopStorageService::humanBytes($imageStats['bytes']) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('settings.plan.images_title') }}</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">{{ __('settings.plan.image_count') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ number_format($imageStats['count']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">{{ __('settings.plan.image_limit') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ number_format($ownerAccount?->image_limit ?? $user->image_limit ?? 0) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">{{ __('settings.plan.image_missing') }}</dt>
                                    <dd class="font-semibold text-gray-900">{{ number_format($imageStats['missing']) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    @if ($user->role === 'admin')
                        <form action="{{ route('settings.update-image-limit') }}" method="POST" class="mt-6 grid gap-4 border-t border-gray-100 pt-6 md:grid-cols-[1fr_auto] md:items-end">
                            @csrf
                            <div>
                                <label for="image_limit" class="block text-sm font-medium text-gray-700">{{ __('settings.plan.image_limit') }}</label>
                                <input id="image_limit" type="number" name="image_limit" min="0" max="10000"
                                    value="{{ old('image_limit', $user->image_limit) }}"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                                {{ __('ui.save') }}
                            </button>
                        </form>
                    @endif
                </x-ui.card>
            </section>

            <section x-show="tab === 'products'" x-cloak>
                <x-ui.card :title="__('settings.products.title')" :subtitle="__('settings.products.subtitle')">
                    <form action="{{ route('settings.update-product') }}" method="POST" class="space-y-6">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="product_warning_period" class="block text-sm font-medium text-gray-700">{{ __('settings.products.warning_period') }}</label>
                                <input id="product_warning_period" type="number" name="product_warning_period" min="1" max="24"
                                    value="{{ old('product_warning_period', $user->product_warning_period ?? 4) }}"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">{{ __('settings.products.warning_hint') }}</p>
                            </div>
                            <div>
                                <label for="product_deactivation_period" class="block text-sm font-medium text-gray-700">{{ __('settings.products.deactivation_period') }}</label>
                                <input id="product_deactivation_period" type="number" name="product_deactivation_period" min="1" max="36"
                                    value="{{ old('product_deactivation_period', $user->product_deactivation_period ?? 6) }}"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">{{ __('settings.products.deactivation_hint') }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                                {{ __('settings.products.save') }}
                            </button>
                        </div>
                    </form>
                </x-ui.card>
            </section>

            <section x-show="tab === 'visibility'" x-cloak>
                <x-ui.card :title="__('settings.visibility.title')" :subtitle="__('settings.visibility.subtitle')">
                    <form action="{{ route('settings.update-visibility') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($visibilityKeys as $key)
                                @php($option = $visibilityOptions[$key] ?? ['label' => $key, 'hint' => null])
                                <label class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 p-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">{{ $option['label'] }}</p>
                                        @if (! empty($option['hint']))
                                            <p class="mt-1 text-xs text-gray-500">{{ $option['hint'] }}</p>
                                        @endif
                                    </div>
                                    <span class="relative mt-1 inline-flex h-6 w-11 flex-shrink-0 items-center">
                                        <input type="hidden" name="{{ $key }}" value="0">
                                        <input type="checkbox" name="{{ $key }}" value="1" class="peer sr-only"
                                            {{ $user->getVisibilitySetting($key) ? 'checked' : '' }}>
                                        <span class="absolute inset-0 rounded-full bg-gray-200 transition peer-checked:bg-indigo-600"></span>
                                        <span class="absolute start-[2px] h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                                {{ __('settings.visibility.save') }}
                            </button>
                        </div>
                    </form>
                </x-ui.card>
            </section>

            <section x-show="tab === 'team'" x-cloak>
                <x-ui.card :title="__('settings.team.title')" :subtitle="__('settings.team.subtitle')">
                    <p class="text-sm text-gray-600">{{ __('settings.team.description') }}</p>
                    <div class="mt-4">
                        @if ($user->isOwnerAccount())
                            <a href="{{ route('shopowner.team.index') }}"
                                class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
                                {{ __('settings.team.open') }}
                            </a>
                        @else
                            <x-ui.badge tone="gray">{{ __('settings.team.owners_only') }}</x-ui.badge>
                        @endif
                    </div>
                </x-ui.card>
            </section>

            <section x-show="tab === 'pos'" x-cloak>
                <x-ui.card :title="__('settings.pos.title')" :subtitle="__('settings.pos.subtitle')">
                    <p class="text-sm text-gray-600">{{ __('settings.pos.description') }}</p>
                    <div class="mt-4">
                        <a href="{{ route('dashboard') }}"
                            class="inline-flex rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('settings.pos.open') }}
                        </a>
                    </div>
                </x-ui.card>
            </section>

            <section x-show="tab === 'account'" x-cloak>
                <x-ui.card :title="__('settings.account.title')" :subtitle="__('settings.account.subtitle')">
                    <a href="{{ route('profile.edit') }}"
                        class="inline-flex rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('settings.account.open') }}
                    </a>
                </x-ui.card>
            </section>
        </div>
    </div>
</x-app-layout>
