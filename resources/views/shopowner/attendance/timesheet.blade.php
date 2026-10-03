<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.timesheet')" :subtitle="$employee->name">
            <a href="{{ route('shopowner.attendance.index', ['employee_id' => $employee->id]) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.attendance_board') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.period') }}</label>
                        <input type="month" name="period" value="{{ $period }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.filter') }}</button>
                </form>
            </x-ui.card>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat :label="__('hr_owner.present_days')" :value="$summary['present_days']" tone="green" />
                <x-ui.stat :label="__('hr_owner.absent_days')" :value="$summary['absent_days']" tone="red" />
                <x-ui.stat :label="__('hr_owner.worked_hours')" :value="number_format($summary['worked_minutes'] / 60, 2) . ' ' . __('hr_owner.hours_unit')" tone="blue" />
                <x-ui.stat :label="__('hr_owner.overtime_hours')" :value="number_format($summary['overtime_minutes'] / 60, 2) . ' ' . __('hr_owner.hours_unit')" tone="amber" />
            </div>
            @if ($summary['pending_approval_minutes'] > 0 || $summary['pending_approval_days'] > 0)
                <x-ui.card class="border-amber-300 bg-amber-50" :title="__('hr_owner.pending_approval_title')" :subtitle="__('hr_owner.pending_approval_payment_hint', ['amount' => number_format((float) $summary['pending_approval_amount'], 2)])">
                    <p class="text-sm text-amber-800">{{ __('hr_owner.pending_approval_hours_label') }}: {{ number_format($summary['pending_approval_minutes'] / 60, 2) }}</p>
                </x-ui.card>
            @endif

            <x-ui.card :title="__('hr_owner.monthly_timesheet')" :subtitle="$period" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.check_in') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.check_out') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.worked_hours') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.late_minutes_short') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.overtime_hours') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($summary['days'] as $day)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $day['date'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $day['check_in_at'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $day['check_out_at'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ number_format($day['worked_minutes'] / 60, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $day['late_minutes'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ number_format($day['overtime_minutes'] / 60, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <x-ui.badge :tone="$day['status'] === 'absent' ? 'red' : (in_array($day['status'], ['flagged', 'pending_approval'], true) ? 'amber' : 'green')">
                                            {{ __('hr_owner.day_statuses.' . $day['status']) }}
                                        </x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
