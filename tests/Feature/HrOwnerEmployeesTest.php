<?php

use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeDevice;
use Tests\Support\Builds;

uses(Builds::class);

function makeHrEmployeeRecord(\Tests\TestCase $test, \App\Models\User $owner, array $attributes = []): Employee
{
    static $counter = 1;
    $counter++;

    return Employee::create(array_merge([
        'shop_owner_id' => $owner->id,
        'name' => 'HR Employee ' . $counter,
        'job_title' => 'Cashier',
        'monthly_salary' => 3200,
        'salary_type' => 'monthly',
        'is_active' => true,
        'portal_enabled' => false,
    ], $attributes));
}

test('owner can create and update employee hr profile', function () {
    $owner = $this->makeOwner();

    $response = $this->actingAs($owner)->post(route('shopowner.employees.store'), [
        'name' => 'Ahmad Saleh',
        'job_title' => 'Supervisor',
        'phone' => '0599111111',
        'hire_date' => '2026-10-01',
        'staff_notes' => 'Trusted team lead',
        'salary_type' => 'hourly',
        'monthly_salary' => 0,
        'hourly_rate' => 20,
        'daily_rate' => '',
        'schedule_mode' => 'custom',
        'schedule' => [
            0 => ['start' => '09:00', 'end' => '17:00', 'off' => '0'],
            5 => ['start' => '09:00', 'end' => '17:00', 'off' => '1'],
        ],
        'portal_enabled' => '1',
        'is_active' => '1',
        'username' => 'ahmad.saleh',
        'password' => 'StrongPass123!',
        'biometric_required' => '1',
    ]);

    $response->assertRedirect();

    $employee = Employee::where('shop_owner_id', $owner->id)->firstOrFail();
    expect($employee->username)->toBe('ahmad.saleh')
        ->and($employee->portal_enabled)->toBeTrue()
        ->and($employee->salary_type)->toBe('hourly')
        ->and($employee->schedule)->toBeArray();

    $this->actingAs($owner)->put(route('shopowner.employees.update', $employee), [
        'name' => 'Ahmad Saleh',
        'job_title' => 'Supervisor',
        'phone' => '0599222222',
        'hire_date' => '2026-10-01',
        'staff_notes' => 'Updated note',
        'salary_type' => 'monthly',
        'monthly_salary' => 4000,
        'hourly_rate' => 20,
        'daily_rate' => '',
        'schedule_mode' => 'default',
        'portal_enabled' => '1',
        'is_active' => '0',
        'username' => 'ahmad.saleh',
        'password' => '',
        'biometric_required' => '',
    ])->assertRedirect(route('shopowner.employees.edit', $employee));

    $employee->refresh();
    expect($employee->monthly_salary)->toBe('4000.00')
        ->and($employee->schedule)->toBeNull()
        ->and($employee->is_active)->toBeFalse();
});

test('employee without permission cannot access owner hr pages', function () {
    $owner = $this->makeOwner();
    $employeeUser = $this->makeEmployee($owner, ['view_products']);

    $this->actingAs($employeeUser)->get(route('shopowner.employees.index'))->assertForbidden();
});

test('portal username is unique per shop only', function () {
    $ownerA = $this->makeOwner();
    $ownerB = $this->makeOwner();
    makeHrEmployeeRecord($this, $ownerA, ['username' => 'same.user', 'portal_enabled' => true, 'password' => 'StrongPass123!']);

    $this->actingAs($ownerA)->post(route('shopowner.employees.store'), [
        'name' => 'Second User',
        'job_title' => 'Cashier',
        'salary_type' => 'monthly',
        'monthly_salary' => 1000,
        'schedule_mode' => 'default',
        'portal_enabled' => '1',
        'is_active' => '1',
        'username' => 'same.user',
        'password' => 'StrongPass123!',
    ])->assertSessionHasErrors('username');

    $this->actingAs($ownerB)->post(route('shopowner.employees.store'), [
        'name' => 'Allowed User',
        'job_title' => 'Cashier',
        'salary_type' => 'monthly',
        'monthly_salary' => 1000,
        'schedule_mode' => 'default',
        'portal_enabled' => '1',
        'is_active' => '1',
        'username' => 'same.user',
        'password' => 'StrongPass123!',
    ])->assertRedirect();

    expect(Employee::where('shop_owner_id', $ownerB->id)->where('username', 'same.user')->exists())->toBeTrue();
});

