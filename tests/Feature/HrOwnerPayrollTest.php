<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayment;
use App\Models\IdempotencyKey;
use App\Services\Payroll\PayrollService;
use App\Support\CsvSanitizer;
use Tests\Support\Builds;

uses(Builds::class);

function makePayrollEmployee(\App\Models\User $owner, array $attributes = []): Employee
{
    return Employee::create(array_merge([
        'shop_owner_id' => $owner->id,
        'name' => 'Payroll Employee',
        'job_title' => 'Clerk',
        'monthly_salary' => 3000,
        'salary_type' => 'monthly',
        'is_active' => true,
    ], $attributes));
}

test('payroll service computes monthly salary with absences adjustments payments and review flags', function () {
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'grace_minutes' => 10,
            'overtime_after_minutes' => 480,
            'overtime_multiplier' => 1.25,
            'default_schedule' => [
                0 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                1 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                2 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                3 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                4 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                5 => ['start' => '09:00', 'end' => '17:00', 'off' => true],
                6 => ['start' => '09:00', 'end' => '17:00', 'off' => true],
            ],
        ],
    ]);
    $employee = makePayrollEmployee($owner, ['monthly_salary' => 3100]);

    foreach (['2026-10-01', '2026-10-02', '2026-10-05'] as $date) {
        AttendanceRecord::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'employee_id' => $employee->id,
            'work_date' => $date,
            'check_in_at' => now()->startOfDay(),
            'check_out_at' => now()->startOfDay()->addHours(8),
            'status' => $date === '2026-10-05' ? 'needs_review' : 'approved',
            'source' => 'portal',
            'minutes_worked' => 480,
        ]);
    }

    EmployeeLeave::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'date_from' => '2026-10-06',
        'date_to' => '2026-10-06',
        'type' => 'paid',
    ]);
    EmployeeAdjustment::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'period' => '2026-10',
        'kind' => 'bonus',
        'amount' => 200,
    ]);
    EmployeePayment::create([
        'employee_id' => $employee->id,
        'amount' => 500,
        'payment_date' => '2026-10-10',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => '2026-10',
    ]);

    $summary = app(PayrollService::class)->compute($employee->load('shopOwner'), '2026-10');

    expect($summary['present_days'])->toBe(2)
        ->and($summary['paid_leave_days'])->toBe(1)
        ->and($summary['flagged'])->toBeTrue()
        ->and($summary['pending_approval_minutes'])->toBe(480)
        ->and($summary['pending_approval_amount'])->toBeGreaterThan(0)
        ->and($summary['already_paid'])->toBe(500.0)
        ->and($summary['adjustments_total'])->toBe(200.0)
        ->and($summary['net_payable'])->toBeGreaterThan(0);
});

test('paid leave does not double pay attended days', function () {
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'default_schedule' => [
                0 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                1 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                2 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                3 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                4 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                5 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
                6 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
            ],
        ],
    ]);
    $employee = makePayrollEmployee($owner, [
        'salary_type' => 'daily',
        'monthly_salary' => 0,
        'daily_rate' => 100,
    ]);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-05',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(8),
        'status' => 'approved',
        'source' => 'portal',
        'minutes_worked' => 480,
    ]);
    EmployeeLeave::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'date_from' => '2026-10-05',
        'date_to' => '2026-10-05',
        'type' => 'paid',
    ]);

    $summary = app(PayrollService::class)->compute($employee->load('shopOwner'), '2026-10');

    expect($summary['present_days'])->toBe(1)
        ->and($summary['paid_leave_days'])->toBe(0)
        ->and($summary['gross'])->toBe(100.0);
});

