<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.Supplier Report') }} - {{ $supplier->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0;
            font-size: 11px;
        }

        .supplier-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .supplier-info h2 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #495057;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 3px 10px 3px 0;
            width: 120px;
        }

        .info-value {
            display: table-cell;
            padding: 3px 0;
        }

        .section {
            margin-bottom: 25px;
        }

        .section h3 {
            font-size: 14px;
            font-weight: bold;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th,
        td {
            border: 1px solid #dee2e6;
            padding: 6px 8px;
            text-align: left;
            font-size: 11px;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }

        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .summary h4 {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #495057;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-row {
            display: table-row;
        }

        .summary-label {
            display: table-cell;
            font-weight: bold;
            padding: 3px 10px 3px 0;
            width: 150px;
        }

        .summary-value {
            display: table-cell;
            padding: 3px 0;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }

        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ __('messages.Supplier Report') }}</h1>
        <p>{{ __('messages.Period') }}: {{ \Carbon\Carbon::parse($date_from)->format('M d, Y') }} -
            {{ \Carbon\Carbon::parse($date_to)->format('M d, Y') }}</p>
        <p>{{ __('messages.Generated on') }}: {{ $generated_at->format('M d, Y H:i') }} | {{ __('messages.By') }}:
            {{ $generated_by }}</p>
    </div>

    <div class="supplier-info">
        <h2>{{ __('messages.Supplier Information') }}</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">{{ __('messages.Name') }}:</div>
                <div class="info-value">{{ $supplier->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ __('messages.Phone') }}:</div>
                <div class="info-value">{{ $supplier->phone ?: '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ __('messages.Email') }}:</div>
                <div class="info-value">{{ $supplier->email ?: '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ __('messages.Address') }}:</div>
                <div class="info-value">{{ $supplier->address ?: '-' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">{{ __('messages.Current Balance') }}:</div>
                <div class="info-value">₪{{ number_format(abs($supplier->balance), 2) }}
                    <span
                        style="font-size: 10px; color: {{ $supplier->balance > 0 ? '#dc3545' : ($supplier->balance < 0 ? '#28a745' : '#6c757d') }}">
                        ({{ $supplier->balance > 0 ? __('messages.We Owe Them') : ($supplier->balance < 0 ? __('messages.They Owe Us') : __('messages.Even')) }})
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if (($report_type === 'both' || $report_type === 'bills') && isset($purchase_bills))
        <div class="section">
            <h3>{{ __('messages.Purchase Bills') }}</h3>
            @if ($purchase_bills->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('messages.Bill #') }}</th>
                            <th>{{ __('messages.Date') }}</th>
                            <th>{{ __('messages.Reference') }}</th>
                            <th class="text-right">{{ __('messages.Amount') }}</th>
                            <th>{{ __('messages.Created By') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase_bills as $bill)
                            <tr>
                                <td>#{{ $bill->id }}</td>
                                <td>{{ $bill->purchase_date->format('M d, Y') }}</td>
                                <td>{{ $bill->reference_number ?: '-' }}</td>
                                <td class="text-right">₪{{ number_format($bill->total_amount, 2) }}</td>
                                <td>{{ $bill->creator->name }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="3" class="text-right"><strong>{{ __('messages.Total') }}:</strong></td>
                            <td class="text-right"><strong>₪{{ number_format($bills_total, 2) }}</strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p>{{ __('messages.No purchase bills found for the selected period') }}</p>
            @endif
        </div>
    @endif

    @if (($report_type === 'both' || $report_type === 'payments') && isset($payments))
        <div class="section">
            <h3>{{ __('messages.Payments') }}</h3>
            @if ($payments->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('messages.Date') }}</th>
                            <th>{{ __('messages.Type') }}</th>
                            <th>{{ __('messages.Amount') }}</th>
                            <th>{{ __('messages.Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
                                <td>{{ ucfirst($payment->type) }}</td>
                                <td>
                                    <span style="color: {{ $payment->amount > 0 ? '#dc3545' : '#28a745' }}">
                                        {{ $payment->amount > 0 ? '-' : '+' }}₪{{ number_format(abs($payment->amount), 2) }}
                                    </span>
                                    <br><small style="color: #6c757d;">
                                        {{ $payment->amount > 0 ? __('messages.We Paid') : __('messages.They Paid') }}
                                    </small>
                                </td>
                                <td>{{ $payment->note ?: '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2" class="text-right"><strong>{{ __('messages.Net Payment') }}:</strong>
                            </td>
                            <td colspan="2">
                                <strong style="color: {{ $payments_total > 0 ? '#dc3545' : '#28a745' }}">
                                    {{ $payments_total > 0 ? '-' : '+' }}₪{{ number_format(abs($payments_total), 2) }}
                                </strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p>{{ __('messages.No payments found for the selected period') }}</p>
            @endif
        </div>
    @endif

    @if ($report_type === 'both')
        <div class="summary">
            <h4>{{ __('messages.Summary') }}</h4>
            <div class="summary-grid">
                <div class="summary-row">
                    <div class="summary-label">{{ __('messages.Total Purchase Bills') }}:</div>
                    <div class="summary-value">₪{{ isset($bills_total) ? number_format($bills_total, 2) : '0.00' }}
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">{{ __('messages.Total Payments') }}:</div>
                    <div class="summary-value"
                        style="color: {{ isset($payments_total) && $payments_total > 0 ? '#dc3545' : '#28a745' }}">
                        ₪{{ isset($payments_total) ? number_format(abs($payments_total), 2) : '0.00' }}
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">{{ __('messages.Net Balance Change') }}:</div>
                    <div class="summary-value"
                        style="color: {{ (isset($bills_total) ? $bills_total : 0) - (isset($payments_total) ? $payments_total : 0) > 0 ? '#dc3545' : '#28a745' }}">
                        ₪{{ number_format((isset($bills_total) ? $bills_total : 0) - (isset($payments_total) ? $payments_total : 0), 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="footer">
        <p>{{ __('messages.Report generated by') }} {{ config('app.name') }} |
            {{ $generated_at->format('M d, Y H:i:s') }}</p>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        }
    </script>
</body>

</html>
