<?php

use App\Http\Middleware\StaffPortalAuth;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\Geofence;
use Carbon\Carbon;
use Tests\Support\Builds;

uses(Builds::class);

function staffOwner(array $attributes = []): \App\Models\User {
    $owner = test()->makeOwner(array_merge([
        'staff_portal_key' => 'portal-' . uniqid(),
        'attendance_settings' => [
            'enabled' => true,
            'biometric_required' => false,
            'allow_remote_checkout' => true,
            'allow_remote_checkin' => false,
            'accuracy_tolerance_m' => 50,
            'max_accuracy_m' => 150,
            'auto_close_after_hours' => 16,
            'show_hours_to_staff' => true,
            'show_pay_to_staff' => true,
        ],
        'timezone' => 'Asia/Jerusalem',
    ], $attributes));

    return $owner;
}

function staffEmployee(\App\Models\User $owner, array $attributes = []): Employee {
    $employee = new Employee(array_merge([
        'shop_owner_id' => $owner->id,
        'name' => 'Staff Member',
        'job_title' => 'Cashier',
        'monthly_salary' => 1500,
        'salary_type' => 'hourly',
        'hourly_rate' => 12.50,
        'username' => 'staff-user',
        'password' => 'secret-pass',
        'portal_enabled' => true,
        'is_active' => true,
    ], $attributes));
    $employee->save();

    return $employee->fresh();
}

function attendanceLocation(\App\Models\User $owner, array $attributes = []): AttendanceLocation {
    return AttendanceLocation::withoutGlobalScopes()->create(array_merge([
        'user_id' => $owner->id,
        'name' => 'Main branch',
        'latitude' => 31.501,
        'longitude' => 34.466,
        'radius_m' => 120,
        'is_active' => true,
    ], $attributes));
}

function staffOrigin(\App\Models\User $owner): string {
    $parts = parse_url(route('staff.portal', $owner->staff_portal_key));
    $host = $parts['host'] ?? 'localhost';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return 'https://' . $host . $port;
}

function staffRoute(string $name, \App\Models\User $owner, array $parameters = []): string {
    return route($name, array_merge(['key' => $owner->staff_portal_key], $parameters), false);
}

function staffHeaders(\App\Models\User $owner, array $headers = []): array {
    return array_merge([
        'Origin' => staffOrigin($owner),
        'X-Forwarded-Proto' => 'https',
    ], $headers);
}

function staffLoginNonce(\App\Models\User $owner): string {
    $nonce = 'nonce-' . uniqid('', true);
    cache()->put(
        'staff-login-nonce:' . $owner->id . ':' . hash('sha256', $nonce),
        true,
        now()->addMinutes(30)
    );

    return $nonce;
}

test('public staff portal page renders for guests', function () {
    $owner = staffOwner();

    $this->get(staffRoute('staff.portal', $owner))
        ->assertOk()
        ->assertSee(__('staff.ui.sign_in_heading'));
})->group('staff-portal');

test('staff login creates a persistent device and state endpoint authenticates with the portal cookie', function () {
    $owner = staffOwner();
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    $nonce = staffLoginNonce($owner);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
            'username' => $employee->username,
            'password' => 'secret-pass',
            'login_nonce' => $nonce,
        ]);

    $login->assertOk()
        ->assertJsonPath('state.authenticated', true)
        ->assertJsonPath('state.employee.name', $employee->name)
        ->assertCookie(StaffPortalAuth::cookieName($owner));

    $token = $login->getCookie(StaffPortalAuth::cookieName($owner))->getValue();
    $csrf = $login->json('state.csrf');

    expect($token)->toBeString()->and($csrf)->toBeString();
    $device = EmployeeDevice::query()->first();
    expect($device)->not->toBeNull()
        ->and($device->token_hash)->toBe(StaffPortalAuth::hashToken($token));

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie(StaffPortalAuth::cookieName($owner), $token)
        ->getJson(staffRoute('staff.state', $owner))
        ->assertOk()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('csrf', StaffPortalAuth::csrfForTokenHash($device->token_hash));
});

