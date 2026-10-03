<?php

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Support\ShopTime;
use Illuminate\Support\Facades\DB;
use Tests\Support\Builds;

uses(Builds::class);

function makeAttendanceEmployee(\App\Models\User $owner, array $attributes = []): Employee
{
    return Employee::create(array_merge([
        'shop_owner_id' => $owner->id,
        'name' => 'Attendance Worker',
        'job_title' => 'Seller',
        'monthly_salary' => 3000,
        'salary_type' => 'monthly',
        'is_active' => true,
    ], $attributes));
}

test('owner can save attendance settings regenerate portal key and revoke portal devices', function () {
    $owner = $this->makeOwner(['staff_portal_key' => 'old-key-123456789012345678901234']);
    $employee = makeAttendanceEmployee($owner);
    EmployeeDevice::create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'token_hash' => str_repeat('d', 64),
        'label' => 'Portal phone',
    ]);

    $this->actingAs($owner)->put(route('shopowner.attendance.settings.update'), [
        'timezone' => 'Asia/Gaza',
        'enabled' => '1',
        'biometric_required' => '1',
        'allow_remote_checkout' => '1',
        'allow_remote_checkin' => '0',
        'accuracy_tolerance_m' => 60,
        'max_accuracy_m' => 140,
        'grace_minutes' => 15,
        'overtime_after_minutes' => 500,
        'overtime_multiplier' => 1.5,
        'rounding_minutes' => 10,
        'auto_close_after_hours' => 18,
        'week_start' => 0,
        'show_hours_to_staff' => '1',
        'show_pay_to_staff' => '0',
        'default_schedule' => [
            0 => ['start' => '09:00', 'end' => '17:00', 'off' => '0'],
        ],
    ])->assertRedirect();

    $owner->refresh();
    expect($owner->timezone)->toBe('Asia/Gaza')
        ->and($owner->attendance_settings['enabled'])->toBeTrue();

    $this->actingAs($owner)->post(route('shopowner.attendance.settings.regenerate-portal'))->assertRedirect();

    $owner->refresh();
    expect($owner->staff_portal_key)->not->toBe('old-key-123456789012345678901234');
    expect(EmployeeDevice::first()->fresh()->revoked_at)->not->toBeNull();
});

test('owner can create update and delete attendance locations with validation', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)->post(route('shopowner.attendance.locations.store'), [
        'name' => 'Main branch',
        'latitude' => 31.5017,
        'longitude' => 34.4668,
        'radius_m' => 100,
        'is_active' => '1',
    ])->assertRedirect();

    $location = AttendanceLocation::withoutGlobalScopes()->firstOrFail();
    expect($location->name)->toBe('Main branch');

    $this->actingAs($owner)->put(route('shopowner.attendance.locations.update', $location), [
        'name' => 'Main branch updated',
        'latitude' => 31.6,
        'longitude' => 34.5,
        'radius_m' => 250,
        'is_active' => '0',
    ])->assertRedirect();

    $location->refresh();
    expect($location->name)->toBe('Main branch updated')->and($location->is_active)->toBeFalse();

    $this->actingAs($owner)->post(route('shopowner.attendance.locations.store'), [
        'name' => 'Bad place',
        'latitude' => 500,
        'longitude' => 34.4,
        'radius_m' => 10,
    ])->assertSessionHasErrors(['latitude', 'radius_m']);

    $this->actingAs($owner)->delete(route('shopowner.attendance.locations.destroy', $location))->assertRedirect();
    expect(AttendanceLocation::withoutGlobalScopes()->count())->toBe(0);
});

test('attendance board shows filters and supports review edit and delete flow', function () {
    $owner = $this->makeOwner(['timezone' => 'Asia/Gaza']);
    $employee = makeAttendanceEmployee($owner);
    $record = AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-03',
        'check_in_at' => now()->subHours(8),
        'check_out_at' => now()->subHours(1),
        'check_out_recorded_at' => now(),
        'status' => 'needs_review',
        'source' => 'portal',
        'check_out_remote' => true,
        'check_out_distance_m' => 3400,
        'reason' => 'Forgot to check out on site',
        'minutes_worked' => 420,
    ]);

    $this->actingAs($owner)->get(route('shopowner.attendance.index', ['from' => '2026-10-03', 'to' => '2026-10-03', 'status' => 'flagged']))
        ->assertOk()
        ->assertSee($employee->name);

    $this->actingAs($owner)->post(route('shopowner.attendance.review', $record), [
        'decision' => 'edit',
        'check_in_local' => '2026-10-03T09:00',
        'check_out_local' => '2026-10-03T17:00',
        'review_note' => 'Verified with supervisor',
    ])->assertRedirect();

    $record->refresh();
    expect($record->status)->toBe('approved')
        ->and($record->minutes_worked)->toBe(480);

    $this->actingAs($owner)->put(route('shopowner.attendance.update', $record), [
        'check_in_local' => '2026-10-03T09:15',
        'check_out_local' => '2026-10-03T17:15',
        'status' => 'approved',
        'reason' => 'Adjusted',
        'review_note' => 'Adjusted',
    ])->assertRedirect();

    $record->refresh();
    expect($record->reason)->toBe('Adjusted');

    $this->actingAs($owner)->delete(route('shopowner.attendance.destroy', $record))->assertRedirect();
    expect(AttendanceRecord::withoutGlobalScopes()->count())->toBe(0);
});

