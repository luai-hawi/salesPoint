<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('hr_owner.payslip_title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 24px; color: #111827; }
        .wrap { max-width: 800px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .card { border: 1px solid #d1d5db; border-radius: 12px; padding: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; text-align: start; }
        th { background: #f9fafb; }
        .print { margin-bottom: 16px; }
        @media print { .print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="print"><button onclick="window.print()">{{ __('hr_owner.print_payslip') }}</button></div>
        <h1>{{ __('hr_owner.payslip_title') }}</h1>
        <p>{{ $owner->name }} — {{ $period }}</p>
        <div class="grid">
            <div class="card">
                <strong>{{ __('hr_owner.employee') }}</strong>
                <div>{{ $employee->name }}</div>
                <div>{{ $employee->job_title }}</div>
            </div>
            <div class="card">
                <strong>{{ __('hr_owner.salary_type') }}</strong>
                <div>{{ __('hr_owner.salary_types.' . $employee->salary_type) }}</div>
                <div>{{ __('hr_owner.net_payable') }}: ₪{{ number_format((float) $summary['net_payable'], 2) }}</div>
            </div>
        </div>

        <table>
            <tr><th>{{ __('hr_owner.scheduled_days') }}</th><td>{{ $summary['scheduled_days'] }}</td><th>{{ __('hr_owner.present_days') }}</th><td>{{ $summary['present_days'] }}</td></tr>
            <tr><th>{{ __('hr_owner.absent_days') }}</th><td>{{ $summary['absent_days'] }}</td><th>{{ __('hr_owner.excused_leave_days') }}</th><td>{{ $summary['excused_leave_days'] }}</td></tr>
            <tr><th>{{ __('hr_owner.worked_hours') }}</th><td>{{ number_format($summary['worked_minutes'] / 60, 2) }}</td><th>{{ __('hr_owner.overtime_hours') }}</th><td>{{ number_format($summary['overtime_minutes'] / 60, 2) }}</td></tr>
            <tr><th>{{ __('hr_owner.gross_amount') }}</th><td>₪{{ number_format((float) $summary['gross'], 2) }}</td><th>{{ __('hr_owner.already_paid') }}</th><td>₪{{ number_format((float) $summary['already_paid'], 2) }}</td></tr>
            <tr><th>{{ __('hr_owner.net_payable') }}</th><td colspan="3">₪{{ number_format((float) $summary['net_payable'], 2) }}</td></tr>
        </table>

        <h2>{{ __('hr_owner.monthly_timesheet') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('hr_owner.date') }}</th>
                    <th>{{ __('hr_owner.check_in') }}</th>
                    <th>{{ __('hr_owner.check_out') }}</th>
                    <th>{{ __('hr_owner.worked_hours') }}</th>
                    <th>{{ __('hr_owner.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['days'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['check_in_at'] ?: '—' }}</td>
                        <td>{{ $day['check_out_at'] ?: '—' }}</td>
                        <td>{{ number_format($day['worked_minutes'] / 60, 2) }}</td>
                        <td>{{ __('hr_owner.day_statuses.' . $day['status']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
