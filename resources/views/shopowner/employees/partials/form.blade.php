@php
    $isEdit = $mode === 'edit';
    $scheduleMode = old('schedule_mode', $employee->schedule ? 'custom' : 'default');
    $scheduleValues = old('schedule', $employee->schedule ?? $scheduleTemplate);
@endphp

<x-ui.card :title="__('hr_owner.basic_info_section')" :subtitle="__('hr_owner.basic_info_help')">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.employee_name') }}</label>
            <input type="text" name="name" value="{{ old('name', $employee->name) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.job_title') }}</label>
            <input type="text" name="job_title" value="{{ old('job_title', $employee->job_title) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
            @error('job_title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.phone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $employee->phone) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.hire_date') }}</label>
            <input type="date" name="hire_date" value="{{ old('hire_date', optional($employee->hire_date)->toDateString()) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @error('hire_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.notes') }}</label>
            <textarea name="staff_notes" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('staff_notes', $employee->staff_notes) }}</textarea>
            @error('staff_notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</x-ui.card>

<x-ui.card :title="__('hr_owner.pay_section')" :subtitle="__('hr_owner.pay_section_help')">
    <div class="grid gap-4 md:grid-cols-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.salary_type') }}</label>
            <select name="salary_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @foreach (['monthly', 'hourly', 'daily'] as $salaryType)
                    <option value="{{ $salaryType }}" @selected(old('salary_type', $employee->salary_type) === $salaryType)>{{ __('hr_owner.salary_types.' . $salaryType) }}</option>
                @endforeach
            </select>
            @error('salary_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.monthly_salary') }}</label>
            <input type="number" step="0.01" min="0" max="99999999.99" name="monthly_salary" value="{{ old('monthly_salary', $employee->monthly_salary) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @error('monthly_salary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.hourly_rate') }}</label>
            <input type="number" step="0.01" min="0" max="9999999999.99" name="hourly_rate" value="{{ old('hourly_rate', $employee->hourly_rate) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @error('hourly_rate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.daily_rate') }}</label>
            <input type="number" step="0.01" min="0" max="9999999999.99" name="daily_rate" value="{{ old('daily_rate', $employee->daily_rate) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            @error('daily_rate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</x-ui.card>