test('unpaid leave reduces a monthly salary like an absence while paid leave does not', function () {
    $off = ['start' => '09:00', 'end' => '17:00', 'off' => true];
    $on = ['start' => '09:00', 'end' => '17:00', 'off' => false];
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'default_schedule' => [0 => $off, 1 => $on, 2 => $off, 3 => $off, 4 => $off, 5 => $off, 6 => $off],
        ],
    ]);

    // October 2026 has four scheduled Mondays: 5, 12, 19 and 26 (1000 per scheduled day).
    $unpaid = makePayrollEmployee($owner, ['name' => 'Unpaid Leave', 'monthly_salary' => 4000]);
    $paid = makePayrollEmployee($owner, ['name' => 'Paid Leave', 'monthly_salary' => 4000]);

    foreach ([$unpaid, $paid] as $employee) {
        foreach (['2026-10-05', '2026-10-12'] as $date) {
            AttendanceRecord::withoutGlobalScopes()->create([
                'user_id' => $owner->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'check_in_at' => now()->startOfDay(),
                'check_out_at' => now()->startOfDay()->addHours(8),
                'status' => 'approved',
                'source' => 'portal',
                'minutes_worked' => 480,
            ]);
        }
    }

    foreach ([[$unpaid, 'unpaid'], [$paid, 'sick']] as [$employee, $type]) {
        EmployeeLeave::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'employee_id' => $employee->id,
            'date_from' => '2026-10-19',
            'date_to' => '2026-10-19',
            'type' => $type,
        ]);
    }

    $unpaidSummary = app(PayrollService::class)->compute($unpaid->load('shopOwner'), '2026-10');
    $paidSummary = app(PayrollService::class)->compute($paid->load('shopOwner'), '2026-10');

    // 26 October has neither attendance nor leave, so it is a plain absence for both.
    expect($unpaidSummary['scheduled_days'])->toBe(4)
        ->and($unpaidSummary['unpaid_leave_days'])->toBe(1)
        ->and($unpaidSummary['absent_days'])->toBe(1)
        ->and($unpaidSummary['gross_base'])->toBe(2000.0)
        ->and($paidSummary['paid_leave_days'])->toBe(1)
        ->and($paidSummary['absent_days'])->toBe(1)
        ->and($paidSummary['gross_base'])->toBe(3000.0);
});

test('payroll service computes exact hourly totals while excluding pending approval time', function () {
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'overtime_after_minutes' => 480,
            'overtime_multiplier' => 1.5,
            'default_schedule' => collect(range(0, 6))->mapWithKeys(fn (int $day) => [$day => ['start' => '09:00', 'end' => '17:00', 'off' => false]])->all(),
        ],
    ]);
    $employee = makePayrollEmployee($owner, [
        'salary_type' => 'hourly',
        'monthly_salary' => 0,
        'hourly_rate' => 12,
    ]);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-05',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(10),
        'status' => 'approved',
        'source' => 'portal',
        'minutes_worked' => 600,
    ]);
    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-06',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(8),
        'status' => 'needs_review',
        'source' => 'offline',
        'minutes_worked' => 480,
    ]);

    $summary = app(PayrollService::class)->compute($employee->load('shopOwner'), '2026-10');

    expect($summary['present_days'])->toBe(1)
        ->and($summary['worked_minutes'])->toBe(600)
        ->and($summary['gross_base'])->toBe(120.0)
        ->and($summary['overtime_pay'])->toBe(12.0)
        ->and($summary['gross'])->toBe(132.0)
        ->and($summary['pending_approval_days'])->toBe(1)
        ->and($summary['pending_approval_minutes'])->toBe(480)
        ->and($summary['pending_approval_amount'])->toBe(96.0)
        ->and($summary['net_payable'])->toBe(132.0)
        ->and($summary['status'])->toBe('pending_approval');
});

