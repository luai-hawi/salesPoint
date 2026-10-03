<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.employees_title')" :subtitle="__('hr_owner.employees_subtitle')">
            <a href="{{ route('shopowner.attendance.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('hr_owner.attendance_board') }}
            </a>
            <a href="{{ route('shopowner.payroll.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('hr_owner.payroll_title') }}
            </a>
            <a href="{{ route('shopowner.employees.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ __('hr_owner.add_employee') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('hr_owner.portal_link')" :subtitle="__('hr_owner.portal_link_help')">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input id="portal-link-field" type="text" readonly value="{{ $portalUrl }}" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-copy-target="portal-link-field" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.copy_link') }}</button>
                        <a href="https://wa.me/?text={{ rawurlencode($portalUrl) }}" target="_blank" rel="noopener" class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-100">{{ __('hr_owner.share_whatsapp') }}</a>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <form method="GET" class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.search') }}</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('hr_owner.employee_search_placeholder') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.status') }}</label>
                        <select name="active" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">{{ __('hr_owner.all_statuses') }}</option>
                            <option value="1" @selected(($filters['active'] ?? null) === '1')>{{ __('hr_owner.active') }}</option>
                            <option value="0" @selected(($filters['active'] ?? null) === '0')>{{ __('hr_owner.inactive') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.filter') }}</button>
                        <a href="{{ route('shopowner.employees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.reset') }}</a>
                    </div>
                </form>
            </x-ui.card>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('hr_owner.total_employees')" :value="$employees->total()">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </x-ui.stat>
                <x-ui.stat :label="__('hr_owner.active_staff')" :value="$employees->getCollection()->where('is_active', true)->count()" tone="green">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </x-ui.stat>
                <x-ui.stat :label="__('hr_owner.portal_enabled_count')" :value="$employees->getCollection()->where('portal_enabled', true)->count()" tone="blue">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 11c0 .828-.895 1.5-2 1.5S8 11.828 8 11s.895-1.5 2-1.5 2 .672 2 1.5zm6 0c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.255-1.042L3 18l1.348-3.146A6.69 6.69 0 014 11c0-3.866 3.582-7 8-7s8 3.134 8 7z" /></svg>
                </x-ui.stat>
                <x-ui.stat :label="__('hr_owner.filtered_results')" :value="$employees->count()" tone="amber">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4h18M6 12h12M10 20h4" /></svg>
                </x-ui.stat>
            </div>

            <x-ui.card :title="__('hr_owner.staff_directory')" :subtitle="__('hr_owner.staff_directory_subtitle')" :padding="false">
                @if ($employees->count() === 0)
                    <div class="p-6">
                        <x-ui.empty :title="__('hr_owner.no_employees_title')" :text="__('hr_owner.no_employees_text')">
                            <a href="{{ route('shopowner.employees.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.add_employee') }}</a>
                        </x-ui.empty>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.employee') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.attendance_today') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.month_hours') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.salary_type') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.portal_access') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('hr_owner.status') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('hr_owner.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($employees as $employee)
                                    <tr>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-gray-900">{{ $employee->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $employee->job_title }}</div>
                                            @if ($employee->phone)
                                                <div class="text-xs text-gray-500">{{ $employee->phone }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <x-ui.badge :tone="$employee->today_status['tone']">{{ $employee->today_status['label'] }}</x-ui.badge>
                                            @if ($employee->today_status['detail'])
                                                <div class="mt-1 text-xs text-gray-500">{{ $employee->today_status['detail'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top text-gray-700">{{ number_format((float) $employee->hours_this_month, 2) }} {{ __('hr_owner.hours_unit') }}</td>
                                        <td class="px-4 py-3 align-top text-gray-700">{{ __('hr_owner.salary_types.' . $employee->salary_type) }}</td>
                                        <td class="px-4 py-3 align-top">
                                            <x-ui.badge :tone="$employee->portal_enabled ? 'green' : 'gray'">
                                                {{ $employee->portal_enabled ? __('hr_owner.enabled') : __('hr_owner.disabled') }}
                                            </x-ui.badge>
                                            @if ($employee->username)
                                                <div class="mt-1 text-xs text-gray-500">{{ '@' . $employee->username }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <x-ui.badge :tone="$employee->is_active ? 'green' : 'red'">
                                                {{ $employee->is_active ? __('hr_owner.active') : __('hr_owner.inactive') }}
                                            </x-ui.badge>
                                        </td>
                                        <td class="px-4 py-3 align-top text-end">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="{{ route('shopowner.employees.edit', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.edit') }}</a>
                                                <a href="{{ route('shopowner.employees.payments', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.payments') }}</a>
                                                @if (Route::has('shopowner.attendance.timesheet'))
                                                    <a href="{{ route('shopowner.attendance.timesheet', $employee) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.timesheet') }}</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-4 py-4">
                        {{ $employees->links('vendor.pagination.custom-light') }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-copy-target]');
                if (!button) return;
                const input = document.getElementById(button.dataset.copyTarget);
                if (!input) return;
                try {
                    await navigator.clipboard.writeText(input.value);
                    SP.toast(@json(__('hr_owner.link_copied')), 'success');
                } catch (error) {
                    input.select();
                    document.execCommand('copy');
                    SP.toast(@json(__('hr_owner.link_copied')), 'success');
                }
            });
        </script>
    @endpush
</x-app-layout>
