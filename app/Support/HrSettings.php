<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class HrSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'biometric_required' => false,
            'allow_remote_checkout' => true,
            'allow_remote_checkin' => false,
            'accuracy_tolerance_m' => 50,
            'max_accuracy_m' => 150,
            'grace_minutes' => 10,
            'overtime_after_minutes' => 480,
            'overtime_multiplier' => 1.25,
            'rounding_minutes' => 0,
            'auto_close_after_hours' => 16,
            'default_schedule' => [],
            'week_start' => 0,
            'show_hours_to_staff' => true,
            'show_pay_to_staff' => false,
        ];
    }

    /**
     * @return array<int, array{start: string, end: string, off: bool}>
     */
    public static function scheduleTemplate(): array
    {
        $days = [];

        foreach (range(0, 6) as $day) {
            $days[$day] = [
                'start' => '09:00',
                'end' => '17:00',
                'off' => false,
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function normalize(?array $settings): array
    {
        $normalized = array_merge(self::defaults(), $settings ?? []);

        $normalized['enabled'] = (bool) ($normalized['enabled'] ?? false);
        $normalized['biometric_required'] = (bool) ($normalized['biometric_required'] ?? false);
        $normalized['allow_remote_checkout'] = (bool) ($normalized['allow_remote_checkout'] ?? true);
        $normalized['allow_remote_checkin'] = (bool) ($normalized['allow_remote_checkin'] ?? false);
        $normalized['show_hours_to_staff'] = (bool) ($normalized['show_hours_to_staff'] ?? true);
        $normalized['show_pay_to_staff'] = (bool) ($normalized['show_pay_to_staff'] ?? false);
        $normalized['accuracy_tolerance_m'] = max(1, (int) ($normalized['accuracy_tolerance_m'] ?? 50));
        $normalized['max_accuracy_m'] = max(1, (int) ($normalized['max_accuracy_m'] ?? 150));
        $normalized['grace_minutes'] = max(0, (int) ($normalized['grace_minutes'] ?? 10));
        $normalized['overtime_after_minutes'] = max(0, (int) ($normalized['overtime_after_minutes'] ?? 480));
        $normalized['overtime_multiplier'] = max(1, (float) ($normalized['overtime_multiplier'] ?? 1.25));
        $normalized['rounding_minutes'] = in_array((int) ($normalized['rounding_minutes'] ?? 0), [0, 5, 10, 15], true)
            ? (int) $normalized['rounding_minutes']
            : 0;
        $normalized['auto_close_after_hours'] = max(1, (int) ($normalized['auto_close_after_hours'] ?? 16));
        $normalized['week_start'] = in_array((int) ($normalized['week_start'] ?? 0), range(0, 6), true)
            ? (int) $normalized['week_start']
            : 0;
        $normalized['default_schedule'] = self::normalizeSchedule($normalized['default_schedule'] ?? null) ?? [];

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     * @return array<int, array{start: string, end: string, off: bool}>|null
     */
    public static function normalizeSchedule(?array $schedule): ?array
    {
        if (! is_array($schedule) || $schedule === []) {
            return null;
        }

        $template = self::scheduleTemplate();
        $normalized = [];
        $hasConfiguredDay = false;

        foreach (range(0, 6) as $day) {
            $source = $schedule[$day] ?? $schedule[(string) $day] ?? null;

            if (! is_array($source)) {
                $normalized[$day] = $template[$day];
                continue;
            }

            $start = is_string($source['start'] ?? null) ? substr($source['start'], 0, 5) : $template[$day]['start'];
            $end = is_string($source['end'] ?? null) ? substr($source['end'], 0, 5) : $template[$day]['end'];
            $off = filter_var($source['off'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $normalized[$day] = [
                'start' => preg_match('/^\d{2}:\d{2}$/', $start) ? $start : $template[$day]['start'],
                'end' => preg_match('/^\d{2}:\d{2}$/', $end) ? $end : $template[$day]['end'],
                'off' => $off,
            ];

            if ($source !== []) {
                $hasConfiguredDay = true;
            }
        }

        return $hasConfiguredDay ? $normalized : null;
    }

    /**
     * @return array<string, string>
     */
    public static function timezoneOptions(): array
    {
        return [
            'Asia/Gaza' => 'Asia/Gaza',
            'Asia/Jerusalem' => 'Asia/Jerusalem',
            'Asia/Amman' => 'Asia/Amman',
            'Asia/Beirut' => 'Asia/Beirut',
            'Asia/Riyadh' => 'Asia/Riyadh',
            'Asia/Dubai' => 'Asia/Dubai',
            'Asia/Baghdad' => 'Asia/Baghdad',
            'Asia/Kuwait' => 'Asia/Kuwait',
            'Asia/Qatar' => 'Asia/Qatar',
            'Asia/Bahrain' => 'Asia/Bahrain',
            'Africa/Cairo' => 'Africa/Cairo',
            'Europe/Istanbul' => 'Europe/Istanbul',
            'Europe/Athens' => 'Europe/Athens',
            'Europe/London' => 'Europe/London',
            'Europe/Paris' => 'Europe/Paris',
            'UTC' => 'UTC',
        ];
    }

    public static function ensurePortalKey(User $owner): User
    {
        if (! $owner->staff_portal_key) {
            $owner->forceFill(['staff_portal_key' => Str::random(32)])->save();
            $owner->refresh();
        }

        return $owner;
    }

    public static function portalUrl(User $owner): string
    {
        self::ensurePortalKey($owner);

        if (\Illuminate\Support\Facades\Route::has('staff.portal')) {
            return route('staff.portal', ['key' => $owner->staff_portal_key]);
        }

        return url('/staff/' . $owner->staff_portal_key);
    }
}
