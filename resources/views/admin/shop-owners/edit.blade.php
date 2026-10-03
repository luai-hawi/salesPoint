<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.edit_shop')" :subtitle="$shopOwner->name" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            @include('admin.shop-owners.partials.form', [
                'shopOwner' => $shopOwner,
                'action' => route('admin.shop-owners.update', $shopOwner),
                'method' => 'PUT',
                'settings' => $settings,
                'currencyOptions' => $currencyOptions,
            ])
        </div>
    </div>
</x-app-layout>