test('paid leave on off days does not add payable salary', function () {
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'default_schedule' => collect(range(0, 6))->mapWithKeys(fn (int $day) => [$day => ['start' => '09:00', 'end' => '17:00', 'off' => true]])->all(),
        ],
    ]);
    $employee = makePayrollEmployee($owner, [
        'salary_type' => 'daily',
        'monthly_salary' => 0,
        'daily_rate' => 100,
    ]);

    EmployeeLeave::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'date_from' => '2026-10-07',
        'date_to' => '2026-10-07',
        'type' => 'paid',
    ]);

    $summary = app(PayrollService::class)->compute($employee->load('shopOwner'), '2026-10');

    expect($summary['scheduled_days'])->toBe(0)
        ->and($summary['paid_leave_days'])->toBe(0)
        ->and($summary['gross'])->toBe(0.0)
        ->and($summary['net_payable'])->toBe(0.0);
});

test('payroll service computes hourly and daily salary with overtime and legacy payments', function () {
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'overtime_after_minutes' => 480,
            'overtime_multiplier' => 1.5,
            'default_schedule' => [
                0 => ['start' => '09:00', 'end' => '17:00', 'off' => false],
            ],
        ],
    ]);
    $hourly = makePayrollEmployee($owner, [
        'name' => 'Hourly Worker',
        'salary_type' => 'hourly',
        'monthly_salary' => 0,
        'hourly_rate' => 20,
    ]);
    $daily = makePayrollEmployee($owner, [
        'name' => 'Daily Worker',
        'salary_type' => 'daily',
        'monthly_salary' => 0,
        'daily_rate' => 120,
    ]);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $hourly->id,
        'work_date' => '2026-10-05',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(10),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 600,
    ]);
    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $daily->id,
        'work_date' => '2026-10-05',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(9),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 540,
    ]);
    EmployeePayment::create([
        'employee_id' => $hourly->id,
        'amount' => 50,
        'payment_date' => '2026-10-12',
        'type' => 'cash',
        'kind' => 'advance',
        'period' => null,
    ]);

    $service = app(PayrollService::class);
    $hourlySummary = $service->compute($hourly->load('shopOwner'), '2026-10');
    $dailySummary = $service->compute($daily->load('shopOwner'), '2026-10');

    expect($hourlySummary['worked_minutes'])->toBe(600)
        ->and($hourlySummary['overtime_minutes'])->toBe(120)
        ->and($hourlySummary['already_paid'])->toBe(50.0)
        ->and($hourlySummary['gross'])->toBeGreaterThan(200.0);

    expect($dailySummary['present_days'])->toBe(1)
        ->and($dailySummary['gross'])->toBeGreaterThanOrEqual(120.0);
});

