<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payables.titles.supplier_statement') }} - {{ $supplier->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 24px;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        .header,
        .box {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .meta,
        .summary {
            display: table;
            width: 100%;
        }

        .meta-row,
        .summary-row {
            display: table-row;
        }

        .meta-label,
        .summary-label,
        .meta-value,
        .summary-value {
            display: table-cell;
            padding: 4px 0;
        }

        .meta-label,
        .summary-label {
            width: 160px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-size: 11px;
            text-transform: uppercase;
        }

        .text-end {
            text-align: end;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ __('payables.statement.title') }}</h1>
        <p class="muted">{{ $supplier->name }}</p>
    </div>

    <div class="box">
        <div class="meta">
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.date_from') }}</div>
                <div class="meta-value">{{ \Carbon\Carbon::parse($date_from)->format('Y-m-d') }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.date_to') }}</div>
                <div class="meta-value">{{ \Carbon\Carbon::parse($date_to)->format('Y-m-d') }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.phone') }}</div>
                <div class="meta-value">{{ $supplier->phone ?: '—' }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.email') }}</div>
                <div class="meta-value">{{ $supplier->email ?: '—' }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.balance') }}</div>
                <div class="meta-value">₪{{ number_format(abs((float) $supplier->balance), 2) }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.status') }}</div>
                <div class="meta-value">
                    {{ (float) $supplier->balance > 0 ? __('payables.statuses.we_owe') : ((float) $supplier->balance < 0 ? __('payables.statuses.supplier_owes') : __('payables.statuses.settled')) }}
                </div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.report_type') }}</div>
                <div class="meta-value">{{ __('payables.report_types.' . $report_type) }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">{{ __('payables.fields.created_by') }}</div>
                <div class="meta-value">{{ $generated_by }}</div>
            </div>
        </div>
    </div>

    <div class="box">
        <h2>{{ __('payables.statement.title') }}</h2>
        @if ($statement_rows->isEmpty())
            <p class="muted" style="margin-top: 8px;">{{ __('payables.messages.no_statement_rows') }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('payables.statement.date') }}</th>
                        <th>{{ __('payables.statement.description') }}</th>
                        <th>{{ __('payables.statement.reference') }}</th>
                        <th>{{ __('payables.statement.increase') }}</th>
                        <th>{{ __('payables.statement.decrease') }}</th>
                        <th>{{ __('payables.fields.running_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statement_rows as $row)
                        <tr>
                            <td>{{ $row['date']->format('Y-m-d') }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td>{{ $row['reference'] ?: '—' }}</td>
                            <td class="text-end">{{ $row['increase'] > 0 ? '₪' . number_format($row['increase'], 2) : '—' }}</td>
                            <td class="text-end">{{ $row['decrease'] > 0 ? '₪' . number_format($row['decrease'], 2) : '—' }}</td>
                            <td class="text-end">₪{{ number_format(abs($row['running_balance']), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (($report_type === 'both' || $report_type === 'bills') && isset($purchase_bills))
        <div class="box">
            <h2>{{ __('payables.titles.purchase_bills') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('payables.fields.bill') }}</th>
                        <th>{{ __('payables.fields.purchase_date') }}</th>
                        <th>{{ __('payables.fields.reference_number') }}</th>
                        <th>{{ __('payables.fields.created_by') }}</th>
                        <th>{{ __('payables.fields.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchase_bills as $bill)
                        <tr>
                            <td>#{{ $bill->id }}</td>
                            <td>{{ optional($bill->purchase_date)->format('Y-m-d') }}</td>
                            <td>{{ $bill->reference_number ?: '—' }}</td>
                            <td>{{ $bill->creator?->name ?: '—' }}</td>
                            <td class="text-end">₪{{ number_format((float) $bill->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">{{ __('payables.messages.no_bills') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if (($report_type === 'both' || $report_type === 'payments') && isset($payments))
        <div class="box">
            <h2>{{ __('payables.sections.payments') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('payables.fields.payment_date') }}</th>
                        <th>{{ __('payables.fields.type') }}</th>
                        <th>{{ __('payables.fields.bill') }}</th>
                        <th>{{ __('payables.fields.amount') }}</th>
                        <th>{{ __('payables.fields.notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $kindKey = match (\App\Services\SupplierLedger::kindForRow($payment)) {
                                \App\Services\SupplierLedger::KIND_BILL_PAYMENT => 'bill_payment',
                                \App\Services\SupplierLedger::KIND_OPENING => 'opening_balance',
                                \App\Services\SupplierLedger::KIND_REFUND => 'refund',
                                default => 'payment',
                            };
                        @endphp
                        <tr>
                            <td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                            <td>{{ __('payables.kinds.' . $kindKey) }} — {{ __('payables.methods.' . $payment->type) }}</td>
                            <td>{{ $payment->purchaseBill ? '#' . $payment->purchaseBill->id : '—' }}</td>
                            <td class="text-end">₪{{ number_format(abs((float) $payment->amount), 2) }}</td>
                            <td>{{ $payment->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">{{ __('payables.messages.no_payments') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</body>

</html>
