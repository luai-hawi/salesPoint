<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('receivables.customer_statement') }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: start; font-size: 12px; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 11px; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .card { border: 1px solid #d1d5db; border-radius: 12px; padding: 12px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">{{ __('receivables.print_statement') }}</button>
    <h1>{{ __('receivables.customer_statement') }}</h1>
    <p>{{ $customer->name }} — {{ $customer->phone ?: '—' }}</p>

    <div class="stats">
        <div class="card">{{ __('messages.Balance') }}<br><strong>₪{{ number_format($customer->balance, 2) }}</strong></div>
        <div class="card">{{ __('messages.Total Payments') }}<br><strong>{{ $payments->count() }}</strong></div>
        <div class="card">{{ __('receivables.open_bills') }}<br><strong>{{ $openBills->count() }}</strong></div>
        <div class="card">{{ __('messages.Date') }}<br><strong>{{ now()->format('Y-m-d H:i') }}</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('receivables.date') }}</th>
                <th>{{ __('receivables.type') }}</th>
                <th>{{ __('messages.Amount') }}</th>
                <th>{{ __('receivables.running_balance') }}</th>
                <th>{{ __('receivables.bill_reference') }}</th>
                <th>{{ __('receivables.note') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payments as $payment)
                @php
                    $kind = \App\Services\CustomerLedger::kindForRow($payment);
                    $kindLabel = match ($kind) {
                        \App\Services\CustomerLedger::KIND_BILL_CHARGE => __('receivables.bill_charge'),
                        \App\Services\CustomerLedger::KIND_BILL_PAYMENT => __('receivables.bill_payment'),
                        \App\Services\CustomerLedger::KIND_OPENING => __('receivables.opening_balance'),
                        \App\Services\CustomerLedger::KIND_ADJUSTMENT => __('receivables.adjustment'),
                        default => __('receivables.general_payment'),
                    };
                @endphp
                <tr>
                    <td>{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $kindLabel }}</td>
                    <td>₪{{ number_format($payment->amount, 2) }}</td>
                    <td>₪{{ number_format($runningBalance[$payment->id] ?? 0, 2) }}</td>
                    <td>{{ $payment->bill_id ? '#' . $payment->bill_id : '—' }}</td>
                    <td>{{ $payment->note ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