test('login is blocked for disabled shops and inactive employees', function () {
    $disabledOwner = staffOwner(['role' => 'disabled']);
    $employee = staffEmployee($disabledOwner);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($disabledOwner))->postJson(staffRoute('staff.login', $disabledOwner), [
        'username' => $employee->username,
        'password' => 'secret-pass',
        'login_nonce' => 'blocked-shop',
    ])->assertStatus(422);

    $owner = staffOwner();
    $inactive = staffEmployee($owner, ['username' => 'inactive', 'is_active' => false]);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
        'username' => $inactive->username,
        'password' => 'secret-pass',
        'login_nonce' => staffLoginNonce($owner),
    ])->assertStatus(422);

    $inactiveOwner = staffOwner(['is_active' => false]);
    $inactiveOwnerEmployee = staffEmployee($inactiveOwner, ['username' => 'owner-inactive']);
    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($inactiveOwner))->postJson(staffRoute('staff.login', $inactiveOwner), [
        'username' => $inactiveOwnerEmployee->username,
        'password' => 'secret-pass',
        'login_nonce' => 'inactive-owner',
    ])->assertStatus(422);
});

test('login rejects cross-site requests and insecure non-local portal state is unavailable', function () {
    $owner = staffOwner();
    $employee = staffEmployee($owner);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner, ['Origin' => 'https://evil.example']))
        ->postJson(staffRoute('staff.login', $owner), [
            'username' => $employee->username,
            'password' => 'secret-pass',
            'login_nonce' => staffLoginNonce($owner),
        ])->assertStatus(422)
        ->assertJsonValidationErrors('username');

    $this->call('GET', '/staff/' . $owner->staff_portal_key . '/state', [], [], [], [
        'HTTP_HOST' => 'shop.example.test',
        'HTTP_ACCEPT' => 'application/json',
    ])->assertOk()
        ->assertJsonPath('portal_available', false)
        ->assertJsonPath('secure_context', false);
});

test('revoked devices lose access immediately and write endpoints require the csrf header', function () {
    $owner = staffOwner();
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    $nonce = staffLoginNonce($owner);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
        'username' => $employee->username,
        'password' => 'secret-pass',
        'login_nonce' => $nonce,
    ]);

    $token = $login->getCookie(StaffPortalAuth::cookieName($owner))->getValue();
    $csrf = $login->json('state.csrf');
    $cookieName = StaffPortalAuth::cookieName($owner);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie($cookieName, $token)
        ->postJson(staffRoute('staff.punch', $owner), [
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 20,
        ])->assertStatus(403)
        ->assertJsonPath('message', __('staff.errors.invalid_header_token'));

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $token)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_in',
            'request_id' => 'req-1',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 20,
        ])->assertOk();

    EmployeeDevice::query()->first()->update(['revoked_at' => now()]);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie($cookieName, $token)
        ->getJson(staffRoute('staff.state', $owner))
        ->assertOk()
        ->assertJsonPath('authenticated', false);
});

test('geofence honors tolerance and distance', function () {
    $owner = staffOwner(['attendance_settings' => ['enabled' => true, 'accuracy_tolerance_m' => 50]]);
    attendanceLocation($owner, ['latitude' => 31.501, 'longitude' => 34.466, 'radius_m' => 100]);
    attendanceLocation($owner, ['name' => 'Farther larger branch', 'latitude' => 31.5030, 'longitude' => 34.466, 'radius_m' => 350]);

    /** @var Geofence $geofence */
    $geofence = app(Geofence::class);

    $inside = $geofence->evaluate($owner->id, 31.5017, 34.466, 40);
    expect($inside['inside'])->toBeTrue();

    $insideFarther = $geofence->evaluate($owner->id, 31.5043, 34.466, 20);
    expect($insideFarther['inside'])->toBeTrue()
        ->and($insideFarther['location_name'])->toBe('Farther larger branch');

    $outside = $geofence->evaluate($owner->id, 31.5070, 34.466, 10);
    expect($outside['inside'])->toBeFalse()
        ->and($outside['distance_m'])->toBeInt();
});