test('owner can revoke devices and remove credentials only for own employee', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $employee = makeHrEmployeeRecord($this, $owner);
    $otherEmployee = makeHrEmployeeRecord($this, $otherOwner);

    $device = EmployeeDevice::create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'token_hash' => str_repeat('a', 64),
        'label' => 'iPhone',
    ]);
    $credential = EmployeeCredential::create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'credential_id' => 'cred-1',
        'credential_hash' => str_repeat('b', 64),
        'public_key' => 'pk',
    ]);
    $otherDevice = EmployeeDevice::create([
        'user_id' => $otherOwner->id,
        'employee_id' => $otherEmployee->id,
        'token_hash' => str_repeat('c', 64),
        'label' => 'Android',
    ]);

    $this->actingAs($owner)->delete(route('shopowner.employees.devices.destroy', [$employee, $device]))->assertRedirect();
    $this->actingAs($owner)->delete(route('shopowner.employees.credentials.destroy', [$employee, $credential]))->assertRedirect();
    $this->actingAs($owner)->delete(route('shopowner.employees.devices.destroy', [$otherEmployee, $otherDevice]))->assertForbidden();

    expect($device->fresh()->revoked_at)->not->toBeNull()
        ->and(EmployeeCredential::find($credential->id))->toBeNull();
});

test('updating employee password or portal access state revokes active portal devices', function () {
    $owner = $this->makeOwner();
    $employee = makeHrEmployeeRecord($this, $owner, [
        'portal_enabled' => true,
        'is_active' => true,
        'username' => 'portal.employee',
        'password' => 'StrongPass123!',
    ]);

    $makeActiveDevice = function (string $tokenHash, string $label) use ($owner, $employee): EmployeeDevice {
        return EmployeeDevice::create([
            'user_id' => $owner->id,
            'employee_id' => $employee->id,
            'token_hash' => $tokenHash,
            'label' => $label,
        ]);
    };

    $passwordDevice = $makeActiveDevice(str_repeat('1', 64), 'Password reset device');
    $this->actingAs($owner)->put(route('shopowner.employees.update', $employee), [
        'name' => $employee->name,
        'job_title' => $employee->job_title,
        'salary_type' => 'monthly',
        'monthly_salary' => 3200,
        'schedule_mode' => 'default',
        'portal_enabled' => '1',
        'is_active' => '1',
        'username' => $employee->username,
        'password' => 'ChangedPass123!',
        'biometric_required' => '',
    ])->assertRedirect(route('shopowner.employees.edit', $employee));

    expect($passwordDevice->fresh()->revoked_at)->not->toBeNull();

    $portalToggleDevice = $makeActiveDevice(str_repeat('2', 64), 'Portal toggle device');
    $this->actingAs($owner)->put(route('shopowner.employees.update', $employee), [
        'name' => $employee->name,
        'job_title' => $employee->job_title,
        'salary_type' => 'monthly',
        'monthly_salary' => 3200,
        'schedule_mode' => 'default',
        'portal_enabled' => '0',
        'is_active' => '1',
        'username' => $employee->username,
        'password' => '',
        'biometric_required' => '',
    ])->assertRedirect(route('shopowner.employees.edit', $employee));

    expect($portalToggleDevice->fresh()->revoked_at)->not->toBeNull();

    $employee->refresh();
    $reactivationDevice = $makeActiveDevice(str_repeat('3', 64), 'Reactivation device');
    $this->actingAs($owner)->put(route('shopowner.employees.update', $employee), [
        'name' => $employee->name,
        'job_title' => $employee->job_title,
        'salary_type' => 'monthly',
        'monthly_salary' => 3200,
        'schedule_mode' => 'default',
        'portal_enabled' => '1',
        'is_active' => '0',
        'username' => 'portal.employee',
        'password' => '',
        'biometric_required' => '',
    ])->assertRedirect(route('shopowner.employees.edit', $employee));

    expect($reactivationDevice->fresh()->revoked_at)->not->toBeNull();
});

