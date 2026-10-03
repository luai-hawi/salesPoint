    <x-app-layout>
        <x-slot name="header">
            <x-ui.page-header :title="__('profile.title')" :subtitle="__('profile.subtitle')" />
        </x-slot>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <x-ui.flash />

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                @if (in_array(auth()->user()->role, ['shop_owner', 'restaurant', 'merchant'], true))
                    <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm sm:p-8">
                        <div class="max-w-xl">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('finance.common.backup_title') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('finance.common.backup_hint') }}</p>
                            <a href="{{ route('dashboard.backup-data') }}"
                                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                {{ __('finance.common.backup') }}
                            </a>
                        </div>
                    </div>
                @endif

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
    </div>
</x-app-layout>
