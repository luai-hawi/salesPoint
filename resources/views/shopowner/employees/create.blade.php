<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.add_employee')" :subtitle="__('hr_owner.employee_form_create_subtitle')">
            <a href="{{ route('shopowner.employees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.back_to_employees') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <form method="POST" action="{{ route('shopowner.employees.store') }}" class="space-y-6">
                @csrf
                @include('shopowner.employees.partials.form', ['mode' => 'create'])
            </form>
        </div>
    </div>
</x-app-layout>