test('attendance service supports check in, remote checkout, and midnight work dates', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 20:55:00', 'UTC'));

    $owner = staffOwner(['timezone' => 'Asia/Jerusalem']);
    $employee = staffEmployee($owner);
    attendanceLocation($owner);

    /** @var AttendanceService $service */
    $service = app(AttendanceService::class);

    $checkIn = $service->checkIn($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 15,
        'device_id' => 5,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    expect($checkIn->work_date->toDateString())->toBe('2026-10-03');

    Carbon::setTestNow(Carbon::parse('2026-10-03 22:10:00', 'UTC'));

    $checkOut = $service->checkOut($employee, [
        'lat' => 31.700,
        'lng' => 34.466,
        'accuracy' => 40,
        'device_id' => 5,
        'method' => 'manual',
        'claimed_time' => '2026-10-04 00:55',
        'reason' => 'Forgot to clock out before leaving',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    expect($checkOut->status)->toBe('needs_review')
        ->and($checkOut->check_out_remote)->toBeTrue()
        ->and($checkOut->minutes_worked)->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('offline attendance timing is bounded and later or overlapping events are rejected', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'UTC'));

    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'auto_close_after_hours' => 8,
        'offline_window_hours' => 4,
    ]]);
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    /** @var AttendanceService $service */
    $service = app(AttendanceService::class);

    expect(fn () => $service->checkIn($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 20,
        'device_id' => 10,
        'method' => 'manual',
        'offline' => true,
        'client_time' => '2026-10-04 05:00',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]))->toThrow(\App\Services\Attendance\AttendanceException::class);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-04',
        'check_in_at' => Carbon::parse('2026-10-04 10:00:00', 'UTC'),
        'check_out_at' => Carbon::parse('2026-10-04 11:00:00', 'UTC'),
        'minutes_worked' => 60,
        'status' => 'closed',
        'source' => 'portal',
    ]);

    expect(fn () => $service->checkIn($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 20,
        'device_id' => 10,
        'method' => 'manual',
        'offline' => true,
        'client_time' => '2026-10-04 09:30',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]))->toThrow(\App\Services\Attendance\AttendanceException::class);

    Carbon::setTestNow();
});

test('offline checkout timing uses the configured offline window and inactive employees are blocked at service level', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'UTC'));

    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'auto_close_after_hours' => 8,
        'offline_window_hours' => 2,
    ]]);
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    /** @var AttendanceService $service */
    $service = app(AttendanceService::class);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-04',
        'check_in_at' => Carbon::parse('2026-10-04 08:00:00', 'UTC'),
        'status' => 'open',
        'source' => 'portal',
    ]);

    expect(fn () => $service->checkOut($employee, [
        'lat' => null,
        'lng' => null,
        'accuracy' => null,
        'claimed_time' => '2026-10-04T09:30:00Z',
        'reason' => 'Offline replay',
        'offline' => true,
        'device_id' => 12,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]))->toThrow(\App\Services\Attendance\AttendanceException::class, 'claimed_time_too_old');

    AttendanceRecord::withoutGlobalScopes()->delete();
    $employee->forceFill(['is_active' => false])->save();

    expect(fn () => $service->checkIn($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 20,
        'device_id' => 12,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]))->toThrow(\App\Services\Attendance\AttendanceException::class, 'portal_unavailable');

    Carbon::setTestNow();
});

test('double taps do not create duplicate open records and remote checkout can be disabled', function () {
    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'allow_remote_checkout' => false,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'auto_close_after_hours' => 16,
        'show_hours_to_staff' => true,
        'show_pay_to_staff' => false,
    ]]);
    $employee = staffEmployee($owner);
    attendanceLocation($owner);

    /** @var AttendanceService $service */
    $service = app(AttendanceService::class);

    $service->checkIn($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 10,
        'device_id' => 5,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    expect(fn () => $service->checkIn($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 10,
        'device_id' => 5,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]))->toThrow(\App\Services\Attendance\AttendanceException::class);

    expect(fn () => $service->checkOut($employee, [
        'lat' => 31.900,
        'lng' => 34.466,
        'accuracy' => 10,
        'claimed_time' => now()->format('Y-m-d H:i'),
        'reason' => 'Late',
        'device_id' => 5,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]))->toThrow(\App\Services\Attendance\AttendanceException::class);

    expect(AttendanceRecord::withoutGlobalScopes()->count())->toBe(1);
});

