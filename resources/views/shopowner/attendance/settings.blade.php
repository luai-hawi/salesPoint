<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('hr_owner.attendance_settings_title')" :subtitle="__('hr_owner.attendance_settings_subtitle')">
            <a href="{{ route('shopowner.attendance.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.attendance_board') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @if ($settings['enabled'] && $locations->where('is_active', true)->isEmpty())
                <x-ui.card class="border-amber-300 bg-amber-50" :title="__('hr_owner.location_warning_title')" :subtitle="__('hr_owner.location_warning_text')" />
            @endif

            <x-ui.card :title="__('hr_owner.portal_link')" :subtitle="__('hr_owner.portal_settings_help')">
                <div class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input id="staff-portal-link" type="text" readonly value="{{ $portalUrl }}" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-copy-target="staff-portal-link" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.copy_link') }}</button>
                            <a href="https://wa.me/?text={{ rawurlencode($portalUrl) }}" target="_blank" rel="noopener" class="rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-100">{{ __('hr_owner.share_whatsapp') }}</a>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('shopowner.attendance.settings.regenerate-portal') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">{{ __('hr_owner.regenerate_link') }}</button>
                    </form>
                    <p class="text-xs text-gray-500">{{ __('hr_owner.qr_optional_skipped') }}</p>
                </div>
            </x-ui.card>

            <form method="POST" action="{{ route('shopowner.attendance.settings.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.card :title="__('hr_owner.attendance_rules')" :subtitle="__('hr_owner.attendance_rules_help')">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                            <span>{{ __('hr_owner.attendance_enabled') }}</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                            <input type="hidden" name="biometric_required" value="0">
                            <input type="checkbox" name="biometric_required" value="1" @checked(old('biometric_required', $settings['biometric_required']))>
                            <span>{{ __('hr_owner.default_biometric_required') }}</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                            <input type="hidden" name="allow_remote_checkout" value="0">
                            <input type="checkbox" name="allow_remote_checkout" value="1" @checked(old('allow_remote_checkout', $settings['allow_remote_checkout']))>
                            <span>{{ __('hr_owner.allow_remote_checkout') }}</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                            <input type="hidden" name="allow_remote_checkin" value="0">
                            <input type="checkbox" name="allow_remote_checkin" value="1" @checked(old('allow_remote_checkin', $settings['allow_remote_checkin']))>
                            <span>{{ __('hr_owner.allow_remote_checkin') }}</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                            <input type="hidden" name="show_hours_to_staff" value="0">
                            <input type="checkbox" name="show_hours_to_staff" value="1" @checked(old('show_hours_to_staff', $settings['show_hours_to_staff']))>
                            <span>{{ __('hr_owner.show_hours_to_staff') }}</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700">
                            <input type="hidden" name="show_pay_to_staff" value="0">
                            <input type="checkbox" name="show_pay_to_staff" value="1" @checked(old('show_pay_to_staff', $settings['show_pay_to_staff']))>
                            <span>{{ __('hr_owner.show_pay_to_staff') }}</span>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.timezone') }}</label>
                            <select name="timezone" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="">{{ __('hr_owner.timezone_default') }}</option>
                                @foreach ($timezoneOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('timezone', $owner->timezone) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @foreach ([
                            'accuracy_tolerance_m' => 'accuracy_tolerance',
                            'max_accuracy_m' => 'max_accuracy',
                            'grace_minutes' => 'grace_minutes',
                            'overtime_after_minutes' => 'overtime_after_minutes',
                            'overtime_multiplier' => 'overtime_multiplier',
                            'rounding_minutes' => 'rounding_minutes',
                            'auto_close_after_hours' => 'auto_close_after_hours',
                            'week_start' => 'week_start',
                        ] as $field => $key)
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.' . $key) }}</label>
                                @if ($field === 'rounding_minutes')
                                    <select name="{{ $field }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @foreach ([0, 5, 10, 15] as $option)
                                            <option value="{{ $option }}" @selected((int) old($field, $settings[$field]) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field === 'week_start')
                                    <select name="{{ $field }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @foreach ($scheduleTemplate as $dayIndex => $day)
                                            <option value="{{ $dayIndex }}" @selected((int) old($field, $settings[$field]) === $dayIndex)>{{ __('hr_owner.weekdays.' . $dayIndex) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="number" step="{{ $field === 'overtime_multiplier' ? '0.01' : '1' }}" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('hr_owner.default_schedule_title')" :subtitle="__('hr_owner.default_schedule_help')">
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
                                    @php $row = old("default_schedule.$dayIndex", $settings['default_schedule'][$dayIndex] ?? $defaultDay); @endphp
                                    <tr>
                                        <td class="px-3 py-2">{{ __('hr_owner.weekdays.' . $dayIndex) }}</td>
                                        <td class="px-3 py-2"><input type="time" name="default_schedule[{{ $dayIndex }}][start]" value="{{ $row['start'] ?? $defaultDay['start'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></td>
                                        <td class="px-3 py-2"><input type="time" name="default_schedule[{{ $dayIndex }}][end]" value="{{ $row['end'] ?? $defaultDay['end'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></td>
                                        <td class="px-3 py-2">
                                            <input type="hidden" name="default_schedule[{{ $dayIndex }}][off]" value="0">
                                            <input type="checkbox" name="default_schedule[{{ $dayIndex }}][off]" value="1" @checked((bool) ($row['off'] ?? false))>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.save_settings') }}</button>
                    </div>
                </x-ui.card>
            </form>

            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
            <x-ui.card :title="__('hr_owner.allowed_places')" :subtitle="__('hr_owner.allowed_places_help')">
                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="space-y-4">
                        <div id="hr-settings-map" class="h-80 rounded-xl border border-gray-200" data-lat="31.5017" data-lng="34.4668"></div>
                        <p class="text-xs text-gray-500">{{ __('hr_owner.map_fallback_help') }}</p>
                        <form method="POST" action="{{ route('shopowner.attendance.locations.store') }}" class="grid gap-4 rounded-xl border border-gray-200 p-4">
                            @csrf
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.location_name') }}</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.latitude') }}</label>
                                    <input id="location-latitude" type="number" step="0.0000001" name="latitude" value="{{ old('latitude') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.longitude') }}</label>
                                    <input id="location-longitude" type="number" step="0.0000001" name="longitude" value="{{ old('longitude') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.radius_m') }}</label>
                                <input id="location-radius" type="range" min="20" max="1000" name="radius_m" value="{{ old('radius_m', 100) }}" class="w-full">
                                <div class="text-xs text-gray-500"><span id="location-radius-value">{{ old('radius_m', 100) }}</span> {{ __('hr_owner.meters_short') }}</div>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" checked>
                                <span>{{ __('hr_owner.active_location') }}</span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="use-current-location" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.use_my_location') }}</button>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('hr_owner.save_location') }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="space-y-4">
                        @forelse ($locations as $location)
                            <form method="POST" action="{{ route('shopowner.attendance.locations.update', $location) }}" class="rounded-xl border border-gray-200 p-4">
                                @csrf
                                @method('PUT')
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.location_name') }}</label>
                                        <input type="text" name="name" value="{{ $location->name }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.latitude') }}</label>
                                        <input type="number" step="0.0000001" name="latitude" value="{{ $location->latitude }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.longitude') }}</label>
                                        <input type="number" step="0.0000001" name="longitude" value="{{ $location->longitude }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('hr_owner.radius_m') }}</label>
                                        <input type="number" min="20" max="1000" name="radius_m" value="{{ $location->radius_m }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    </div>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($location->is_active)>
                                        <span>{{ __('hr_owner.active_location') }}</span>
                                    </label>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('hr_owner.save_location') }}</button>
                                    <button formaction="{{ route('shopowner.attendance.locations.destroy', $location) }}" formmethod="POST" name="_method" value="DELETE" class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">{{ __('hr_owner.delete') }}</button>
                                </div>
                            </form>
                        @empty
                            <x-ui.empty :title="__('hr_owner.no_locations_title')" :text="__('hr_owner.no_locations_text')" />
                        @endforelse
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            window.hrSettingsTranslations = {
                copySuccess: @json(__('hr_owner.link_copied')),
            };
        </script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ \App\Support\Assets::versioned('js/hr-settings.js') }}"></script>
    @endpush
</x-app-layout>
