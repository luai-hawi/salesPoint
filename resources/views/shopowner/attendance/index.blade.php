<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.attendance_board')" :subtitle="__('hr_owner.attendance_board_subtitle')">
            <a href="{{ route('shopowner.attendance.settings') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.attendance_settings_title') }}</a>
            <a href="{{ route('shopowner.attendance.export', $filters) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.export_csv') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" class="grid gap-4 md:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.from') }}</label>
                        <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.to') }}</label>
                        <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.employee') }}</label>
                        <select name="employee_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">{{ __('hr_owner.all_employees') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) $filters['employee_id'] === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.status') }}</label>
                        <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">{{ __('hr_owner.all_statuses') }}</option>
                            @foreach (['open', 'closed', 'approved', 'needs_review', 'rejected', 'flagged', 'in_now'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ __('hr_owner.filter_statuses.' . $status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.filter') }}</button>
                        <a href="{{ route('shopowner.attendance.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.reset') }}</a>
                    </div>
                </form>
            </x-ui.card>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <x-ui.stat :label="__('hr_owner.counter_in_now')" :value="$counters['in_now']" tone="green" />
                <x-ui.stat :label="__('hr_owner.counter_late')" :value="$counters['late']" tone="amber" />
                <x-ui.stat :label="__('hr_owner.counter_absent')" :value="$counters['absent']" tone="red" />
                <x-ui.stat :label="__('hr_owner.counter_left')" :value="$counters['left']" tone="blue" />
                <x-ui.stat :label="__('hr_owner.counter_flagged')" :value="$counters['flagged']" tone="purple" />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <x-ui.card :title="__('hr_owner.manual_record_title')" :subtitle="__('hr_owner.manual_record_help')">
                    <form method="POST" action="{{ route('shopowner.attendance.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.employee') }}</label>
                            <select name="employee_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.check_in') }}</label>
                            <input type="datetime-local" name="check_in_local" value="{{ old('check_in_local', $focusDate . 'T09:00') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.check_out') }}</label>
                            <input type="datetime-local" name="check_out_local" value="{{ old('check_out_local', $focusDate . 'T17:00') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.status') }}</label>
                            <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach (['approved', 'needs_review', 'open', 'rejected'] as $status)
                                    <option value="{{ $status }}">{{ __('hr_owner.filter_statuses.' . $status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.reason') }}</label>
                            <textarea name="reason" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                        </div>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.save_record') }}</button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('hr_owner.day_map_title')" :subtitle="$focusDate" class="xl:col-span-2">
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
                    <div id="attendance-day-map" class="h-80 rounded-xl border border-gray-200" data-points='@json($mapPoints)'></div>
                    <div class="mt-4 space-y-2 text-sm text-gray-600">
                        @forelse ($mapPoints as $point)
                            <div>{{ $point['label'] }} — {{ $point['lat'] }}, {{ $point['lng'] }}</div>
                        @empty
                            <div>{{ __('hr_owner.no_map_points') }}</div>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card :title="__('hr_owner.staff_status_title')" :subtitle="__('hr_owner.staff_status_subtitle')" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.employee') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.status') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.check_in') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.check_out') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.worked_hours') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.distance') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.flags') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('hr_owner.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($board as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $row['employee']->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['employee']->job_title }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-ui.badge :tone="$row['status']['tone']">{{ $row['status']['label'] }}</x-ui.badge>
                                        @if ($row['status']['detail'])
                                            <div class="mt-1 text-xs text-gray-500">{{ $row['status']['detail'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['record']?->check_in_at ? \App\Support\ShopTime::local($row['record']->check_in_at, $owner)->format('H:i') : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['record']?->check_out_at ? \App\Support\ShopTime::local($row['record']->check_out_at, $owner)->format('H:i') : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ number_format($row['hours'], 2) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['distance'] !== null ? ($row['distance'] >= 1000 ? __('hr_owner.distance_kilometers', ['value' => number_format($row['distance'] / 1000, 1)]) : __('hr_owner.distance_meters', ['value' => number_format($row['distance'])])) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($row['status']['flagged'])
                                            <x-ui.badge tone="amber">{{ __('hr_owner.review_required') }}</x-ui.badge>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($row['record'] && Route::has('shopowner.attendance.timesheet'))
                                            <a href="{{ route('shopowner.attendance.timesheet', $row['employee']) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.timesheet') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('hr_owner.review_queue_title')" :subtitle="__('hr_owner.review_queue_subtitle')">
                @if ($reviewQueue->isEmpty())
                    <x-ui.empty :title="__('hr_owner.review_queue_empty')" />
                @else
                    <div class="space-y-4">
                        @foreach ($reviewQueue as $record)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $record->employee?->name }} — {{ $record->work_date->toDateString() }}</div>
                                        <div class="text-sm text-gray-600">
                                            {{ __('hr_owner.claimed_time') }}: {{ $record->check_out_at ? \App\Support\ShopTime::local($record->check_out_at, $owner)->format('Y-m-d H:i') : '—' }}
                                            • {{ __('hr_owner.recorded_time') }}: {{ $record->check_out_recorded_at ? \App\Support\ShopTime::local($record->check_out_recorded_at, $owner)->format('Y-m-d H:i') : '—' }}
                                            • {{ __('hr_owner.distance') }}: {{ $record->check_out_distance_m ? ($record->check_out_distance_m >= 1000 ? __('hr_owner.distance_kilometers', ['value' => number_format($record->check_out_distance_m / 1000, 1)]) : __('hr_owner.distance_meters', ['value' => number_format($record->check_out_distance_m)])) : '—' }}
                                        </div>
                                        @if ($record->reason)
                                            <div class="mt-1 text-sm text-gray-600">{{ __('hr_owner.reason') }}: {{ $record->reason }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                                    <form method="POST" action="{{ route('shopowner.attendance.review', $record) }}" class="space-y-2 rounded-lg border border-white/70 bg-white p-3">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <label class="block text-sm font-medium text-gray-700">{{ __('hr_owner.review_note') }}</label>
                                        <textarea name="review_note" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                                        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">{{ __('hr_owner.approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('shopowner.attendance.review', $record) }}" class="space-y-2 rounded-lg border border-white/70 bg-white p-3">
                                        @csrf
                                        <input type="hidden" name="decision" value="edit">
                                        <div class="grid gap-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">{{ __('hr_owner.check_in') }}</label>
                                                <input type="datetime-local" name="check_in_local" value="{{ $record->check_in_at ? \App\Support\ShopTime::local($record->check_in_at, $owner)->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">{{ __('hr_owner.check_out') }}</label>
                                                <input type="datetime-local" name="check_out_local" value="{{ $record->check_out_at ? \App\Support\ShopTime::local($record->check_out_at, $owner)->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            </div>
                                        </div>
                                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.edit_time') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('shopowner.attendance.review', $record) }}" class="space-y-2 rounded-lg border border-white/70 bg-white p-3">
                                        @csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <label class="block text-sm font-medium text-gray-700">{{ __('hr_owner.review_note') }}</label>
                                        <textarea name="review_note" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required></textarea>
                                        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('hr_owner.reject') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('hr_owner.records_table_title')" :subtitle="__('hr_owner.records_table_subtitle')" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.employee') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.source') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.status') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.check_in') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.check_out') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.minutes') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_owner.notes') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('hr_owner.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($records as $record)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->work_date->toDateString() }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->employee?->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ __('hr_owner.sources.' . $record->source) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ __('hr_owner.filter_statuses.' . $record->status) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->check_in_at ? \App\Support\ShopTime::local($record->check_in_at, $owner)->format('H:i') : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->check_out_at ? \App\Support\ShopTime::local($record->check_out_at, $owner)->format('H:i') : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->minutes_worked ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $record->reason ?: $record->review_note ?: '—' }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <details class="inline-block text-start">
                                            <summary class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.edit') }}</summary>
                                            <div class="mt-3 w-80 rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
                                                <form method="POST" action="{{ route('shopowner.attendance.update', $record) }}" class="space-y-3">
                                                    @csrf
                                                    @method('PUT')
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.check_in') }}</label>
                                                        <input type="datetime-local" name="check_in_local" value="{{ $record->check_in_at ? \App\Support\ShopTime::local($record->check_in_at, $owner)->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.check_out') }}</label>
                                                        <input type="datetime-local" name="check_out_local" value="{{ $record->check_out_at ? \App\Support\ShopTime::local($record->check_out_at, $owner)->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.status') }}</label>
                                                        <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                            @foreach (['open', 'closed', 'approved', 'needs_review', 'rejected'] as $status)
                                                                <option value="{{ $status }}" @selected($record->status === $status)>{{ __('hr_owner.filter_statuses.' . $status) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.reason') }}</label>
                                                        <textarea name="reason" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ $record->reason }}</textarea>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.save_record') }}</button>
                                                        <button formaction="{{ route('shopowner.attendance.destroy', $record) }}" formmethod="POST" name="_method" value="DELETE" class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">{{ __('hr_owner.delete') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 px-4 py-4">
                    {{ $records->links('vendor.pagination.custom-light') }}
                </div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ \App\Support\Assets::versioned('js/hr-attendance-board.js') }}"></script>
    @endpush
</x-app-layout>