test('payroll overtime uses configured scheduled minutes instead of actual paid minutes', function () {
    $owner = $this->makeOwner([
        'attendance_settings' => [
            'overtime_after_minutes' => 480,
            'overtime_multiplier' => 1.25,
            'default_schedule' => collect(range(0, 6))->mapWithKeys(fn (int $day) => [$day => ['start' => '09:00', 'end' => '17:00', 'off' => false]])->all(),
        ],
    ]);

    $monthlyFull = makePayrollEmployee($owner, [
        'name' => 'Monthly Full',
        'monthly_salary' => 3100,
    ]);
    $monthlyAbsent = makePayrollEmployee($owner, [
        'name' => 'Monthly Absent',
        'monthly_salary' => 3100,
    ]);
    $monthlyPartial = makePayrollEmployee($owner, [
        'name' => 'Monthly Partial',
        'monthly_salary' => 3100,
        'hire_date' => '2026-10-16',
    ]);
    $hourly = makePayrollEmployee($owner, [
        'name' => 'Hourly Exact',
        'salary_type' => 'hourly',
        'monthly_salary' => 0,
        'hourly_rate' => 20,
    ]);
    $daily = makePayrollEmployee($owner, [
        'name' => 'Daily Exact',
        'salary_type' => 'daily',
        'monthly_salary' => 0,
        'daily_rate' => 120,
    ]);

    foreach (range(1, 31) as $day) {
        $date = sprintf('2026-10-%02d', $day);
        $minutes = $date === '2026-10-05' ? 600 : 480;

        AttendanceRecord::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'employee_id' => $monthlyFull->id,
            'work_date' => $date,
            'check_in_at' => \Carbon\Carbon::parse($date . ' 09:00:00'),
            'check_out_at' => \Carbon\Carbon::parse($date . ' 09:00:00')->addMinutes($minutes),
            'status' => 'approved',
            'source' => 'manual',
            'minutes_worked' => $minutes,
        ]);
    }

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $monthlyAbsent->id,
        'work_date' => '2026-10-05',
        'check_in_at' => \Carbon\Carbon::parse('2026-10-05 09:00:00'),
        'check_out_at' => \Carbon\Carbon::parse('2026-10-05 19:00:00'),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 600,
    ]);
    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $monthlyPartial->id,
        'work_date' => '2026-10-20',
        'check_in_at' => \Carbon\Carbon::parse('2026-10-20 09:00:00'),
        'check_out_at' => \Carbon\Carbon::parse('2026-10-20 19:00:00'),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 600,
    ]);
    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $hourly->id,
        'work_date' => '2026-10-05',
        'check_in_at' => \Carbon\Carbon::parse('2026-10-05 09:00:00'),
        'check_out_at' => \Carbon\Carbon::parse('2026-10-05 19:00:00'),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 600,
    ]);
    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $daily->id,
        'work_date' => '2026-10-05',
        'check_in_at' => \Carbon\Carbon::parse('2026-10-05 09:00:00'),
        'check_out_at' => \Carbon\Carbon::parse('2026-10-05 19:00:00'),
        'status' => 'approved',
        'source' => 'manual',
        'minutes_worked' => 600,
    ]);

    $service = app(PayrollService::class);

    expect($service->compute($monthlyFull->load('shopOwner'), '2026-10')['overtime_pay'])->toBe(6.25)
        ->and($service->compute($monthlyAbsent->load('shopOwner'), '2026-10')['overtime_pay'])->toBe(6.25)
        ->and($service->compute($monthlyPartial->load('shopOwner'), '2026-10')['overtime_pay'])->toBe(6.25)
        ->and($service->compute($hourly->load('shopOwner'), '2026-10')['overtime_pay'])->toBe(10.0)
        ->and($service->compute($daily->load('shopOwner'), '2026-10')['overtime_pay'])->toBe(7.5);
});

test('open past records stay pending approval instead of counting as payable presence', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-01 09:00:00'));

    $owner = $this->makeOwner([
        'attendance_settings' => [
            'overtime_after_minutes' => 480,
            'overtime_multiplier' => 1.25,
            'default_schedule' => collect(range(0, 6))->mapWithKeys(fn (int $day) => [$day => ['start' => '09:00', 'end' => '17:00', 'off' => false]])->all(),
        ],
    ]);

    $daily = makePayrollEmployee($owner, [
        'name' => 'Daily Pending',
        'salary_type' => 'daily',
        'monthly_salary' => 0,
        'daily_rate' => 120,
        'hire_date' => '2026-10-31',
    ]);
    $monthly = makePayrollEmployee($owner, [
        'name' => 'Monthly Pending',
        'monthly_salary' => 3100,
        'hire_date' => '2026-10-31',
    ]);

    foreach ([$daily, $monthly] as $employee) {
        AttendanceRecord::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-31',
            'check_in_at' => \Carbon\Carbon::parse('2026-10-31 09:00:00'),
            'status' => 'open',
            'source' => 'portal',
            'minutes_worked' => null,
        ]);
    }

    $service = app(PayrollService::class);
    $dailySummary = $service->compute($daily->load('shopOwner'), '2026-10');
    $monthlySummary = $service->compute($monthly->load('shopOwner'), '2026-10');

    expect($dailySummary['present_days'])->toBe(0)
        ->and($dailySummary['pending_approval_days'])->toBe(1)
        ->and($dailySummary['pending_approval_amount'])->toBe(120.0)
        ->and($dailySummary['gross'])->toBe(0.0)
        ->and($dailySummary['status'])->toBe('pending_approval');

    expect($monthlySummary['present_days'])->toBe(0)
        ->and($monthlySummary['absent_days'])->toBe(1)
        ->and($monthlySummary['pending_approval_days'])->toBe(1)
        ->and($monthlySummary['pending_approval_amount'])->toBe(3100.0)
        ->and($monthlySummary['gross'])->toBe(0.0)
        ->and($monthlySummary['status'])->toBe('pending_approval');
});

