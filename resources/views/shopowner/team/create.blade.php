<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('team.form.create_title')" :subtitle="__('team.form.create_subtitle')">
            <a href="{{ route('shopowner.team.index') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('team.actions.back') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            @include('shopowner.team.partials.form', ['submitRoute' => route('shopowner.team.store'), 'method' => 'POST'])
        </div>
    </div>
</x-app-layout>