test('very long shifts are capped and flagged for review', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 18:00:00', 'UTC'));

    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'auto_close_after_hours' => 6,
        'max_shift_hours' => 6,
    ]]);
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    /** @var AttendanceService $service */
    $service = app(AttendanceService::class);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-04',
        'check_in_at' => Carbon::parse('2026-10-04 08:00:00', 'UTC'),
        'status' => 'open',
        'source' => 'portal',
    ]);

    $record = $service->checkOut($employee, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 10,
        'device_id' => 22,
        'method' => 'manual',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    expect($record->status)->toBe('needs_review')
        ->and($record->minutes_worked)->toBe(360)
        ->and($record->check_out_at->toIso8601String())->toBe(Carbon::parse('2026-10-04 14:00:00', 'UTC')->toIso8601String());

    Carbon::setTestNow();
});

test('stale attendance closes automatically and state endpoint triggers opportunistic closure', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00', 'UTC'));

    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'auto_close_after_hours' => 4,
        'show_hours_to_staff' => true,
        'show_pay_to_staff' => false,
    ]]);
    $employee = staffEmployee($owner);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-04',
        'check_in_at' => Carbon::parse('2026-10-04 02:00:00', 'UTC'),
        'status' => 'open',
        'source' => 'portal',
    ]);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
        'username' => $employee->username,
        'password' => 'secret-pass',
        'login_nonce' => staffLoginNonce($owner),
    ]);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie(StaffPortalAuth::cookieName($owner), $login->getCookie(StaffPortalAuth::cookieName($owner))->getValue())
        ->getJson(staffRoute('staff.state', $owner))
        ->assertOk();

    $record = AttendanceRecord::withoutGlobalScopes()->first();
    expect($record->status)->toBe('needs_review')
        ->and($record->source)->toBe('auto')
        ->and($record->minutes_worked)->toBe(240);

    Carbon::setTestNow();
});

test('history page shows summaries after login', function () {
    $owner = staffOwner();
    $employee = staffEmployee($owner);
    attendanceLocation($owner);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-03',
        'check_in_at' => Carbon::parse('2026-10-03 07:00:00', 'UTC'),
        'check_out_at' => Carbon::parse('2026-10-03 15:00:00', 'UTC'),
        'minutes_worked' => 480,
        'status' => 'closed',
        'source' => 'portal',
    ]);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
        'username' => $employee->username,
        'password' => 'secret-pass',
        'login_nonce' => staffLoginNonce($owner),
    ]);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie(StaffPortalAuth::cookieName($owner), $login->getCookie(StaffPortalAuth::cookieName($owner))->getValue())
        ->get(staffRoute('staff.history', $owner))
        ->assertOk()
        ->assertSee('2026-10-03')
        ->assertSee('08:00');
});

test('punch requests are idempotent and reject stale expected actions', function () {
    $owner = staffOwner();
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    $cookieName = StaffPortalAuth::cookieName($owner);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
        'username' => $employee->username,
        'password' => 'secret-pass',
        'login_nonce' => staffLoginNonce($owner),
    ]);

    $cookieValue = $login->getCookie($cookieName)->getValue();
    $csrf = $login->json('state.csrf');

    $first = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $cookieValue)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_in',
            'request_id' => 'dupe-1',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 20,
        ]);

    $first->assertOk()->assertJsonPath('state.attendance.button.action', 'check_out');

    $replay = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $cookieValue)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_in',
            'request_id' => 'dupe-1',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 20,
        ]);

    $replay->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true');

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $cookieValue)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_in',
            'request_id' => 'dupe-2',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 20,
        ])->assertStatus(409)
        ->assertJsonPath('code', 'action_out_of_date');
});