test('payroll page pay endpoint and payslip work', function () {
    $owner = $this->makeOwner();
    $employee = makePayrollEmployee($owner);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-10',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(8),
        'status' => 'approved',
        'source' => 'portal',
        'minutes_worked' => 480,
    ]);

    $this->actingAs($owner)->get(route('shopowner.payroll.index', ['period' => '2026-10']))->assertOk();
    $token = session('hr-owner.payment_tokens.' . $employee->id);
    $idempotencyKey = array_key_first($token);

    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), [
        'period' => '2026-10',
        'amount' => 99999,
        'payment_date' => '2026-10-15',
        'type' => 'cash',
        'note' => 'Too much',
        'idempotency_key' => $idempotencyKey,
    ])->assertSessionHasErrors('amount');

    $this->actingAs($owner)->get(route('shopowner.payroll.index', ['period' => '2026-10']))->assertOk();
    $token = session('hr-owner.payment_tokens.' . $employee->id);
    $idempotencyKey = array_key_first($token);
    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), [
        'period' => '2026-10',
        'amount' => 100,
        'payment_date' => '2026-10-15',
        'type' => 'cash',
        'note' => 'Partial salary',
        'idempotency_key' => $idempotencyKey,
    ])->assertRedirect();

    expect(EmployeePayment::where('employee_id', $employee->id)->where('kind', 'salary')->exists())->toBeTrue();
    $this->actingAs($owner)->get(route('shopowner.payroll.payslip', ['employee' => $employee, 'period' => '2026-10']))->assertOk();
});

test('payroll payment is blocked for pending approval minutes and invalid periods', function () {
    $owner = $this->makeOwner();
    $employee = makePayrollEmployee($owner, [
        'salary_type' => 'hourly',
        'monthly_salary' => 0,
        'hourly_rate' => 20,
    ]);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-10',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(8),
        'status' => 'needs_review',
        'source' => 'offline',
        'minutes_worked' => 480,
    ]);

    $this->actingAs($owner)->get(route('shopowner.payroll.index', ['period' => '2026-10']))->assertOk();
    $token = session('hr-owner.payment_tokens.' . $employee->id);
    $idempotencyKey = array_key_first($token);

    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), [
        'period' => '2026-10',
        'amount' => 10,
        'payment_date' => '2026-10-15',
        'type' => 'cash',
        'note' => 'Blocked',
        'idempotency_key' => $idempotencyKey,
    ])->assertSessionHasErrors('amount');

    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), [
        'period' => '2026-99',
        'amount' => 10,
        'payment_date' => '2026-10-15',
        'type' => 'cash',
        'note' => 'Bad period',
        'idempotency_key' => 'invalid-period-token',
    ])->assertSessionHasErrors('period');
});

test('payroll payment and adjustments reject values beyond database precision', function () {
    $owner = $this->makeOwner();
    $employee = makePayrollEmployee($owner);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-10',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(8),
        'status' => 'approved',
        'source' => 'portal',
        'minutes_worked' => 480,
    ]);

    $this->actingAs($owner)->get(route('shopowner.payroll.index', ['period' => '2026-10']))->assertOk();
    $idempotencyKey = array_key_first(session('hr-owner.payment_tokens.' . $employee->id));

    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), [
        'period' => '2026-10',
        'amount' => '100000000.00',
        'payment_date' => '2026-10-15',
        'type' => 'cash',
        'note' => 'Too large',
        'allow_overpay' => '1',
        'idempotency_key' => $idempotencyKey,
    ])->assertSessionHasErrors('amount');

    $this->actingAs($owner)->post(route('shopowner.payroll.adjustments.store', $employee), [
        'period' => '2026-10',
        'kind' => 'bonus',
        'amount' => '10000000000.00',
        'note' => 'Too large',
    ])->assertSessionHasErrors('amount');
});

