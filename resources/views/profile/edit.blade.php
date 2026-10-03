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

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
    </div>
</x-app-layout>