test('invalid or missing accuracy records the punch for review while poor accuracy is still rejected', function () {
    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'biometric_required' => false,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'show_hours_to_staff' => true,
        'show_pay_to_staff' => true,
    ]]);
    $employee = staffEmployee($owner, ['username' => 'accuracy-user']);
    attendanceLocation($owner);
    $cookieName = StaffPortalAuth::cookieName($owner);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
            'username' => $employee->username,
            'password' => 'secret-pass',
            'login_nonce' => staffLoginNonce($owner),
        ]);

    $cookieValue = $login->getCookie($cookieName)->getValue();
    $csrf = $login->json('state.csrf');

    $response = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $cookieValue)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_in',
            'request_id' => 'accuracy-review',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 'spoofed',
        ]);

    $response->assertOk()
        ->assertJsonPath('state.attendance.status', 'open')
        ->assertJsonPath('state.recent_days.0.status', 'needs_review')
        ->assertJsonPath('state.recent_days.0.reason', __('staff.messages.invalid_accuracy_review'));

    $record = AttendanceRecord::withoutGlobalScopes()->latest('id')->firstOrFail();
    expect($record->status)->toBe('needs_review')
        ->and($record->reason)->toBe(__('staff.messages.invalid_accuracy_review'))
        ->and($record->check_in_accuracy_m)->toBeNull();

    $ownerTooPoor = staffOwner(['staff_portal_key' => 'portal-too-poor', 'attendance_settings' => [
        'enabled' => true,
        'biometric_required' => false,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 15,
    ]]);
    $employeeTooPoor = staffEmployee($ownerTooPoor, ['username' => 'too-poor']);
    attendanceLocation($ownerTooPoor);
    $cookieNameTooPoor = StaffPortalAuth::cookieName($ownerTooPoor);

    $loginTooPoor = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($ownerTooPoor))->postJson(staffRoute('staff.login', $ownerTooPoor), [
            'username' => $employeeTooPoor->username,
            'password' => 'secret-pass',
            'login_nonce' => staffLoginNonce($ownerTooPoor),
        ]);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieNameTooPoor, $loginTooPoor->getCookie($cookieNameTooPoor)->getValue())
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $loginTooPoor->json('state.csrf'),
            'Origin' => staffOrigin($ownerTooPoor),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $ownerTooPoor), [
            'action' => 'check_in',
            'request_id' => 'accuracy-too-poor',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 25,
        ])->assertStatus(422)
        ->assertJsonPath('code', 'accuracy_too_low');
});

test('biometric-required checkout is enforced online but offline checkout remains review-only', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'UTC'));

    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'biometric_required' => true,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'offline_window_hours' => 6,
    ]]);
    $employee = staffEmployee($owner, ['biometric_required' => true]);
    attendanceLocation($owner);
    $cookieName = StaffPortalAuth::cookieName($owner);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
            'username' => $employee->username,
            'password' => 'secret-pass',
            'login_nonce' => staffLoginNonce($owner),
        ]);

    $cookieValue = $login->getCookie($cookieName)->getValue();
    $csrf = $login->json('state.csrf');

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-04',
        'check_in_at' => Carbon::parse('2026-10-04 08:00:00', 'UTC'),
        'status' => 'open',
        'source' => 'portal',
    ]);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $cookieValue)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_out',
            'request_id' => 'biometric-checkout-online',
            'lat' => 31.501,
            'lng' => 34.466,
            'accuracy' => 10,
        ])->assertStatus(422)
        ->assertJsonPath('code', 'biometric_required');

    $offline = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withCookie($cookieName, $cookieValue)
        ->withHeaders([
            StaffPortalAuth::CSRF_HEADER => $csrf,
            'Origin' => staffOrigin($owner),
            'X-Forwarded-Proto' => 'https',
        ])->postJson(staffRoute('staff.punch', $owner), [
            'action' => 'check_out',
            'request_id' => 'biometric-checkout-offline',
            'offline' => true,
            'client_time' => '2026-10-04T11:45:00Z',
            'claimed_time' => '2026-10-04T11:45:00Z',
            'reason' => 'Offline replay',
        ]);

    $offline->assertOk()
        ->assertJsonPath('state.recent_days.0.status', 'needs_review');

    $record = AttendanceRecord::withoutGlobalScopes()->latest('id')->firstOrFail();
    expect($record->status)->toBe('needs_review')
        ->and($record->check_out_at?->toIso8601String())->toBe(Carbon::parse('2026-10-04 11:45:00', 'UTC')->toIso8601String());

    Carbon::setTestNow();
});