test('payroll pay endpoint rejects replayed idempotency keys even if the session token is restored', function () {
    $owner = $this->makeOwner();
    $employee = makePayrollEmployee($owner);

    AttendanceRecord::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'employee_id' => $employee->id,
        'work_date' => '2026-10-10',
        'check_in_at' => now()->startOfDay(),
        'check_out_at' => now()->startOfDay()->addHours(8),
        'status' => 'approved',
        'source' => 'portal',
        'minutes_worked' => 480,
    ]);

    $this->actingAs($owner)->get(route('shopowner.payroll.index', ['period' => '2026-10']))->assertOk();
    $token = array_key_first(session('hr-owner.payment_tokens.' . $employee->id));

    $payload = [
        'period' => '2026-10',
        'amount' => 100,
        'payment_date' => '2026-10-15',
        'type' => 'cash',
        'note' => 'Salary run',
        'idempotency_key' => $token,
    ];

    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), $payload)->assertRedirect();

    session(['hr-owner.payment_tokens.' . $employee->id => [$token => true]]);

    $this->actingAs($owner)->post(route('shopowner.payroll.pay', $employee), $payload)
        ->assertSessionHasErrors('amount');

    expect(EmployeePayment::where('employee_id', $employee->id)->where('kind', 'salary')->count())->toBe(1)
        ->and(IdempotencyKey::query()
            ->where('user_id', $owner->id)
            ->where('request_key', $token)
            ->where('path', parse_url(route('shopowner.payroll.pay', $employee), PHP_URL_PATH))
            ->exists())->toBeTrue();
});

test('admin adjustments leaves and manual attendance use employee owner tenant and csv export is sanitized', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();
    $employee = makePayrollEmployee($owner, ['name' => '=Danger']);

    $this->actingAs($admin)->post(route('shopowner.payroll.adjustments.store', $employee), [
        'period' => '2026-10',
        'kind' => 'bonus',
        'amount' => 10,
        'note' => 'ok',
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('shopowner.payroll.leaves.store', $employee), [
        'date_from' => '2026-10-10',
        'date_to' => '2026-10-10',
        'type' => 'paid',
        'note' => 'ok',
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('shopowner.attendance.store'), [
        'employee_id' => $employee->id,
        'check_in_local' => '2026-10-10T09:00',
        'check_out_local' => '2026-10-10T17:00',
        'status' => 'approved',
        'reason' => '=cmd',
    ])->assertRedirect();

    expect(EmployeeAdjustment::withoutGlobalScopes()->latest('id')->first()->user_id)->toBe($owner->id)
        ->and(EmployeeLeave::withoutGlobalScopes()->latest('id')->first()->user_id)->toBe($owner->id)
        ->and(AttendanceRecord::withoutGlobalScopes()->latest('id')->first()->user_id)->toBe($owner->id)
        ->and(AttendanceRecord::withoutGlobalScopes()->latest('id')->first()->work_date->toDateString())->toBe('2026-10-10');

    $payrollCsv = $this->actingAs($owner)->get(route('shopowner.payroll.export', ['period' => '2026-10']))->streamedContent();
    $attendanceCsv = $this->actingAs($owner)->get(route('shopowner.attendance.export', ['from' => '2026-10-10', 'to' => '2026-10-10']))->streamedContent();

    expect(str_contains($payrollCsv, (string) CsvSanitizer::cell('=Danger')))->toBeTrue();
    expect($attendanceCsv)->toContain((string) CsvSanitizer::cell('=Danger'));
    expect(str_contains($attendanceCsv, (string) CsvSanitizer::cell('=cmd')))->toBeTrue();
});