<x-ui.card :title="__('hr_owner.schedule_section')" :subtitle="__('hr_owner.schedule_section_help')">
    <div class="space-y-4">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="radio" name="schedule_mode" value="default" @checked($scheduleMode === 'default')>
            {{ __('hr_owner.use_shop_default_schedule') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="radio" name="schedule_mode" value="custom" @checked($scheduleMode === 'custom')>
            {{ __('hr_owner.use_custom_schedule') }}
        </label>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ __('hr_owner.day') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('hr_owner.start_time') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('hr_owner.end_time') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('hr_owner.day_off') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach ($scheduleTemplate as $dayIndex => $defaultDay)
                        @php
                            $row = $scheduleValues[$dayIndex] ?? $defaultDay;
                        @endphp
                        <tr>
                            <td class="px-3 py-2">{{ __('hr_owner.weekdays.' . $dayIndex) }}</td>
                            <td class="px-3 py-2"><input type="time" name="schedule[{{ $dayIndex }}][start]" value="{{ $row['start'] ?? $defaultDay['start'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></td>
                            <td class="px-3 py-2"><input type="time" name="schedule[{{ $dayIndex }}][end]" value="{{ $row['end'] ?? $defaultDay['end'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></td>
                            <td class="px-3 py-2">
                                <label class="inline-flex items-center gap-2">
                                    <input type="hidden" name="schedule[{{ $dayIndex }}][off]" value="0">
                                    <input type="checkbox" name="schedule[{{ $dayIndex }}][off]" value="1" @checked((bool) ($row['off'] ?? false))>
                                    <span>{{ __('hr_owner.off') }}</span>
                                </label>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-ui.card>

<x-ui.card :title="__('hr_owner.portal_access')" :subtitle="__('hr_owner.portal_access_help')">
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                <input type="hidden" name="portal_enabled" value="0">
                <input type="checkbox" name="portal_enabled" value="1" @checked(old('portal_enabled', $employee->portal_enabled))>
                <span>{{ __('hr_owner.portal_enabled') }}</span>
            </label>
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active ?? true))>
                <span>{{ __('hr_owner.employee_active') }}</span>
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.portal_username') }}</label>
                <input type="text" name="username" value="{{ old('username', $employee->username) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="ahmad.saleh">
                <p class="mt-1 text-xs text-gray-500">{{ __('hr_owner.username_format_help') }}</p>
                @error('username')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.portal_password') }}</label>
                <div class="flex gap-2">
                    <input id="portal-password" type="text" name="password" value="" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" autocomplete="new-password">
                    <button type="button" id="generate-password" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.generate') }}</button>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('hr_owner.password_show_once_help') }}</p>
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.biometric_rule') }}</label>
                <select name="biometric_required" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="" @selected(old('biometric_required', $employee->biometric_required) === null)>{{ __('hr_owner.biometric_default') }}</option>
                    <option value="1" @selected((string) old('biometric_required', $employee->biometric_required) === '1')>{{ __('hr_owner.yes') }}</option>
                    <option value="0" @selected((string) old('biometric_required', $employee->biometric_required) === '0')>{{ __('hr_owner.no') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.portal_link') }}</label>
                <div class="flex gap-2">
                    <input id="employee-portal-link" type="text" readonly value="{{ $portalUrl }}" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                    <button type="button" data-copy-target="employee-portal-link" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.copy_link') }}</button>
                    <a href="https://wa.me/?text={{ rawurlencode($portalUrl) }}" target="_blank" rel="noopener" class="rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 hover:bg-green-100">{{ __('hr_owner.share_whatsapp') }}</a>
                </div>
            </div>
        </div>

        @if ($isEdit)
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('hr_owner.devices') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('hr_owner.last_used') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('hr_owner.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($employee->devices as $device)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $device->label ?: __('hr_owner.unnamed_device') }}</div>
                                        <div class="text-xs text-gray-500">{{ $device->user_agent }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ optional($device->last_used_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                    <td class="px-3 py-2 text-end">
                                        @if ($device->revoked_at)
                                            <x-ui.badge tone="gray">{{ __('hr_owner.revoked') }}</x-ui.badge>
                                        @else
                                            <form method="POST" action="{{ route('shopowner.employees.devices.destroy', [$employee, $device]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">{{ __('hr_owner.revoke') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-4 text-center text-sm text-gray-500">{{ __('hr_owner.no_devices') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('hr_owner.credentials') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('hr_owner.last_used') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('hr_owner.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($employee->credentials as $credential)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $credential->label ?: __('hr_owner.biometric_credential') }}</div>
                                        <div class="text-xs text-gray-500">{{ is_array($credential->transports) ? implode(', ', $credential->transports) : '' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ optional($credential->last_used_at)->format('Y-m-d H:i') ?: '—' }}</td>
                                    <td class="px-3 py-2 text-end">
                                        <form method="POST" action="{{ route('shopowner.employees.credentials.destroy', [$employee, $credential]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">{{ __('hr_owner.remove') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-4 text-center text-sm text-gray-500">{{ __('hr_owner.no_credentials') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-ui.card>

<div class="flex flex-wrap justify-end gap-3">
    <a href="{{ route('shopowner.employees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.cancel') }}</a>
    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ $isEdit ? __('hr_owner.save_changes') : __('hr_owner.create_employee') }}</button>
</div>

@push('scripts')
    <script>
        document.getElementById('generate-password')?.addEventListener('click', () => {
            const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
            const password = Array.from({length: 14}, () => charset[Math.floor(Math.random() * charset.length)]).join('');
            document.getElementById('portal-password').value = password;
            SP.toast(@json(__('hr_owner.password_generated')), 'success');
        });

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-target]');
            if (!button) return;
            const input = document.getElementById(button.dataset.copyTarget);
            if (!input) return;
            await navigator.clipboard.writeText(input.value);
            SP.toast(@json(__('hr_owner.link_copied')), 'success');
        });
    </script>
@endpush
