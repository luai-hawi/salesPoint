<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopOwner\Concerns\ResolvesHrOwner;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Payroll\PayrollService;
use App\Support\CsvSanitizer;
use App\Support\PayrollPeriod;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    use ResolvesHrOwner;

    private const EMPLOYEE_PAYMENT_AMOUNT_MAX = 99999999.99;

    private const EMPLOYEE_ADJUSTMENT_AMOUNT_MAX = 9999999999.99;

    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request)
    {
        $owner = $this->hrOwner();
        $period = $this->resolvedPeriod($request->string('period')->trim()->value(), $owner);

        $employees = Employee::query()
            ->where('shop_owner_id', $owner->id)
            ->when($request->string('search')->trim()->value(), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', '%' . $search . '%')
                        ->orWhere('job_title', 'like', '%' . $search . '%');
                });
            })
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $summaries = $this->payrollService
            ->computeMany($employees->getCollection()->loadMissing('shopOwner'), $period, $owner);

        $employees->getCollection()->transform(function (Employee $employee) use ($summaries) {
            $employee->setAttribute('payroll_summary', $summaries->get($employee->id));

            return $employee;
        });

        return view('shopowner.payroll.index', [
            'employees' => $employees,
            'period' => $period,
            'filters' => $request->only(['search', 'active']),
            'leaveTypes' => $this->leaveTypes(),
            'paymentTypes' => $this->paymentTypes(),
            'paymentIdempotencyTokens' => $employees->getCollection()->mapWithKeys(fn (Employee $employee) => [$employee->id => $this->issuePaymentIdempotencyKey($employee)]),
            'totals' => [
                'gross' => round($summaries->sum('gross'), 2),
                'already_paid' => round($summaries->sum('already_paid'), 2),
                'net_payable' => round($summaries->sum('net_payable'), 2),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $owner = $this->hrOwner();
        $period = $this->resolvedPeriod($request->string('period')->trim()->value(), $owner);

        $employees = Employee::query()
            ->where('shop_owner_id', $owner->id)
            ->orderBy('name')
            ->get();

        $summaries = $this->payrollService->computeMany($employees->loadMissing('shopOwner'), $period, $owner);
        $fileName = 'payroll-' . $period . '.csv';

        return response()->streamDownload(function () use ($employees, $summaries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee', 'Job Title', 'Salary Type', 'Scheduled Days', 'Present Days', 'Absent Days', 'Worked Minutes', 'Overtime Minutes', 'Gross', 'Paid', 'Net Payable', 'Status']);

            foreach ($employees as $employee) {
                $summary = $summaries->get($employee->id);
                fputcsv($out, [
                    CsvSanitizer::cell($employee->name),
                    CsvSanitizer::cell($employee->job_title),
                    CsvSanitizer::cell($employee->salary_type),
                    $summary['scheduled_days'],
                    $summary['present_days'],
                    $summary['absent_days'],
                    $summary['worked_minutes'],
                    $summary['overtime_minutes'],
                    $summary['gross'],
                    $summary['already_paid'],
                    $summary['net_payable'],
                    $summary['status'],
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pay(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $employee->loadMissing('shopOwner');
        $validated = $request->validate([
            'period' => ['required', function ($attribute, $value, $fail) {
                if (! PayrollPeriod::isValid($value)) {
                    $fail(__('hr_owner.invalid_period'));
                }
            }],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . self::EMPLOYEE_PAYMENT_AMOUNT_MAX],
            'payment_date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys($this->paymentTypes()))],
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $period = PayrollPeriod::normalize($validated['period']);
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
                'note' => $validated['note'] ?: null,
                'kind' => 'salary',
                'period' => $period,
            ]);
        });

        ActivityLogger::record('employee_salary.paid', EmployeePayment::class, $payment, [
            'employee_id' => $employee->id,
            'period' => $period,
        ], amount: $amount, label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        return back()->with('success', __('hr_owner.salary_paid'));
    }

    public function storeAdjustment(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $validated = $request->validate([
            'period' => ['required', function ($attribute, $value, $fail) {
                if (! PayrollPeriod::isValid($value)) {
                    $fail(__('hr_owner.invalid_period'));
                }
            }],
            'kind' => ['required', Rule::in(['bonus', 'deduction', 'penalty'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . self::EMPLOYEE_ADJUSTMENT_AMOUNT_MAX],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $period = PayrollPeriod::normalize($validated['period']);

        $adjustment = EmployeeAdjustment::create([
            'user_id' => $this->ownerIdForEmployee($employee),
            'employee_id' => $employee->id,
            'period' => $period,
            'kind' => $validated['kind'],
            'amount' => round((float) $validated['amount'], 2),
            'note' => $validated['note'] ?: null,
            'created_by' => $this->hrActor()->id,
        ]);

        ActivityLogger::record('employee_adjustment.created', EmployeeAdjustment::class, $adjustment, [
            'employee_id' => $employee->id,
            'period' => $period,
            'kind' => $validated['kind'],
        ], amount: (float) $adjustment->amount, label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        return back()->with('success', __('hr_owner.adjustment_saved'));
    }

    public function destroyAdjustment(EmployeeAdjustment $adjustment)
    {
        $this->authorizeAdjustment($adjustment);
        $adjustment->loadMissing('employee');

        ActivityLogger::record('employee_adjustment.deleted', EmployeeAdjustment::class, $adjustment, [
            'employee_id' => $adjustment->employee_id,
            'period' => $adjustment->period,
            'kind' => $adjustment->kind,
        ], amount: (float) $adjustment->amount, label: $adjustment->employee->name, ownerId: $this->ownerIdForAdjustment($adjustment));

        $adjustment->delete();

        return back()->with('success', __('hr_owner.adjustment_deleted'));
    }

    public function storeLeave(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'type' => ['required', Rule::in(array_keys($this->leaveTypes()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave = EmployeeLeave::create([
            'user_id' => $this->ownerIdForEmployee($employee),
            'employee_id' => $employee->id,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'type' => $validated['type'],
            'note' => $validated['note'] ?: null,
            'created_by' => $this->hrActor()->id,
        ]);

        ActivityLogger::record('employee_leave.created', EmployeeLeave::class, $leave, [
            'employee_id' => $employee->id,
            'type' => $leave->type,
        ], label: $employee->name, ownerId: (int) $employee->shop_owner_id);

        return back()->with('success', __('hr_owner.leave_saved'));
    }

    public function destroyLeave(EmployeeLeave $leave)
    {
        $this->authorizeLeave($leave);
        $leave->loadMissing('employee');

        ActivityLogger::record('employee_leave.deleted', EmployeeLeave::class, $leave, [
            'employee_id' => $leave->employee_id,
            'type' => $leave->type,
        ], label: $leave->employee->name, ownerId: $this->ownerIdForLeave($leave));

        $leave->delete();

        return back()->with('success', __('hr_owner.leave_deleted'));
    }

    public function payslip(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $owner = $this->ownerForEmployee($employee->loadMissing('shopOwner'));
        $period = $this->resolvedPeriod($request->string('period')->trim()->value(), $owner);
        $summary = $this->payrollService->compute($employee, $period);

        return view('shopowner.payroll.payslip', [
            'employee' => $employee,
            'period' => $period,
            'summary' => $summary,
            'owner' => $this->hrOwner(),
        ]);
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
    protected function leaveTypes(): array
    {
        return [
            'paid' => __('hr_owner.leave_paid'),
            'unpaid' => __('hr_owner.leave_unpaid'),
            'sick' => __('hr_owner.leave_sick'),
            'vacation' => __('hr_owner.leave_vacation'),
        ];
    }

    protected function resolvedPeriod(?string $period, Employee|User $owner): string
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
        return 'hr-owner.payment_tokens.' . $employee->id;
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
