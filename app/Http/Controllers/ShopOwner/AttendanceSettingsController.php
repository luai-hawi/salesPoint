<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopOwner\Concerns\ResolvesHrOwner;
use App\Models\EmployeeDevice;
use App\Services\ActivityLogger;
use App\Support\HrSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttendanceSettingsController extends Controller
{
    use ResolvesHrOwner;

    public function edit()
    {
        $owner = HrSettings::ensurePortalKey($this->hrOwner());
        $settings = HrSettings::normalize($owner->attendance_settings);
        $locations = $owner->id
            ? \App\Models\AttendanceLocation::withoutGlobalScopes()->where('user_id', $owner->id)->orderByDesc('is_active')->orderBy('name')->get()
            : collect();

        return view('shopowner.attendance.settings', [
            'owner' => $owner,
            'settings' => $settings,
            'timezoneOptions' => HrSettings::timezoneOptions(),
            'scheduleTemplate' => HrSettings::scheduleTemplate(),
            'locations' => $locations,
            'portalUrl' => HrSettings::portalUrl($owner),
        ]);
    }

    public function update(Request $request)
    {
        $owner = $this->hrOwner();
        $validated = $request->validate([
            'timezone' => ['nullable', 'string', Rule::in(array_keys(HrSettings::timezoneOptions()))],
            'enabled' => ['nullable', 'boolean'],
            'biometric_required' => ['nullable', 'boolean'],
            'allow_remote_checkout' => ['nullable', 'boolean'],
            'allow_remote_checkin' => ['nullable', 'boolean'],
            'accuracy_tolerance_m' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_accuracy_m' => ['required', 'integer', 'min:1', 'max:1000'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'overtime_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'rounding_minutes' => ['required', Rule::in([0, 5, 10, 15])],
            'auto_close_after_hours' => ['required', 'integer', 'min:1', 'max:48'],
            'week_start' => ['required', Rule::in(range(0, 6))],
            'show_hours_to_staff' => ['nullable', 'boolean'],
            'show_pay_to_staff' => ['nullable', 'boolean'],
            'default_schedule' => ['nullable', 'array'],
        ]);

        $settings = HrSettings::normalize([
            'enabled' => $request->boolean('enabled'),
            'biometric_required' => $request->boolean('biometric_required'),
            'allow_remote_checkout' => $request->boolean('allow_remote_checkout', true),
            'allow_remote_checkin' => $request->boolean('allow_remote_checkin'),
            'accuracy_tolerance_m' => (int) $validated['accuracy_tolerance_m'],
            'max_accuracy_m' => (int) $validated['max_accuracy_m'],
            'grace_minutes' => (int) $validated['grace_minutes'],
            'overtime_after_minutes' => (int) $validated['overtime_after_minutes'],
            'overtime_multiplier' => (float) $validated['overtime_multiplier'],
            'rounding_minutes' => (int) $validated['rounding_minutes'],
            'auto_close_after_hours' => (int) $validated['auto_close_after_hours'],
            'week_start' => (int) $validated['week_start'],
            'show_hours_to_staff' => $request->boolean('show_hours_to_staff', true),
            'show_pay_to_staff' => $request->boolean('show_pay_to_staff'),
            'default_schedule' => HrSettings::normalizeSchedule($validated['default_schedule'] ?? []),
        ]);

        $owner->forceFill([
            'timezone' => $validated['timezone'] ?: null,
            'attendance_settings' => $settings,
        ])->save();

        ActivityLogger::record('attendance_settings.updated', \App\Models\User::class, $owner, [
            'enabled' => $settings['enabled'],
            'timezone' => $owner->timezone,
        ], label: $owner->name, ownerId: (int) $owner->id);

        return back()->with('success', __('hr_owner.attendance_settings_saved'));
    }

    public function regeneratePortalKey()
    {
        $owner = $this->hrOwner();
        $owner->forceFill(['staff_portal_key' => Str::random(32)])->save();

        EmployeeDevice::query()
            ->where('user_id', $owner->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        ActivityLogger::record('attendance_portal.regenerated', \App\Models\User::class, $owner, [], label: $owner->name, ownerId: (int) $owner->id);

        return back()->with('success', __('hr_owner.portal_key_regenerated'));
    }
}
