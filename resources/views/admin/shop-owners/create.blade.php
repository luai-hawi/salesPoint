<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.create_shop')" :subtitle="__('admin.titles.shops')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            @include('admin.shop-owners.partials.form', [
                'shopOwner' => null,
                'action' => route('admin.shop-owners.store'),
                'method' => 'POST',
                'settings' => $settings,
                'currencyOptions' => $currencyOptions,
            ])
        </div>
    </div>
</x-app-layout>
