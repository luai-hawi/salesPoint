<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopOwner\Concerns\ResolvesHrOwner;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeDevice;
use App\Models\EmployeePayment;
use App\Services\ActivityLogger;
use App\Services\Payroll\PayrollService;
use App\Support\HrSettings;
use App\Support\PayrollPeriod;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    use ResolvesHrOwner;

    private const EMPLOYEE_PAYMENT_AMOUNT_MAX = 99999999.99;

    private const MONTHLY_SALARY_MAX = 99999999.99;

    private const RATE_AMOUNT_MAX = 9999999999.99;

    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request)
    {
        $owner = $this->hrOwner();
        $ownerId = (int) $owner->id;
        $today = ShopTime::today($owner);
        $monthStart = ShopTime::local(now(), $owner)->startOfMonth()->toDateString();
        $monthEnd = ShopTime::local(now(), $owner)->endOfMonth()->toDateString();

        $employees = Employee::query()
            ->where('shop_owner_id', $ownerId)
            ->when($request->string('search')->trim()->value(), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', '%' . $search . '%')
                        ->orWhere('job_title', 'like', '%' . $search . '%')
                        ->orWhere('username', 'like', '%' . Str::lower($search) . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            })
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $employeeIds = $employees->pluck('id');

        $monthMinutes = AttendanceRecord::withoutGlobalScopes()
            ->select('employee_id', DB::raw('COALESCE(SUM(minutes_worked), 0) as total_minutes'))
            ->where('user_id', $ownerId)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->whereIn('status', ['approved', 'closed', 'needs_review'])
            ->groupBy('employee_id')
            ->pluck('total_minutes', 'employee_id');

        $todayRecords = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', $today)
            ->orderByDesc('check_in_at')
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $records) => $records->sortByDesc(fn ($record) => $record->check_out_at ?? $record->check_in_at)->first());

        $employees->getCollection()->transform(function (Employee $employee) use ($monthMinutes, $todayRecords, $owner) {
            $record = $todayRecords->get($employee->id);
            $employee->setAttribute('hours_this_month', round(((int) ($monthMinutes[$employee->id] ?? 0)) / 60, 2));
            $employee->setAttribute('today_status', $this->todayStatus($record, $owner));

            return $employee;
        });

        return view('shopowner.employees.index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'active']),
            'portalUrl' => HrSettings::portalUrl($owner),
        ]);
    }

    public function create()
    {
        $owner = HrSettings::ensurePortalKey($this->hrOwner());

        return view('shopowner.employees.create', [
            'employee' => new Employee([
                'salary_type' => 'monthly',
                'is_active' => true,
                'portal_enabled' => false,
            ]),
            'scheduleTemplate' => HrSettings::scheduleTemplate(),
            'portalUrl' => HrSettings::portalUrl($owner),
            'shopSettings' => HrSettings::normalize($owner->attendance_settings),
            'paymentTypes' => $this->paymentTypes(),
            'paymentKinds' => $this->paymentKinds(),
        ]);
    }

    public function store(Request $request)
    {
        $owner = $this->hrOwner();
        $ownerId = (int) $owner->id;
        $validated = $this->validateEmployee($request, $ownerId);
        $payload = $this->employeePayload($validated);
        $payload['shop_owner_id'] = $ownerId;

        $employee = Employee::create($payload);

        ActivityLogger::record('employee.created', Employee::class, $employee, [
            'name' => $employee->name,
            'salary_type' => $employee->salary_type,
        ], label: $employee->name, ownerId: $ownerId);

        return redirect()->route('shopowner.employees.edit', $employee)->with('success', __('hr_owner.employee_created'));
    }

    public function edit(Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $owner = HrSettings::ensurePortalKey($this->ownerForEmployee($employee->loadMissing('shopOwner')));
        $period = $this->resolvedPeriod(null, $owner);
        $summary = $this->payrollService->compute($employee, $period);

        return view('shopowner.employees.edit', [
            'employee' => $employee->load([
                'devices' => fn ($query) => $query->latest('last_used_at'),
                'credentials' => fn ($query) => $query->latest('last_used_at'),
            ]),
            'payrollSummary' => $summary,
            'scheduleTemplate' => HrSettings::scheduleTemplate(),
            'portalUrl' => HrSettings::portalUrl($owner),
            'shopSettings' => HrSettings::normalize($owner->attendance_settings),
            'paymentTypes' => $this->paymentTypes(),
            'paymentKinds' => $this->paymentKinds(),
            'paymentIdempotencyKey' => $this->issuePaymentIdempotencyKey($employee),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $validated = $this->validateEmployee($request, (int) $employee->shop_owner_id, $employee);
        $payload = $this->employeePayload($validated, $employee);
        $devicesRevoked = false;

        $employee = DB::transaction(function () use ($employee, $payload, &$devicesRevoked) {
            $lockedEmployee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $devicesRevoked = $this->employeeChangesRequireDeviceRevocation($lockedEmployee, $payload);

            $lockedEmployee->fill($payload)->save();

            if ($devicesRevoked) {
                $lockedEmployee->devices()
                    ->whereNull('revoked_at')
                    ->get()
                    ->each
                    ->revoke();
            }

            return $lockedEmployee->fresh();
        });

        ActivityLogger::record('employee.updated', Employee::class, $employee, [
            'name' => $employee->name,
            'salary_type' => $employee->salary_type,
        ], label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        return redirect()->route('shopowner.employees.edit', $employee)->with(
            'success',
            $devicesRevoked ? __('hr_owner.employee_updated_devices_revoked') : __('hr_owner.employee_updated')
        );
    }

    public function destroy(Employee $employee)
    {
        $this->authorizeEmployee($employee);

        ActivityLogger::record('employee.deleted', Employee::class, $employee, [
            'name' => $employee->name,
        ], label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        $employee->delete();

        return redirect()->route('shopowner.employees.index')->with('success', __('hr_owner.employee_deleted'));
    }

    public function payments(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $owner = $this->ownerForEmployee($employee->loadMissing('shopOwner'));
        $period = $this->resolvedPeriod($request->string('period')->trim()->value(), $owner);
        $summary = $this->payrollService->compute($employee, $period);

        $payments = $employee->payments()
            ->when($request->filled('from'), fn ($query) => $query->whereDate('payment_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('payment_date', '<=', $request->input('to')))
            ->latest('payment_date')
            ->paginate(25)
            ->withQueryString();

        return view('shopowner.employees.payments', [
            'employee' => $employee,
            'payments' => $payments,
            'period' => $period,
            'payrollSummary' => $summary,
            'paymentTypes' => $this->paymentTypes(),
            'paymentKinds' => $this->paymentKinds(),
            'paymentIdempotencyKey' => $this->issuePaymentIdempotencyKey($employee),
        ]);
    }

    public function storePayment(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $employee->loadMissing('shopOwner');
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . self::EMPLOYEE_PAYMENT_AMOUNT_MAX],
            'payment_date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys($this->paymentTypes()))],
            'kind' => ['required', Rule::in(array_keys($this->paymentKinds()))],
            'period' => ['nullable', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && ! PayrollPeriod::isValid($value)) {
                    $fail(__('hr_owner.invalid_period'));
                }
            }],
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $period = $validated['period'] ?: PayrollPeriod::normalize(Carbon::parse($validated['payment_date'])->format('Y-m'));
        $this->consumePaymentIdempotencyKey($employee, $validated['idempotency_key']);

        $amount = round((float) $validated['amount'], 2);
        $payment = DB::transaction(function () use ($employee, $validated, $period, $amount, $request) {
            $this->claimPaymentIdempotency($request, $validated['idempotency_key']);
            $lockedEmployee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail()->loadMissing('shopOwner');
            EmployeePayment::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where(function ($query) use ($period) {
                    $query->where('period', $period)
                        ->orWhereNull('period');
                })
                ->lockForUpdate()
                ->get();

            $summary = $this->payrollService->compute($lockedEmployee, $period);

            if (($summary['pending_approval_minutes'] > 0 || $summary['pending_approval_days'] > 0) && $amount > (float) $summary['net_payable'] + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => __('hr_owner.pending_approval_payment_blocked'),
                ]);
            }

            if ($amount > ((float) $summary['net_payable'] + 0.01) && ! $request->boolean('allow_overpay')) {
                throw ValidationException::withMessages([
                    'amount' => __('hr_owner.payment_overpay_warning'),
                ]);
            }

            return EmployeePayment::create([
                'employee_id' => $lockedEmployee->id,
                'amount' => $amount,
                'payment_date' => $validated['payment_date'],
                'type' => $validated['type'],
                'kind' => $validated['kind'],
                'period' => $period,
                'note' => $validated['note'] ?? null,
            ]);
        });

        ActivityLogger::record('employee_payment.created', EmployeePayment::class, $payment, [
            'employee_id' => $employee->id,
            'kind' => $payment->kind,
            'period' => $payment->period,
        ], amount: $amount, label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        return redirect()->route('shopowner.employees.payments', $employee)->with('success', __('hr_owner.payment_recorded'));
    }

    public function destroyPayment(EmployeePayment $payment)
    {
        $this->authorizePayment($payment);
        $payment->loadMissing('employee');

        ActivityLogger::record('employee_payment.deleted', EmployeePayment::class, $payment, [
            'employee_id' => $payment->employee_id,
            'kind' => $payment->kind,
            'period' => $payment->period,
        ], amount: (float) $payment->amount, label: $payment->employee->name, ownerId: (int) $payment->employee->shop_owner_id);

        $payment->delete();

        return back()->with('success', __('hr_owner.payment_deleted'));
    }

    public function revokeDevice(Employee $employee, EmployeeDevice $device)
    {
        $this->authorizeEmployee($employee);
        $this->authorizeDevice($device);
        abort_unless((int) $device->employee_id === (int) $employee->id, 404);

        $device->revoke();

        \App\Services\ActivityLogger::record('revoked', 'employee_device', $device, [
            'employee_id' => $employee->id,
            'label' => $device->label,
        ], label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        return back()->with('success', __('hr_owner.device_revoked'));
    }

    public function destroyCredential(Employee $employee, EmployeeCredential $credential)
    {
        $this->authorizeEmployee($employee);
        $this->authorizeCredential($credential);
        abort_unless((int) $credential->employee_id === (int) $employee->id, 404);

        \App\Services\ActivityLogger::record('deleted', 'employee_credential', $credential, [
            'employee_id' => $employee->id,
            'label' => $credential->label,
        ], label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        $credential->delete();

        return back()->with('success', __('hr_owner.credential_removed'));
    }

    protected function validateEmployee(Request $request, int $ownerId, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'hire_date' => ['nullable', 'date'],
            'staff_notes' => ['nullable', 'string', 'max:5000'],
            'salary_type' => ['required', Rule::in(['monthly', 'hourly', 'daily'])],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:' . self::MONTHLY_SALARY_MAX],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:' . self::RATE_AMOUNT_MAX],
            'daily_rate' => ['nullable', 'numeric', 'min:0', 'max:' . self::RATE_AMOUNT_MAX],
            'schedule_mode' => ['nullable', Rule::in(['default', 'custom'])],
            'schedule' => ['nullable', 'array'],
            'portal_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('employees', 'username')
                    ->where(fn ($query) => $query->where('shop_owner_id', $ownerId))
                    ->ignore($employee?->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'biometric_required' => ['nullable', Rule::in(['', '0', '1'])],
        ], [
            'username.regex' => __('hr_owner.username_format_help'),
        ]);

        $portalEnabled = $request->boolean('portal_enabled');
        $username = Str::lower((string) ($data['username'] ?? ''));

        if ($portalEnabled && $username === '') {
            throw ValidationException::withMessages([
                'username' => __('hr_owner.username_required_for_portal'),
            ]);
        }

        if ($portalEnabled && $employee === null && blank($data['password'] ?? null)) {
            throw ValidationException::withMessages([
                'password' => __('hr_owner.password_required_for_portal'),
            ]);
        }

        $data['portal_enabled'] = $portalEnabled;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['username'] = $username ?: null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function employeePayload(array $validated, ?Employee $employee = null): array
    {
        $payload = [
            'name' => $validated['name'],
            'job_title' => $validated['job_title'],
            'phone' => ($validated['phone'] ?? null) ?: null,
            'hire_date' => ($validated['hire_date'] ?? null) ?: null,
            'staff_notes' => ($validated['staff_notes'] ?? null) ?: null,
            'salary_type' => $validated['salary_type'],
            'monthly_salary' => $validated['salary_type'] === 'monthly' ? (float) ($validated['monthly_salary'] ?? 0) : (float) ($validated['monthly_salary'] ?? 0),
            'hourly_rate' => $validated['salary_type'] === 'hourly' ? (float) ($validated['hourly_rate'] ?? 0) : (($validated['hourly_rate'] ?? null) !== null ? (float) $validated['hourly_rate'] : null),
            'daily_rate' => $validated['salary_type'] === 'daily' ? (float) ($validated['daily_rate'] ?? 0) : (($validated['daily_rate'] ?? null) !== null ? (float) $validated['daily_rate'] : null),
            'schedule' => ($validated['schedule_mode'] ?? 'default') === 'custom' ? HrSettings::normalizeSchedule($validated['schedule'] ?? []) : null,
            'portal_enabled' => (bool) $validated['portal_enabled'],
            'is_active' => (bool) $validated['is_active'],
            'username' => $validated['portal_enabled'] ? $validated['username'] : null,
            'biometric_required' => ($validated['biometric_required'] ?? '') === '' ? null : (bool) $validated['biometric_required'],
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
        } elseif (($validated['portal_enabled'] ?? false) === false && $employee === null) {
            $payload['password'] = null;
        }

        if (! $payload['portal_enabled']) {
            $payload['username'] = null;
            $payload['biometric_required'] = null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function employeeChangesRequireDeviceRevocation(Employee $employee, array $payload): bool
    {
        if (array_key_exists('password', $payload) && filled($payload['password'] ?? null)) {
            return true;
        }

        return $employee->portal_enabled !== (bool) $payload['portal_enabled']
            || $employee->is_active !== (bool) $payload['is_active'];
    }

    /**
     * @return array<string, string>
     */
    protected function paymentTypes(): array
    {
        return [
            'cash' => __('hr_owner.payment_type_cash'),
            'card' => __('hr_owner.payment_type_card'),
            'transfer' => __('hr_owner.payment_type_transfer'),
            'check' => __('hr_owner.payment_type_check'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function paymentKinds(): array
    {
        return [
            'salary' => __('hr_owner.payment_kind_salary'),
            'advance' => __('hr_owner.payment_kind_advance'),
            'bonus' => __('hr_owner.payment_kind_bonus'),
            'other' => __('hr_owner.payment_kind_other'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function todayStatus(?AttendanceRecord $record, $owner): array
    {
        if ($record === null) {
            return ['tone' => 'gray', 'label' => __('hr_owner.attendance_absent'), 'detail' => null];
        }

        if ($record->status === 'needs_review' || $record->check_out_remote) {
            $distance = $record->check_out_distance_m ?? $record->check_in_distance_m;

            return [
                'tone' => 'amber',
                'label' => __('hr_owner.attendance_flagged'),
                'detail' => $distance ? __('hr_owner.flagged_distance', ['distance' => $this->formatDistance($distance)]) : null,
            ];
        }

        if ($record->check_out_at) {
            return [
                'tone' => 'blue',
                'label' => __('hr_owner.attendance_left'),
                'detail' => ShopTime::local($record->check_out_at, $owner)->format('H:i'),
            ];
        }

        return [
            'tone' => 'green',
            'label' => __('hr_owner.attendance_in'),
            'detail' => $record->check_in_at ? ShopTime::local($record->check_in_at, $owner)->format('H:i') : null,
        ];
    }

    protected function formatDistance(int $meters): string
    {
        return $meters >= 1000
            ? __('hr_owner.distance_kilometers', ['value' => number_format($meters / 1000, 1)])
            : __('hr_owner.distance_meters', ['value' => number_format($meters)]);
    }

    protected function resolvedPeriod(?string $period, $owner): string
    {
        $candidate = $period ?: ShopTime::local(now(), $owner)->format('Y-m');

        if (! PayrollPeriod::isValid($candidate)) {
            throw ValidationException::withMessages([
                'period' => __('hr_owner.invalid_period'),
            ]);
        }

        return PayrollPeriod::normalize($candidate);
    }

    protected function paymentTokenSessionKey(Employee $employee): string
    {
        return 'hr-owner.employee-payment-tokens.' . $employee->id;
    }

    protected function issuePaymentIdempotencyKey(Employee $employee): string
    {
        $token = (string) Str::uuid();
        $tokens = session($this->paymentTokenSessionKey($employee), []);
        $tokens[$token] = true;
        session([$this->paymentTokenSessionKey($employee) => $tokens]);

        return $token;
    }

    protected function consumePaymentIdempotencyKey(Employee $employee, string $token): void
    {
        $tokens = session($this->paymentTokenSessionKey($employee), []);

        if (! isset($tokens[$token])) {
            throw ValidationException::withMessages([
                'amount' => __('hr_owner.payment_already_processed'),
            ]);
        }

        unset($tokens[$token]);
        session([$this->paymentTokenSessionKey($employee) => $tokens]);
    }
}