test('employee pages render when optional hr columns are null', function () {
    $owner = $this->makeOwner();
    $employee = makeHrEmployeeRecord($this, $owner, [
        'phone' => null,
        'hire_date' => null,
        'schedule' => null,
        'staff_notes' => null,
        'username' => null,
        'password' => null,
        'portal_enabled' => false,
        'biometric_required' => null,
        'hourly_rate' => null,
        'daily_rate' => null,
        'last_portal_login_at' => null,
    ]);

    $this->actingAs($owner)->get(route('shopowner.employees.index'))->assertOk();
    $this->actingAs($owner)->get(route('shopowner.employees.edit', $employee))->assertOk();
    $this->actingAs($owner)->get(route('shopowner.employees.payments', $employee))->assertOk();
});

test('employee payment flow validates period and reuses idempotency tokens', function () {
    $owner = $this->makeOwner();
    $employee = makeHrEmployeeRecord($this, $owner);

    $this->actingAs($owner)->get(route('shopowner.employees.payments', $employee))->assertOk();
    $tokens = session('hr-owner.employee-payment-tokens.' . $employee->id);
    $token = array_key_first($tokens);

    $this->actingAs($owner)->post(route('shopowner.employees.storePayment', $employee), [
        'amount' => 50,
        'payment_date' => '2026-10-10',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => '2026-10',
        'idempotency_key' => $token,
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('shopowner.employees.storePayment', $employee), [
        'amount' => 50,
        'payment_date' => '2026-10-10',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => '2026-10',
        'idempotency_key' => $token,
    ])->assertSessionHasErrors('amount');

    $this->actingAs($owner)->post(route('shopowner.employees.storePayment', $employee), [
        'amount' => 50,
        'payment_date' => '2026-10-10',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => '2026-99',
        'idempotency_key' => 'fresh-token',
    ])->assertSessionHasErrors('period');
});

test('employee payment flow rejects replayed idempotency keys even if the session token is restored', function () {
    $owner = $this->makeOwner();
    $employee = makeHrEmployeeRecord($this, $owner);

    $this->actingAs($owner)->get(route('shopowner.employees.payments', $employee))->assertOk();
    $token = array_key_first(session('hr-owner.employee-payment-tokens.' . $employee->id));

    $payload = [
        'amount' => 50,
        'payment_date' => '2026-10-10',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => '2026-10',
        'idempotency_key' => $token,
    ];

    $this->actingAs($owner)->post(route('shopowner.employees.storePayment', $employee), $payload)->assertRedirect();

    session(['hr-owner.employee-payment-tokens.' . $employee->id => [$token => true]]);

    $this->actingAs($owner)->post(route('shopowner.employees.storePayment', $employee), $payload)
        ->assertSessionHasErrors('amount');

    expect($employee->payments()->count())->toBe(1);
});

test('employee salary and payment forms reject values beyond database precision', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)->post(route('shopowner.employees.store'), [
        'name' => 'Big Salary',
        'job_title' => 'Cashier',
        'salary_type' => 'monthly',
        'monthly_salary' => '100000000.00',
        'hourly_rate' => '10000000000.00',
        'daily_rate' => '10000000000.00',
        'schedule_mode' => 'default',
        'portal_enabled' => '0',
        'is_active' => '1',
    ])->assertSessionHasErrors(['monthly_salary', 'hourly_rate', 'daily_rate']);

    $employee = makeHrEmployeeRecord($this, $owner);

    $this->actingAs($owner)->get(route('shopowner.employees.payments', $employee))->assertOk();
    $token = array_key_first(session('hr-owner.employee-payment-tokens.' . $employee->id));

    $this->actingAs($owner)->post(route('shopowner.employees.storePayment', $employee), [
        'amount' => '100000000.00',
        'payment_date' => '2026-10-10',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => '2026-10',
        'idempotency_key' => $token,
    ])->assertSessionHasErrors('amount');
});
