<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.edit_employee')" :subtitle="$employee->name">
            <a href="{{ route('shopowner.employees.payments', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.payments') }}</a>
            @if (Route::has('shopowner.attendance.timesheet'))
                <a href="{{ route('shopowner.attendance.timesheet', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.timesheet') }}</a>
            @endif
            <a href="{{ route('shopowner.employees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.back_to_employees') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('hr_owner.net_payable')" :value="'₪' . number_format((float) $payrollSummary['net_payable'], 2)" tone="indigo" />
                <x-ui.stat :label="__('hr_owner.present_days')" :value="$payrollSummary['present_days']" tone="green" />
                <x-ui.stat :label="__('hr_owner.worked_hours')" :value="number_format($payrollSummary['worked_minutes'] / 60, 2) . ' ' . __('hr_owner.hours_unit')" tone="blue" />
                <x-ui.stat :label="__('hr_owner.flagged_records')" :value="$payrollSummary['flagged'] ? 1 : 0" tone="amber" />
            </div>

            <form method="POST" action="{{ route('shopowner.employees.update', $employee) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('shopowner.employees.partials.form', ['mode' => 'edit'])
            </form>

            <x-ui.card :title="__('hr_owner.delete_employee')" :subtitle="__('hr_owner.delete_employee_help')">
                <form method="POST" action="{{ route('shopowner.employees.destroy', $employee) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" data-confirm="{{ __('hr_owner.delete_employee_confirm') }}" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        {{ __('hr_owner.delete_employee') }}
                    </button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