test('state and history redact hours and pay when staff visibility is disabled', function () {
    $owner = staffOwner(['attendance_settings' => [
        'enabled' => true,
        'biometric_required' => false,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'show_hours_to_staff' => false,
        'show_pay_to_staff' => false,
    ]]);
    $employee = staffEmployee($owner);
    attendanceLocation($owner);
    $cookieName = StaffPortalAuth::cookieName($owner);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-03',
        'check_in_at' => Carbon::parse('2026-10-03 07:00:00', 'UTC'),
        'check_out_at' => Carbon::parse('2026-10-03 15:00:00', 'UTC'),
        'minutes_worked' => 480,
        'status' => 'closed',
        'source' => 'portal',
    ]);

    $login = $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(staffHeaders($owner))->postJson(staffRoute('staff.login', $owner), [
            'username' => $employee->username,
            'password' => 'secret-pass',
            'login_nonce' => staffLoginNonce($owner),
        ]);

    $cookieValue = $login->getCookie($cookieName)->getValue();

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withCredentials()
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie($cookieName, $cookieValue)
        ->getJson(staffRoute('staff.state', $owner))
        ->assertOk()
        ->assertJsonPath('settings.show_hours_to_staff', false)
        ->assertJsonPath('settings.show_pay_to_staff', false)
        ->assertJsonPath('stats.month_hours', null)
        ->assertJsonPath('stats.month_pay', null)
        ->assertJsonPath('recent_days.0.hours', null)
        ->assertJsonPath('attendance.today.hours', null);

    $this->withServerVariables(['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->withCookie($cookieName, $cookieValue)
        ->get(staffRoute('staff.history', $owner))
        ->assertOk()
        ->assertDontSee(__('staff.ui.hours_label'));
});

test('offline punches parse absolute instants correctly for non-utc shops and keep old queued timestamps as utc', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'UTC'));

    $owner = staffOwner(['timezone' => 'Asia/Gaza', 'attendance_settings' => [
        'enabled' => true,
        'allow_remote_checkout' => true,
        'allow_remote_checkin' => false,
        'accuracy_tolerance_m' => 50,
        'max_accuracy_m' => 150,
        'offline_window_hours' => 6,
    ]]);
    $employeeIso = staffEmployee($owner, ['username' => 'iso-user']);
    $employeeLegacy = staffEmployee($owner, ['username' => 'legacy-user']);
    attendanceLocation($owner);
    /** @var AttendanceService $service */
    $service = app(AttendanceService::class);

    $isoRecord = $service->checkIn($employeeIso, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 15,
        'device_id' => 20,
        'method' => 'manual',
        'offline' => true,
        'client_time' => '2026-10-04T08:30:00Z',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    $legacyRecord = $service->checkIn($employeeLegacy, [
        'lat' => 31.501,
        'lng' => 34.466,
        'accuracy' => 15,
        'device_id' => 21,
        'method' => 'manual',
        'offline' => true,
        'client_time' => '2026-10-04T08:30',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    expect($isoRecord->check_in_at->toIso8601String())->toBe('2026-10-04T08:30:00+00:00')
        ->and($legacyRecord->check_in_at->toIso8601String())->toBe('2026-10-04T08:30:00+00:00')
        ->and($isoRecord->work_date->toDateString())->toBe('2026-10-04')
        ->and($legacyRecord->work_date->toDateString())->toBe('2026-10-04');

    Carbon::setTestNow();
});

test('language switching only redirects back to validated staff urls on the same host', function () {
    $owner = staffOwner();
    $expectedPortal = route('staff.portal', $owner->staff_portal_key);
    $portalParts = parse_url($expectedPortal);
    $safeOrigin = ($portalParts['scheme'] ?? 'http') . '://' . ($portalParts['host'] ?? 'localhost') . (isset($portalParts['port']) ? ':' . $portalParts['port'] : '');
    $safeHistory = $safeOrigin . staffRoute('staff.history', $owner) . '?page=2';

    $this->withServerVariables(['HTTPS' => 'on'])
        ->withHeaders(['Referer' => 'https://evil.example/steal'])
        ->get(route('staff.lang', ['key' => $owner->staff_portal_key, 'locale' => 'ar'], false))
        ->assertRedirect($expectedPortal);

    $this->withServerVariables(['HTTPS' => 'on'])
        ->withHeaders(['Referer' => $safeHistory])
        ->get(route('staff.lang', ['key' => $owner->staff_portal_key, 'locale' => 'en'], false))
        ->assertRedirect($safeHistory);
});
