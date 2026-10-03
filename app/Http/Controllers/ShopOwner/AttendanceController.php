<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopOwner\Concerns\ResolvesHrOwner;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\ActivityLogger;
use App\Services\Payroll\PayrollService;
use App\Support\CsvSanitizer;
use App\Support\HrSettings;
use App\Support\PayrollPeriod;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    use ResolvesHrOwner;

    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request)
    {
        $owner = $this->hrOwner();
        $ownerId = (int) $owner->id;
        $settings = HrSettings::normalize($owner->attendance_settings);
        $from = $request->string('from')->trim()->value() ?: ShopTime::today($owner);
        $to = $request->string('to')->trim()->value() ?: $from;
        $focusDate = $to;

        $employees = Employee::query()
            ->where('shop_owner_id', $ownerId)
            ->orderBy('name')
            ->get();

        $recordsQuery = AttendanceRecord::withoutGlobalScopes()
            ->with('employee')
            ->where('user_id', $ownerId)
            ->whereBetween('work_date', [$from, $to])
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->string('status')->trim()->value(), function ($query, $status) {
                if ($status === 'flagged') {
                    $query->where(function ($flagged) {
                        $flagged->where('status', 'needs_review')
                            ->orWhere('check_out_remote', true)
                            ->orWhereIn('source', ['offline', 'auto']);
                    });

                    return;
                }

                if ($status === 'in_now') {
                    $query->whereNull('check_out_at');

                    return;
                }

                $query->where('status', $status);
            });

        $records = (clone $recordsQuery)
            ->orderByDesc('work_date')
            ->orderByDesc('check_in_at')
            ->paginate(25)
            ->withQueryString();

        $dayRecords = AttendanceRecord::withoutGlobalScopes()
            ->with('employee')
            ->where('user_id', $ownerId)
            ->whereDate('work_date', $focusDate)
            ->get()
            ->groupBy('employee_id');

        $board = $employees
            ->when($request->filled('employee_id'), fn ($collection) => $collection->where('id', $request->integer('employee_id')))
            ->values()
            ->map(function (Employee $employee) use ($dayRecords, $owner, $settings, $focusDate) {
                $employeeDayRecords = $dayRecords->get($employee->id, collect());
                $record = $employeeDayRecords->sortByDesc(fn ($item) => $item->check_out_at ?? $item->check_in_at)->first();
                $status = $this->boardStatus($employee, $record, $owner, $settings, $focusDate);

                return [
                    'employee' => $employee,
                    'record' => $record,
                    'status' => $status,
                    'hours' => round(($this->recordMinutes($record) / 60), 2),
                    'distance' => $record ? ($record->check_out_distance_m ?? $record->check_in_distance_m) : null,
                ];
            });

        $reviewQueue = AttendanceRecord::withoutGlobalScopes()
            ->with('employee')
            ->where('user_id', $ownerId)
            ->whereBetween('work_date', [$from, $to])
            ->where('status', 'needs_review')
            ->orderByDesc('work_date')
            ->orderByDesc('check_out_recorded_at')
            ->get();

        $mapPoints = AttendanceRecord::withoutGlobalScopes()
            ->with('employee')
            ->where('user_id', $ownerId)
            ->whereDate('work_date', $focusDate)
            ->get()
            ->flatMap(function (AttendanceRecord $record) {
                $points = [];
                if ($record->check_in_lat !== null && $record->check_in_lng !== null) {
                    $points[] = [
                        'label' => $record->employee?->name . ' / in',
                        'lat' => $record->check_in_lat,
                        'lng' => $record->check_in_lng,
                    ];
                }
                if ($record->check_out_lat !== null && $record->check_out_lng !== null) {
                    $points[] = [
                        'label' => $record->employee?->name . ' / out',
                        'lat' => $record->check_out_lat,
                        'lng' => $record->check_out_lng,
                    ];
                }

                return $points;
            })->values();

        return view('shopowner.attendance.index', [
            'owner' => $owner,
            'settings' => $settings,
            'employees' => $employees,
            'records' => $records,
            'board' => $board,
            'reviewQueue' => $reviewQueue,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'employee_id' => $request->input('employee_id'),
                'status' => $request->input('status'),
            ],
            'focusDate' => $focusDate,
            'mapPoints' => $mapPoints,
            'counters' => [
                'in_now' => $board->where('status.key', 'in_now')->count(),
                'late' => $board->where('status.is_late', true)->count(),
                'absent' => $board->where('status.key', 'absent')->count(),
                'left' => $board->where('status.key', 'left')->count(),
                'flagged' => $board->where('status.flagged', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'check_in_local' => ['required', 'date'],
            'check_out_local' => ['nullable', 'date', 'after_or_equal:check_in_local'],
            'status' => ['nullable', Rule::in(['open', 'closed', 'approved', 'needs_review', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $this->authorizeEmployee($employee);
        $owner = $this->ownerForEmployee($employee);
        $settings = HrSettings::normalize($owner->attendance_settings);

        $checkIn = $this->localToUtc($validated['check_in_local'], $owner);
        $checkOut = filled($validated['check_out_local'] ?? null) ? $this->localToUtc($validated['check_out_local'], $owner) : null;
        $record = AttendanceRecord::create([
            'user_id' => $this->ownerIdForEmployee($employee),
            'employee_id' => $employee->id,
            'work_date' => ShopTime::localDate($checkIn, $owner),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'minutes_worked' => $this->workedMinutes($checkIn, $checkOut, (int) $settings['rounding_minutes']),
            'status' => $validated['status'] ?: ($checkOut ? 'approved' : 'open'),
            'source' => 'manual',
            'reason' => $validated['reason'] ?: null,
            'created_by' => $this->hrActor()->id,
        ]);

        ActivityLogger::record('attendance_record.created', AttendanceRecord::class, $record, [
            'employee_id' => $employee->id,
            'status' => $record->status,
        ], label: $employee->name, ownerId: $this->ownerIdForEmployee($employee));

        return back()->with('success', __('hr_owner.attendance_record_saved'));
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorizeRecord($attendanceRecord);
        $owner = $this->ownerForRecord($attendanceRecord);
        $settings = HrSettings::normalize($owner->attendance_settings);
        $validated = $request->validate([
            'check_in_local' => ['required', 'date'],
            'check_out_local' => ['nullable', 'date', 'after_or_equal:check_in_local'],
            'status' => ['required', Rule::in(['open', 'closed', 'approved', 'needs_review', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $checkIn = $this->localToUtc($validated['check_in_local'], $owner);
        $checkOut = filled($validated['check_out_local'] ?? null) ? $this->localToUtc($validated['check_out_local'], $owner) : null;
        $attendanceRecord->update([
            'work_date' => ShopTime::localDate($checkIn, $owner),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'minutes_worked' => $this->workedMinutes($checkIn, $checkOut, (int) $settings['rounding_minutes']),
            'status' => $validated['status'],
            'reason' => $validated['reason'] ?: null,
            'review_note' => $validated['review_note'] ?: null,
            'reviewed_by' => $this->hrActor()->id,
            'reviewed_at' => now(),
        ]);

        ActivityLogger::record('attendance_record.updated', AttendanceRecord::class, $attendanceRecord, [
            'employee_id' => $attendanceRecord->employee_id,
            'status' => $attendanceRecord->status,
        ], label: $attendanceRecord->employee?->name, ownerId: (int) $attendanceRecord->user_id);

        return back()->with('success', __('hr_owner.attendance_record_saved'));
    }

    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $this->authorizeRecord($attendanceRecord);

        ActivityLogger::record('attendance_record.deleted', AttendanceRecord::class, $attendanceRecord, [
            'employee_id' => $attendanceRecord->employee_id,
        ], label: $attendanceRecord->employee?->name, ownerId: (int) $attendanceRecord->user_id);

        $attendanceRecord->delete();

        return back()->with('success', __('hr_owner.attendance_record_deleted'));
    }

    public function review(Request $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorizeRecord($attendanceRecord);
        $owner = $this->ownerForRecord($attendanceRecord);
        $settings = HrSettings::normalize($owner->attendance_settings);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'edit'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'check_in_local' => ['nullable', 'date'],
            'check_out_local' => ['nullable', 'date', 'after_or_equal:check_in_local'],
        ]);

        $payload = [
            'review_note' => $validated['review_note'] ?: null,
            'reason' => ($validated['reason'] ?? null) ?: $attendanceRecord->reason,
            'reviewed_by' => $this->hrActor()->id,
            'reviewed_at' => now(),
        ];

        if ($validated['decision'] === 'approve') {
            $payload['status'] = 'approved';
        } elseif ($validated['decision'] === 'reject') {
            $payload['status'] = 'rejected';
        } else {
            $checkIn = filled($validated['check_in_local'] ?? null)
                ? $this->localToUtc($validated['check_in_local'], $owner)
                : $attendanceRecord->check_in_at;
            $checkOut = filled($validated['check_out_local'] ?? null)
                ? $this->localToUtc($validated['check_out_local'], $owner)
                : $attendanceRecord->check_out_at;

            $payload['check_in_at'] = $checkIn;
            $payload['check_out_at'] = $checkOut;
            $payload['work_date'] = $checkIn ? ShopTime::localDate($checkIn, $owner) : $attendanceRecord->work_date->toDateString();
            $payload['minutes_worked'] = $this->workedMinutes($checkIn, $checkOut, (int) $settings['rounding_minutes']);
            $payload['status'] = $checkOut ? 'approved' : 'open';
        }

        $attendanceRecord->update($payload);

        ActivityLogger::record('attendance_record.reviewed', AttendanceRecord::class, $attendanceRecord, [
            'employee_id' => $attendanceRecord->employee_id,
            'decision' => $validated['decision'],
        ], label: $attendanceRecord->employee?->name, ownerId: (int) $attendanceRecord->user_id);

        return back()->with('success', __('hr_owner.review_saved'));
    }

    public function export(Request $request)
    {
        $owner = $this->hrOwner();
        $ownerId = (int) $owner->id;
        $from = $request->string('from')->trim()->value() ?: ShopTime::today($owner);
        $to = $request->string('to')->trim()->value() ?: $from;

        $records = AttendanceRecord::withoutGlobalScopes()
            ->with('employee')
            ->where('user_id', $ownerId)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->get();
        $employeeNames = Employee::query()
            ->whereIn('id', $records->pluck('employee_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return response()->streamDownload(function () use ($records, $employeeNames, $ownerId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Employee', 'Status', 'Check In', 'Check Out', 'Minutes', 'Remote Checkout', 'Reason']);
            foreach ($records as $record) {
                fputcsv($out, [
                    $record->work_date->toDateString(),
                    CsvSanitizer::cell($record->employee?->name ?? $employeeNames->get($record->employee_id)),
                    CsvSanitizer::cell($record->status),
                    $record->check_in_at ? ShopTime::local($record->check_in_at, $ownerId)->format('Y-m-d H:i') : null,
                    $record->check_out_at ? ShopTime::local($record->check_out_at, $ownerId)->format('Y-m-d H:i') : null,
                    $record->minutes_worked,
                    $record->check_out_remote ? 'yes' : 'no',
                    CsvSanitizer::cell($record->reason),
                ]);
            }
            fclose($out);
        }, 'attendance-' . $from . '-to-' . $to . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function timesheet(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $owner = $this->ownerForEmployee($employee->loadMissing('shopOwner'));
        $period = $this->resolvedPeriod($request->string('period')->trim()->value(), $owner);
        $summary = $this->payrollService->compute($employee, $period);

        return view('shopowner.attendance.timesheet', [
            'employee' => $employee,
            'period' => $period,
            'summary' => $summary,
        ]);
    }

    protected function boardStatus(Employee $employee, ?AttendanceRecord $record, $owner, array $settings, string $date): array
    {
        $schedule = HrSettings::normalizeSchedule($employee->schedule)
            ?? HrSettings::normalizeSchedule($settings['default_schedule'] ?? null);
        $dayPlan = $schedule[Carbon::parse($date)->dayOfWeek] ?? null;

        if ($record === null) {
            $scheduled = $dayPlan !== null && ! $dayPlan['off'];

            return [
                'key' => $scheduled ? 'absent' : 'off',
                'label' => $scheduled ? __('hr_owner.attendance_absent') : __('hr_owner.day_off'),
                'tone' => $scheduled ? 'red' : 'gray',
                'flagged' => false,
                'is_late' => false,
                'detail' => null,
            ];
        }

        $late = false;
        if ($record->check_in_at && $dayPlan !== null && ! $dayPlan['off']) {
            $scheduledAt = Carbon::parse($date . ' ' . $dayPlan['start'], ShopTime::timezone($owner))->addMinutes((int) $settings['grace_minutes']);
            $late = ShopTime::local($record->check_in_at, $owner)->greaterThan($scheduledAt);
        }

        if ($record->status === 'needs_review' || $record->check_out_remote || in_array($record->source, ['offline', 'auto'], true)) {
            return [
                'key' => 'flagged',
                'label' => __('hr_owner.attendance_flagged'),
                'tone' => 'amber',
                'flagged' => true,
                'is_late' => $late,
                'detail' => $record->check_out_remote && $record->check_out_distance_m
                    ? __('hr_owner.flagged_distance', ['distance' => $this->formatDistance((int) $record->check_out_distance_m)])
                    : null,
            ];
        }

        if ($record->check_out_at === null) {
            return [
                'key' => 'in_now',
                'label' => __('hr_owner.attendance_in'),
                'tone' => 'green',
                'flagged' => false,
                'is_late' => $late,
                'detail' => $record->check_in_at ? ShopTime::local($record->check_in_at, $owner)->format('H:i') : null,
            ];
        }

        return [
            'key' => 'left',
            'label' => __('hr_owner.attendance_left'),
            'tone' => 'blue',
            'flagged' => false,
            'is_late' => $late,
            'detail' => ShopTime::local($record->check_out_at, $owner)->format('H:i'),
        ];
    }

    protected function localToUtc(string $value, $owner): Carbon
    {
        return Carbon::parse($value, ShopTime::timezone($owner))->utc();
    }

    protected function workedMinutes(?Carbon $checkIn, ?Carbon $checkOut, int $rounding): ?int
    {
        if (! $checkIn || ! $checkOut) {
            return null;
        }

        $minutes = max(0, $checkIn->diffInMinutes($checkOut, false));

        if ($rounding <= 0) {
            return $minutes;
        }

        return (int) (round($minutes / $rounding) * $rounding);
    }

    protected function recordMinutes(?AttendanceRecord $record): int
    {
        if ($record === null) {
            return 0;
        }

        if ($record->minutes_worked !== null) {
            return (int) $record->minutes_worked;
        }

        if ($record->check_in_at && $record->check_out_at) {
            return max(0, $record->check_in_at->diffInMinutes($record->check_out_at, false));
        }

        if ($record->check_in_at && $record->work_date->toDateString() === ShopTime::today($this->hrOwner())) {
            return max(0, $record->check_in_at->diffInMinutes(now(), false));
        }

        return 0;
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
}
