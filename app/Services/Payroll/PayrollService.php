<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayment;
use App\Models\User;
use App\Support\PayrollPeriod;
use App\Support\HrSettings;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollService
{
    /**
     * @return array<string, mixed>
     */
    public function compute(Employee $employee, string $period): array
    {
        return $this->computeMany(collect([$employee]), PayrollPeriod::normalize($period), $employee->shopOwner)->get($employee->id);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, array<string, mixed>>
     */
    public function computeMany(Collection $employees, string $period, ?User $owner = null): Collection
    {
        $period = PayrollPeriod::normalize($period);
        $employees = $employees->values();

        if ($employees->isEmpty()) {
            return collect();
        }

        $owner ??= $employees->first()->shopOwner ?? User::withoutGlobalScopes()->findOrFail((int) $employees->first()->shop_owner_id);
        $ownerId = (int) $owner->id;
        $employeeIds = $employees->pluck('id')->all();
        $settings = HrSettings::normalize($owner->attendance_settings);
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $todayLocal = ShopTime::today($owner);

        $records = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->orderBy('work_date')
            ->orderBy('check_in_at')
            ->get()
            ->groupBy('employee_id');

        $adjustments = EmployeeAdjustment::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('employee_id', $employeeIds)
            ->where('period', $period)
            ->orderBy('created_at')
            ->get()
            ->groupBy('employee_id');

        $leaves = EmployeeLeave::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date_from', '<=', $end->toDateString())
            ->whereDate('date_to', '>=', $start->toDateString())
            ->orderBy('date_from')
            ->get()
            ->groupBy('employee_id');

        $payments = EmployeePayment::query()
            ->whereIn('employee_id', $employeeIds)
            ->where(function ($query) use ($period, $start, $end) {
                $query->where('period', $period)
                    ->orWhere(function ($legacy) use ($start, $end) {
                        $legacy->whereNull('period')->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);
                    });
            })
            ->orderBy('payment_date')
            ->get()
            ->groupBy('employee_id');

        return $employees->mapWithKeys(function (Employee $employee) use ($settings, $records, $adjustments, $leaves, $payments, $period, $owner, $start, $end, $todayLocal) {
            $summary = $this->computeEmployee(
                $employee,
                $period,
                $owner,
                $settings,
                $records->get($employee->id, collect()),
                $adjustments->get($employee->id, collect()),
                $leaves->get($employee->id, collect()),
                $payments->get($employee->id, collect()),
                $start,
                $end,
                $todayLocal,
            );

            return [$employee->id => $summary];
        });
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  Collection<int, EmployeeAdjustment>  $adjustments
     * @param  Collection<int, EmployeeLeave>  $leaves
     * @param  Collection<int, EmployeePayment>  $payments
     * @return array<string, mixed>
     */
    protected function computeEmployee(
        Employee $employee,
        string $period,
        User $owner,
        array $settings,
        Collection $records,
        Collection $adjustments,
        Collection $leaves,
        Collection $payments,
        Carbon $start,
        Carbon $end,
        string $todayLocal,
    ): array {
        $schedule = $this->resolvedSchedule($employee, $settings);
        $schedulePresent = $schedule !== null;
        $workDates = $this->periodDates($start, $end, $employee->hire_date?->toDateString());
        $recordsByDate = $records->groupBy(fn (AttendanceRecord $record) => $record->work_date->toDateString());
        $leaveMap = $this->expandLeaves($leaves);

        $days = [];
        $scheduledDays = 0;
        $presentDays = 0;
        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;
        $pendingApprovalDays = 0;
        $lateMinutes = 0;
        $workedMinutes = 0;
        $overtimeMinutes = 0;
        $paidLeaveMinutes = 0;
        $scheduledMinutes = 0;
        $flagged = false;
        $flagNotes = [];

        foreach ($workDates as $date) {
            $dayRecords = $recordsByDate->get($date, collect());
            $approvedRecords = $dayRecords->filter(fn (AttendanceRecord $record) => $this->countsForPayroll($record, $todayLocal));
            $pendingRecords = $dayRecords->filter(fn (AttendanceRecord $record) => $this->awaitsApproval($record, $todayLocal));
            $leaveType = $leaveMap[$date] ?? null;
            $plan = $this->scheduleForDate($schedule, $date);
            $isScheduled = $schedulePresent && $plan !== null && ! $plan['off'];

            if ($isScheduled) {
                $scheduledDays++;
                $scheduledMinutes += $this->scheduledMinutes($plan);
            }

            $minutesForDay = $approvedRecords->sum(fn (AttendanceRecord $record) => $this->recordMinutes($record));
            $workedMinutes += $minutesForDay;
            $pendingMinutesForDay = $pendingRecords->sum(fn (AttendanceRecord $record) => $this->recordMinutes($record));

            $checkIn = $approvedRecords->filter(fn (AttendanceRecord $record) => $record->check_in_at)->sortBy('check_in_at')->first();
            $checkOut = $approvedRecords->filter(fn (AttendanceRecord $record) => $record->check_out_at)->sortByDesc('check_out_at')->first();
            $present = $approvedRecords->isNotEmpty();
            $pendingApproval = $pendingRecords->isNotEmpty();

            if ($present) {
                $presentDays++;
            }

            if ($pendingApproval && $isScheduled && ! $present) {
                $pendingApprovalDays++;
            }

            if ($leaveType !== null && $isScheduled && ! $present && ! $pendingApproval) {
                if ($this->isPaidLeave($leaveType)) {
                    $paidLeaveDays++;
                    $paidLeaveMinutes += $this->scheduledMinutes($plan);
                } else {
                    $unpaidLeaveDays++;
                }
            }

            if ($isScheduled && $checkIn !== null) {
                $lateMinutes += $this->lateMinutes($checkIn, $date, $plan, $settings['grace_minutes'], $owner);
            }

            $overtimeMinutes += max(0, $minutesForDay - (int) $settings['overtime_after_minutes']);

            $dayFlagNotes = [];
            foreach ($dayRecords as $record) {
                if ($record->status === 'needs_review') {
                    $flagged = true;
                    $dayFlagNotes[] = __('hr_owner.review_required');
                }

                if ($record->check_out_remote || $record->source === 'offline' || $record->source === 'auto') {
                    $flagged = true;
                    $dayFlagNotes[] = __('hr_owner.flagged_record');
                }

                if ($record->status === 'open' && $record->work_date->toDateString() < $todayLocal) {
                    $flagged = true;
                    $dayFlagNotes[] = __('hr_owner.open_past_record');
                }
            }

            $status = 'off';
            if ($pendingApproval) {
                $status = 'pending_approval';
            } elseif ($leaveType !== null && $isScheduled && ! $present) {
                $status = $this->isPaidLeave($leaveType) ? 'paid_leave' : 'unpaid_leave';
            } elseif ($present) {
                $status = $dayFlagNotes !== [] ? 'flagged' : 'present';
            } elseif ($isScheduled) {
                $status = 'absent';
            }

            $days[] = [
                'date' => $date,
                'check_in_at' => $checkIn?->check_in_at ? ShopTime::local($checkIn->check_in_at, $owner)->format('H:i') : null,
                'check_out_at' => $checkOut?->check_out_at ? ShopTime::local($checkOut->check_out_at, $owner)->format('H:i') : null,
                'worked_minutes' => $minutesForDay,
                'pending_approval_minutes' => $pendingMinutesForDay,
                'late_minutes' => $isScheduled ? $this->lateMinutes($checkIn, $date, $plan, $settings['grace_minutes'], $owner) : 0,
                'overtime_minutes' => max(0, $minutesForDay - (int) $settings['overtime_after_minutes']),
                'status' => $status,
                'leave_type' => $leaveType,
                'flag_notes' => array_values(array_unique($dayFlagNotes)),
                'is_scheduled' => $isScheduled,
            ];

            $flagNotes = array_merge($flagNotes, $dayFlagNotes);
        }

        $excusedLeaveDays = $paidLeaveDays + $unpaidLeaveDays;
        $absentDays = $schedulePresent ? max(0, $scheduledDays - $presentDays - $excusedLeaveDays) : 0;
        $grossBase = $this->grossBase($employee, $schedulePresent, $scheduledDays, $absentDays + $unpaidLeaveDays, $presentDays, $workedMinutes, $paidLeaveDays, $paidLeaveMinutes);
        $overtimePay = $this->overtimePay($employee, $schedule, $start, $end, $scheduledDays, $scheduledMinutes, $overtimeMinutes, $settings['overtime_multiplier']);
        $pendingApprovalMinutes = $records->filter(fn (AttendanceRecord $record) => $this->awaitsApproval($record, $todayLocal))->sum(fn (AttendanceRecord $record) => $this->recordMinutes($record));
        $pendingApprovalAmount = round($this->pendingApprovalAmount($employee, $schedulePresent, $scheduledDays, $pendingApprovalDays, $pendingApprovalMinutes), 2);
        $hasPendingApproval = $pendingApprovalMinutes > 0 || $pendingApprovalDays > 0;
        $adjustmentTotal = $adjustments->sum(fn (EmployeeAdjustment $adjustment) => $adjustment->kind === 'bonus'
            ? (float) $adjustment->amount
            : -1 * abs((float) $adjustment->amount));
        $paidTotal = (float) $payments->sum('amount');
        $gross = $grossBase + $overtimePay + $adjustmentTotal;
        $netPayable = round($gross - $paidTotal, 2);

        return [
            'period' => $period,
            'employee' => $employee,
            'scheduled_days' => $scheduledDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'excused_leave_days' => $excusedLeaveDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'late_minutes' => $lateMinutes,
            'worked_minutes' => $workedMinutes,
            'pending_approval_minutes' => $pendingApprovalMinutes,
            'pending_approval_days' => $pendingApprovalDays,
            'pending_approval_amount' => $pendingApprovalAmount,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_pay' => round($overtimePay, 2),
            'gross_base' => round($grossBase, 2),
            'adjustments_total' => round($adjustmentTotal, 2),
            'already_paid' => round($paidTotal, 2),
            'gross' => round($gross, 2),
            'net_payable' => $netPayable,
            'status' => $hasPendingApproval
                ? 'pending_approval'
                : ($netPayable <= 0 ? ($netPayable < 0 ? 'overpaid' : 'paid') : ($flagged ? 'needs_review' : 'due')),
            'flagged' => $flagged,
            'flag_notes' => array_values(array_unique($flagNotes)),
            'days' => $days,
            'adjustments' => $adjustments->values(),
            'leaves' => $leaves->values(),
            'payments' => $payments->values(),
            'counts_needs_review_records' => true,
        ];
    }

    /**
     * @return list<string>
     */
    protected function periodDates(Carbon $start, Carbon $end, ?string $hireDate): array
    {
        $cursor = $hireDate ? Carbon::parse($hireDate)->greaterThan($start) ? Carbon::parse($hireDate) : $start->copy() : $start->copy();
        $last = $end->copy();
        $dates = [];

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    protected function countsForPayroll(AttendanceRecord $record, string $todayLocal): bool
    {
        if ($record->status === 'approved') {
            return true;
        }

        if ($record->status === 'closed') {
            return ! in_array($record->source, ['offline', 'auto'], true);
        }

        return false;
    }

    protected function awaitsApproval(AttendanceRecord $record, string $todayLocal): bool
    {
        if ($record->status === 'needs_review') {
            return true;
        }

        if ($record->status === 'open' && $record->work_date->toDateString() < $todayLocal) {
            return true;
        }

        return $record->status !== 'approved' && in_array($record->source, ['offline', 'auto'], true);
    }

    protected function recordMinutes(AttendanceRecord $record): int
    {
        if ($record->minutes_worked !== null) {
            return max(0, (int) $record->minutes_worked);
        }

        if ($record->check_in_at && $record->check_out_at) {
            return max(0, $record->check_in_at->diffInMinutes($record->check_out_at, false));
        }

        return 0;
    }

    /**
     * @param  array<int, array{start: string, end: string, off: bool}>|null  $schedule
     * @return array{start: string, end: string, off: bool}|null
     */
    protected function scheduleForDate(?array $schedule, string $date): ?array
    {
        if ($schedule === null) {
            return null;
        }

        return $schedule[Carbon::parse($date)->dayOfWeek] ?? null;
    }

    /**
     * @param  array<int, array{start: string, end: string, off: bool}>|null  $schedule
     */
    protected function scheduledMinutes(?array $schedule): int
    {
        if ($schedule === null || $schedule['off']) {
            return 0;
        }

        [$startHour, $startMinute] = array_map('intval', explode(':', $schedule['start']));
        [$endHour, $endMinute] = array_map('intval', explode(':', $schedule['end']));

        return max(0, (($endHour * 60) + $endMinute) - (($startHour * 60) + $startMinute));
    }

    protected function lateMinutes(?AttendanceRecord $record, string $date, ?array $schedule, int $graceMinutes, User $owner): int
    {
        if ($record === null || $schedule === null || $schedule['off'] || ! $record->check_in_at) {
            return 0;
        }

        $scheduled = Carbon::parse($date . ' ' . $schedule['start'], ShopTime::timezone($owner))->addMinutes($graceMinutes);
        $actual = ShopTime::local($record->check_in_at, $owner);

        return max(0, $scheduled->diffInMinutes($actual, false));
    }

    protected function grossBase(Employee $employee, bool $schedulePresent, int $scheduledDays, int $unpaidDays, int $presentDays, int $workedMinutes, int $paidLeaveDays, int $paidLeaveMinutes): float
    {
        return match ($employee->salary_type) {
            'hourly' => (($workedMinutes + $paidLeaveMinutes) / 60) * (float) ($employee->hourly_rate ?? 0),
            'daily' => ($presentDays + $paidLeaveDays) * (float) ($employee->daily_rate ?? 0),
            default => $this->monthlyBase($employee, $schedulePresent, $scheduledDays, $unpaidDays),
        };
    }

    /**
     * Overtime premium = overtime_minutes × normal_minute_rate × max(multiplier - 1, 0).
     *
     * The normal minute rate must come from configured pay and configured scheduled minutes,
     * never from actual worked/present minutes:
     * - hourly: hourly_rate ÷ 60
     * - daily: daily_rate ÷ average configured scheduled minutes per scheduled workday in the payable sub-period
     * - monthly: monthly_salary ÷ configured scheduled minutes in the full calendar month
     *
     * This keeps overtime stable when the employee has absences or joins part-way through a month.
     *
     * @param  array<int, array{start: string, end: string, off: bool}>|null  $schedule
     */
    protected function overtimePay(Employee $employee, ?array $schedule, Carbon $start, Carbon $end, int $scheduledDays, int $scheduledMinutes, int $overtimeMinutes, float $multiplier): float
    {
        if ($overtimeMinutes <= 0) {
            return 0;
        }

        $premiumFactor = max(0, $multiplier - 1);
        $minuteRate = $this->normalMinuteRate($employee, $schedule, $start, $end, $scheduledDays, $scheduledMinutes);

        return $overtimeMinutes * $minuteRate * $premiumFactor;
    }

    /**
     * Monthly base = monthly_salary − ((monthly_salary ÷ scheduled_days_in_payable_sub-period) × unpaid_days),
     * where unpaid_days are the absent days plus the unpaid-leave days (paid leave is never deducted).
     *
     * The payable sub-period starts on hire_date when the employee joins mid-month, so absences only
     * reduce the days the employee was actually scheduled to work in that active window.
     */
    protected function monthlyBase(Employee $employee, bool $schedulePresent, int $scheduledDays, int $unpaidDays): float
    {
        $base = (float) ($employee->monthly_salary ?? 0);

        if (! $schedulePresent || $scheduledDays <= 0) {
            return $base;
        }

        return max(0, $base - (($base / $scheduledDays) * $unpaidDays));
    }

    /**
     * @param  Collection<int, EmployeeLeave>  $leaves
     * @return array<string, string>
     */
    protected function expandLeaves(Collection $leaves): array
    {
        $map = [];

        foreach ($leaves as $leave) {
            $cursor = $leave->date_from->copy();
            $end = $leave->date_to->copy();

            while ($cursor->lte($end)) {
                $map[$cursor->toDateString()] = $leave->type;
                $cursor->addDay();
            }
        }

        return $map;
    }

    protected function isPaidLeave(string $type): bool
    {
        return in_array($type, ['paid', 'vacation', 'sick'], true);
    }

    protected function pendingApprovalAmount(Employee $employee, bool $schedulePresent, int $scheduledDays, int $pendingApprovalDays, int $pendingApprovalMinutes): float
    {
        if ($pendingApprovalMinutes <= 0 && $pendingApprovalDays <= 0) {
            return 0.0;
        }

        return match ($employee->salary_type) {
            'hourly' => ($pendingApprovalMinutes / 60) * (float) ($employee->hourly_rate ?? 0),
            'daily' => ((float) ($employee->daily_rate ?? 0)) * $pendingApprovalDays,
            default => $schedulePresent && $scheduledDays > 0
                ? (((float) ($employee->monthly_salary ?? 0)) / $scheduledDays) * $pendingApprovalDays
                : 0.0,
        };
    }

    /**
     * @return array<int, array{start: string, end: string, off: bool}>|null
     */
    protected function resolvedSchedule(Employee $employee, array $settings): ?array
    {
        $employeeSchedule = HrSettings::normalizeSchedule($employee->schedule);
        if ($employeeSchedule !== null) {
            return $employeeSchedule;
        }

        return HrSettings::normalizeSchedule($settings['default_schedule'] ?? null);
    }

    /**
     * @param  array<int, array{start: string, end: string, off: bool}>|null  $schedule
     */
    protected function normalMinuteRate(Employee $employee, ?array $schedule, Carbon $start, Carbon $end, int $scheduledDays, int $scheduledMinutes): float
    {
        return match ($employee->salary_type) {
            'hourly' => ((float) ($employee->hourly_rate ?? 0)) / 60,
            'daily' => $scheduledDays > 0 && $scheduledMinutes > 0
                ? ((float) ($employee->daily_rate ?? 0)) / ($scheduledMinutes / $scheduledDays)
                : 0.0,
            'monthly' => ($monthlyScheduledMinutes = $this->scheduledMinutesForRange($schedule, $start, $end)) > 0
                ? ((float) ($employee->monthly_salary ?? 0)) / $monthlyScheduledMinutes
                : 0.0,
            default => 0.0,
        };
    }

    /**
     * @param  array<int, array{start: string, end: string, off: bool}>|null  $schedule
     */
    protected function scheduledMinutesForRange(?array $schedule, Carbon $start, Carbon $end): int
    {
        if ($schedule === null) {
            return 0;
        }

        $minutes = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $minutes += $this->scheduledMinutes($this->scheduleForDate($schedule, $cursor->toDateString()));
            $cursor->addDay();
        }

        return $minutes;
    }
}