test('manual attendance creation respects shop timezone for work date near midnight', function () {
    $owner = $this->makeOwner(['timezone' => 'Asia/Gaza']);
    $employee = makeAttendanceEmployee($owner);

    $this->actingAs($owner)->post(route('shopowner.attendance.store'), [
        'employee_id' => $employee->id,
        'check_in_local' => '2026-10-04T00:30',
        'check_out_local' => '2026-10-04T08:30',
        'status' => 'approved',
        'reason' => 'Night shift',
    ])->assertRedirect();

    $record = AttendanceRecord::withoutGlobalScopes()->firstOrFail();
    expect($record->work_date->toDateString())->toBe('2026-10-04');

    $this->actingAs($owner)->get(route('shopowner.attendance.timesheet', ['employee' => $employee, 'period' => '2026-10']))->assertOk();
});

test('attendance csv export uses the shop timezone', function () {
    $owner = $this->makeOwner(['timezone' => 'Asia/Gaza']);
    $employee = makeAttendanceEmployee($owner, ['name' => 'Timezone Worker']);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-03',
        'check_in_at' => \Carbon\Carbon::parse('2026-10-03 06:30:00', 'UTC'),
        'check_out_at' => \Carbon\Carbon::parse('2026-10-03 14:45:00', 'UTC'),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 495,
    ]);

    $csv = $this->actingAs($owner)->get(route('shopowner.attendance.export', [
        'from' => '2026-10-03',
        'to' => '2026-10-03',
    ]))->streamedContent();

    expect($csv)->toContain('2026-10-03 09:30')
        ->and($csv)->toContain('2026-10-03 17:45')
        ->and($csv)->not->toContain('2026-10-03 06:30');
});

test('admin manual attendance uses the employee owner timezone and tenant', function () {
    $admin = $this->makeAdmin(['timezone' => 'UTC']);
    $owner = $this->makeOwner(['timezone' => 'Asia/Riyadh']);
    $employee = makeAttendanceEmployee($owner);

    $this->actingAs($admin)->post(route('shopowner.attendance.store'), [
        'employee_id' => $employee->id,
        'check_in_local' => '2026-10-04T00:30',
        'check_out_local' => '2026-10-04T08:30',
        'status' => 'approved',
        'reason' => 'Admin correction',
    ])->assertRedirect();

    $record = AttendanceRecord::withoutGlobalScopes()->firstOrFail();

    expect($record->user_id)->toBe($owner->id)
        ->and($record->work_date->toDateString())->toBe('2026-10-04')
        ->and($record->check_in_at->toDateTimeString())->toBe('2026-10-03 21:30:00');
});

test('hr owner pages render with legacy null hr columns', function () {
    $owner = $this->makeOwner();
    $owner->forceFill([
        'attendance_settings' => null,
        'staff_portal_key' => null,
        'timezone' => null,
    ])->save();

    $employeeId = DB::table('employees')->insertGetId([
        'shop_owner_id' => $owner->id,
        'name' => 'Legacy Null Employee',
        'job_title' => 'Legacy role',
        'monthly_salary' => 0,
        'created_at' => now(),
        'updated_at' => now(),
        'username' => null,
        'password' => null,
        'phone' => null,
        'biometric_required' => null,
        'hourly_rate' => null,
        'daily_rate' => null,
        'hire_date' => null,
        'schedule' => null,
        'staff_notes' => null,
        'last_portal_login_at' => null,
    ]);

    $employee = Employee::findOrFail($employeeId);

    $this->actingAs($owner)->get('/shopowner/attendance')->assertOk();
    $this->actingAs($owner)->get('/shopowner/attendance/settings')->assertOk();
    $this->actingAs($owner)->get('/shopowner/payroll')->assertOk();
    $this->actingAs($owner)->get('/shopowner/employees')->assertOk()->assertSee($employee->name);
});
