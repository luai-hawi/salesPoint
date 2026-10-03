<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.create_employee')" :subtitle="__('admin.titles.employees')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            @include('admin.employees.partials.form', ['employee' => null, 'action' => route('admin.employees.store'), 'method' => 'POST', 'shopOwners' => $shopOwners])
        </div>
    </div>
</x-app-layout>
