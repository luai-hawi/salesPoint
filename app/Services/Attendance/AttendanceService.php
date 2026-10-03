<?php

namespace App\Services\Attendance;

use App\Http\Middleware\StaffPortalAuth;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ShopTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        protected Geofence $geofence = new Geofence(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsForOwner(User|int $owner): array
    {
        $settings = $owner instanceof User
            ? ($owner->attendance_settings ?? [])
            : (User::withoutGlobalScopes()->whereKey($owner)->value('attendance_settings') ?? []);

        if (! is_array($settings)) {
            $settings = [];
        }

        return array_merge([
            'enabled' => false,
            'biometric_required' => false,
            'allow_remote_checkout' => true,
            'allow_remote_checkin' => false,
            'accuracy_tolerance_m' => 50,
            'max_accuracy_m' => 150,
            'auto_close_after_hours' => 16,
            'offline_window_hours' => 6,
            'max_shift_hours' => 16,
            'show_hours_to_staff' => true,
            'show_pay_to_staff' => false,
        ], $settings);
    }

    public function openRecord(Employee $employee): ?AttendanceRecord
    {
        return AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $employee->shop_owner_id)
            ->where('employee_id', $employee->id)
            ->whereNull('check_out_at')
            ->latest('check_in_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function checkIn(Employee $employee, array $ctx): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $ctx) {
            $lockedEmployee = Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureEmployeePortalAccess($lockedEmployee);

            $owner = User::withoutGlobalScopes()->findOrFail($lockedEmployee->shop_owner_id);
            $settings = self::settingsForOwner($owner);
            $this->ensurePortalEnabled($owner, $settings);
            $maxShiftHours = $this->maxShiftHours($settings);

            if ($this->openRecord($lockedEmployee)) {
                throw new AttendanceException('already_checked_in');
            }

            $isOffline = (bool) ($ctx['offline'] ?? false);
            $eventTime = $isOffline
                ? $this->validatedOfflineTime($ctx['client_time'] ?? null, $owner, $settings)
                : now()->utc();

            $this->ensureNoOverlappingShift($lockedEmployee, $eventTime, $eventTime->copy()->addHours($maxShiftHours));

            if ($isOffline) {
                $this->ensureNoLaterServerRecordedEvent($lockedEmployee, $eventTime);
            }

            $lat = $this->parseCoordinate($ctx['lat'] ?? null, -90, 90);
            $lng = $this->parseCoordinate($ctx['lng'] ?? null, -180, 180);
            $accuracyState = $this->inspectAccuracy($ctx['accuracy'] ?? null);
            $accuracy = $accuracyState['value'];
            $accuracyReviewReason = $accuracyState['review_reason'];

            if ($lat === null || $lng === null) {
                throw new AttendanceException('location_required');
            }

            if ($accuracy !== null && $accuracy > (float) $settings['max_accuracy_m']) {
                throw new AttendanceException('accuracy_too_low');
            }

            $needsBiometric = $lockedEmployee->biometric_required;
            if ($needsBiometric === null) {
                $needsBiometric = (bool) $settings['biometric_required'];
            }

            if ($needsBiometric && ! (bool) ($ctx['biometric_verified'] ?? false)) {
                throw new AttendanceException('biometric_required');
            }

            $geo = $this->geofence->evaluate((int) $owner->id, $lat, $lng, $accuracy);
            if (! $geo['inside'] && ! (bool) $settings['allow_remote_checkin']) {
                throw new AttendanceException('outside_area');
            }

            $status = $isOffline || $accuracyReviewReason !== null ? 'needs_review' : 'open';
            $source = $isOffline ? 'offline' : 'portal';

            $record = AttendanceRecord::withoutGlobalScopes()->create([
                'user_id' => $owner->id,
                'employee_id' => $lockedEmployee->id,
                'work_date' => ShopTime::localDate($eventTime, $owner),
                'check_in_at' => $eventTime,
                'check_in_lat' => $lat,
                'check_in_lng' => $lng,
                'check_in_accuracy_m' => $accuracy !== null ? (int) round($accuracy) : null,
                'check_in_distance_m' => $geo['distance_m'],
                'check_in_inside' => $geo['inside'],
                'check_in_location_id' => $geo['location_id'],
                'check_in_method' => $ctx['method'] ?? ($needsBiometric ? 'biometric' : 'manual'),
                'check_in_device_id' => $ctx['device_id'] ?? null,
                'status' => $status,
                'source' => $source,
                'reason' => $accuracyReviewReason,
                'ip_address' => $this->truncate($ctx['ip'] ?? null, 45),
                'user_agent' => $this->truncate($ctx['user_agent'] ?? null, 255),
            ]);

            ActivityLogger::record(
                'checked_in',
                'staff_member',
                $lockedEmployee,
                [
                    'employee_name' => $lockedEmployee->name,
                    'work_date' => $record->work_date?->toDateString(),
                    'source' => $source,
                ],
                null,
                $lockedEmployee->name,
                $owner->id,
                $lockedEmployee->name,
            );

            return $record->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function checkOut(Employee $employee, array $ctx): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $ctx) {
            $lockedEmployee = Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureEmployeePortalAccess($lockedEmployee);

            $owner = User::withoutGlobalScopes()->findOrFail($lockedEmployee->shop_owner_id);
            $settings = self::settingsForOwner($owner);
            $this->ensurePortalEnabled($owner, $settings);
            $maxShiftHours = $this->maxShiftHours($settings);

            $record = AttendanceRecord::withoutGlobalScopes()
                ->where('user_id', $owner->id)
                ->where('employee_id', $lockedEmployee->id)
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->latest('check_in_at')
                ->first();

            if (! $record) {
                throw new AttendanceException('not_checked_in');
            }

            $isOffline = (bool) ($ctx['offline'] ?? false);
            $now = now()->utc();
            $lat = $this->parseCoordinate($ctx['lat'] ?? null, -90, 90);
            $lng = $this->parseCoordinate($ctx['lng'] ?? null, -180, 180);
            $accuracyState = $this->inspectAccuracy($ctx['accuracy'] ?? null);
            $accuracy = $accuracyState['value'];
            $accuracyReviewReason = $accuracyState['review_reason'];
            $hasValidCoords = $lat !== null && $lng !== null;
            $geo = $hasValidCoords ? $this->geofence->evaluate((int) $owner->id, $lat, $lng, $accuracy) : [
                'inside' => false,
                'distance_m' => null,
                'location_id' => null,
                'location_name' => null,
            ];

            $claimedTimeRaw = $ctx['claimed_time'] ?? null;
            $claimedTime = $claimedTimeRaw !== null && $claimedTimeRaw !== ''
                ? ($isOffline
                    ? $this->parseClientReportedInstant((string) $claimedTimeRaw)
                    : $this->parseShopDateTime((string) $claimedTimeRaw, $owner))
                : null;

            $needsBiometric = $lockedEmployee->biometric_required;
            if ($needsBiometric === null) {
                $needsBiometric = (bool) $settings['biometric_required'];
            }

            if ($needsBiometric && ! (bool) ($ctx['biometric_verified'] ?? false) && ! $isOffline) {
                throw new AttendanceException('biometric_required');
            }

            $remote = ! $hasValidCoords || ! $geo['inside'] || $claimedTime !== null || $isOffline;
            if ($remote) {
                if (! (bool) $settings['allow_remote_checkout']) {
                    throw new AttendanceException('outside_area');
                }

                if ($claimedTime === null) {
                    throw new AttendanceException('claimed_time_required');
                }

                if (($reason = trim((string) ($ctx['reason'] ?? ''))) === '') {
                    throw new AttendanceException('reason_required');
                }

                if ($claimedTime->lt($record->check_in_at)) {
                    throw new AttendanceException('claimed_time_before_checkin');
                }

                if ($claimedTime->gt($now)) {
                    throw new AttendanceException('claimed_time_future');
                }

                $oldestClaimedTime = $isOffline
                    ? $now->copy()->subHours($this->offlineWindowHours($settings))
                    : $now->copy()->subDay();

                if ($claimedTime->lt($oldestClaimedTime)) {
                    throw new AttendanceException('claimed_time_too_old');
                }

                $maxCheckoutAt = $record->check_in_at->copy()->addHours($maxShiftHours);
                $status = 'needs_review';
                if ($claimedTime->gt($maxCheckoutAt)) {
                    $claimedTime = $maxCheckoutAt;
                }

                $this->ensureNoOverlappingShift($lockedEmployee, $record->check_in_at, $claimedTime, $record->id);

                $record->fill([
                    'check_out_at' => $claimedTime,
                    'check_out_recorded_at' => $now,
                    'check_out_lat' => $lat,
                    'check_out_lng' => $lng,
                    'check_out_accuracy_m' => $accuracy !== null ? (int) round($accuracy) : null,
                    'check_out_distance_m' => $geo['distance_m'],
                    'check_out_inside' => $geo['inside'],
                    'check_out_location_id' => $geo['location_id'],
                    'check_out_method' => $ctx['method'] ?? 'manual',
                    'check_out_device_id' => $ctx['device_id'] ?? null,
                    'check_out_remote' => true,
                    'reason' => $this->combineReasons($reason, $accuracyReviewReason),
                    'status' => $status,
                    'source' => $isOffline ? 'offline' : ($ctx['source'] ?? 'portal'),
                    'minutes_worked' => max(0, $record->check_in_at->diffInMinutes($claimedTime)),
                    'ip_address' => $this->truncate($ctx['ip'] ?? $record->ip_address, 45),
                    'user_agent' => $this->truncate($ctx['user_agent'] ?? $record->user_agent, 255),
                ]);
            } else {
                $effectiveCheckoutAt = $now;
                $status = ($isOffline || $accuracyReviewReason !== null || ($needsBiometric && ! (bool) ($ctx['biometric_verified'] ?? false)))
                    ? 'needs_review'
                    : 'closed';
                $maxCheckoutAt = $record->check_in_at->copy()->addHours($maxShiftHours);
                if ($effectiveCheckoutAt->gt($maxCheckoutAt)) {
                    $effectiveCheckoutAt = $maxCheckoutAt;
                    $status = 'needs_review';
                }

                $this->ensureNoOverlappingShift($lockedEmployee, $record->check_in_at, $effectiveCheckoutAt, $record->id);

                $record->fill([
                    'check_out_at' => $effectiveCheckoutAt,
                    'check_out_recorded_at' => $now,
                    'check_out_lat' => $lat,
                    'check_out_lng' => $lng,
                    'check_out_accuracy_m' => (int) round($accuracy),
                    'check_out_distance_m' => $geo['distance_m'],
                    'check_out_inside' => true,
                    'check_out_location_id' => $geo['location_id'],
                    'check_out_method' => $ctx['method'] ?? 'manual',
                    'check_out_device_id' => $ctx['device_id'] ?? null,
                    'check_out_remote' => false,
                    'reason' => $accuracyReviewReason,
                    'status' => $status,
                    'source' => $isOffline ? 'offline' : ($ctx['source'] ?? 'portal'),
                    'minutes_worked' => max(0, $record->check_in_at->diffInMinutes($effectiveCheckoutAt)),
                    'ip_address' => $this->truncate($ctx['ip'] ?? $record->ip_address, 45),
                    'user_agent' => $this->truncate($ctx['user_agent'] ?? $record->user_agent, 255),
                ]);
            }

            $record->save();

            ActivityLogger::record(
                'checked_out',
                'staff_member',
                $lockedEmployee,
                [
                    'employee_name' => $lockedEmployee->name,
                    'work_date' => $record->work_date?->toDateString(),
                    'status' => $record->status,
                    'source' => $record->source,
                ],
                null,
                $lockedEmployee->name,
                $owner->id,
                $lockedEmployee->name,
            );

            return $record->fresh();
        });
    }

    public function closeStaleForOwner(User|int $owner): int
    {
        $ownerModel = $owner instanceof User ? $owner : User::withoutGlobalScopes()->find($owner);
        if (! $ownerModel || ! StaffPortalAuth::ownerIsAvailable($ownerModel)) {
            return 0;
        }

        $settings = self::settingsForOwner($ownerModel);
        $hours = min(
            $this->maxShiftHours($settings),
            max(1, (int) ($settings['auto_close_after_hours'] ?? 16))
        );
        $cutoff = now()->utc()->subHours($hours);
        $closed = 0;

        AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $ownerModel->id)
            ->whereNull('check_out_at')
            ->where('check_in_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($hours, &$closed, $ownerModel) {
                foreach ($records as $record) {
                    DB::transaction(function () use ($record, $hours, &$closed, $ownerModel) {
                        $locked = AttendanceRecord::withoutGlobalScopes()
                            ->whereKey($record->id)
                            ->whereNull('check_out_at')
                            ->lockForUpdate()
                            ->first();

                        if (! $locked) {
                            return;
                        }

                        $autoCheckoutAt = $locked->check_in_at->copy()->addHours($hours);
                        $minutes = max(0, $locked->check_in_at->diffInMinutes($autoCheckoutAt));

                        $locked->fill([
                            'check_out_at' => $autoCheckoutAt,
                            'check_out_recorded_at' => now()->utc(),
                            'check_out_remote' => true,
                            'check_out_method' => 'manual',
                            'status' => 'needs_review',
                            'source' => 'auto',
                            'minutes_worked' => $minutes,
                            'reason' => $locked->reason ?: 'auto-close',
                        ])->save();

                        $employee = Employee::find($locked->employee_id);
                        ActivityLogger::record(
                            'checked_out',
                            'staff_member',
                            $employee,
                            [
                                'employee_name' => $employee?->name,
                                'work_date' => $locked->work_date?->toDateString(),
                                'status' => 'needs_review',
                                'source' => 'auto',
                            ],
                            null,
                            $employee?->name,
                            $ownerModel->id,
                            $employee?->name,
                        );

                        $closed++;
                    });
                }
            });

        return $closed;
    }

    private function ensurePortalEnabled(User $owner, array $settings): void
    {
        if (! StaffPortalAuth::ownerIsAvailable($owner)) {
            throw new AttendanceException('portal_unavailable');
        }

        if (! (bool) ($settings['enabled'] ?? false)) {
            throw new AttendanceException('attendance_disabled');
        }
    }

    private function ensureEmployeePortalAccess(Employee $employee): void
    {
        if (! $employee->portal_enabled || ! $employee->is_active) {
            throw new AttendanceException('portal_unavailable');
        }
    }

    private function parseCoordinate(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new AttendanceException('invalid_location');
        }

        $float = (float) $value;
        if (! is_finite($float) || $float < $min || $float > $max) {
            throw new AttendanceException('invalid_location');
        }

        return $float;
    }

    private function parseAccuracy(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new AttendanceException('invalid_location');
        }

        $float = (float) $value;
        if (! is_finite($float) || $float < 0) {
            throw new AttendanceException('invalid_location');
        }

        return $float;
    }

    /**
     * @return array{value: ?float, review_reason: ?string}
     */
    private function inspectAccuracy(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['value' => null, 'review_reason' => __('staff.messages.invalid_accuracy_review')];
        }

        if (! is_numeric($value)) {
            return ['value' => null, 'review_reason' => __('staff.messages.invalid_accuracy_review')];
        }

        $float = (float) $value;
        if (! is_finite($float)) {
            return ['value' => null, 'review_reason' => __('staff.messages.invalid_accuracy_review')];
        }

        if ($float <= 0) {
            return ['value' => null, 'review_reason' => __('staff.messages.invalid_accuracy_review')];
        }

        return ['value' => $this->parseAccuracy($float), 'review_reason' => null];
    }

    private function parseShopDateTime(string $value, User $owner): Carbon
    {
        try {
            return Carbon::parse($value, ShopTime::timezone($owner))->utc();
        } catch (\Throwable) {
            throw new AttendanceException('claimed_time_invalid');
        }
    }

    private function parseClientReportedInstant(string $value): Carbon
    {
        try {
            if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
                return Carbon::parse($value)->utc();
            }

            return Carbon::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            throw new AttendanceException('claimed_time_invalid');
        }
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function validatedOfflineTime(mixed $value, User $owner, array $settings): CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            throw new AttendanceException('claimed_time_required');
        }

        $time = $this->parseClientReportedInstant($value);
        $now = now()->utc();

        if ($time->gt($now)) {
            throw new AttendanceException('claimed_time_future');
        }

        if ($time->lt($now->copy()->subHours($this->offlineWindowHours($settings)))) {
            throw new AttendanceException('claimed_time_too_old');
        }

        return $time;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function maxShiftHours(array $settings): int
    {
        return max(1, min(16, (int) ($settings['max_shift_hours'] ?? $settings['auto_close_after_hours'] ?? 16)));
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function offlineWindowHours(array $settings): int
    {
        return max(1, min($this->maxShiftHours($settings), (int) ($settings['offline_window_hours'] ?? 6)));
    }

    private function ensureNoLaterServerRecordedEvent(Employee $employee, CarbonInterface $eventTime): void
    {
        $hasLaterEvent = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $employee->shop_owner_id)
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($eventTime) {
                $query->where('check_in_at', '>', $eventTime)
                    ->orWhere('check_out_at', '>', $eventTime);
            })
            ->exists();

        if ($hasLaterEvent) {
            throw new AttendanceException('later_event_exists');
        }
    }

    private function ensureNoOverlappingShift(Employee $employee, CarbonInterface $from, CarbonInterface $to, ?int $ignoreRecordId = null): void
    {
        $query = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $employee->shop_owner_id)
            ->where('employee_id', $employee->id)
            ->when($ignoreRecordId !== null, fn ($builder) => $builder->whereKeyNot($ignoreRecordId))
            ->where('check_in_at', '<', $to)
            ->where(function ($builder) use ($from) {
                $builder->whereNull('check_out_at')
                    ->orWhere('check_out_at', '>', $from);
            });

        if ($query->exists()) {
            throw new AttendanceException('overlapping_shift');
        }
    }

    private function truncate(mixed $value, int $limit): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    private function combineReasons(?string $primary, ?string $secondary): ?string
    {
        $parts = array_values(array_filter([
            $primary !== null ? trim($primary) : null,
            $secondary !== null ? trim($secondary) : null,
        ], fn (?string $value) => $value !== null && $value !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(' — ', array_unique($parts));
    }
}
