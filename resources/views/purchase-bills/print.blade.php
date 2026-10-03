<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('payables.titles.print_purchase_bill', ['id' => $purchaseBill->id]) }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 24px;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        .box {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .grid {
            display: table;
            width: 100%;
        }

        .row {
            display: table-row;
        }

        .cell {
            display: table-cell;
            padding: 4px 0;
        }

        .label {
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
        }

        th {
            background: #f3f4f6;
            font-size: 11px;
            text-transform: uppercase;
        }

        .text-end {
            text-align: end;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>{{ __('payables.titles.purchase_bill', ['id' => $purchaseBill->id]) }}</h1>
        <p>{{ $purchaseBill->supplier?->name }}</p>
    </div>

    <div class="box">
        <div class="grid">
            <div class="row">
                <div class="cell label">{{ __('payables.fields.purchase_date') }}</div>
                <div class="cell">{{ optional($purchaseBill->purchase_date)->format('Y-m-d') }}</div>
            </div>
            <div class="row">
                <div class="cell label">{{ __('payables.fields.reference_number') }}</div>
                <div class="cell">{{ $purchaseBill->reference_number ?: '—' }}</div>
            </div>
            <div class="row">
                <div class="cell label">{{ __('payables.fields.created_by') }}</div>
                <div class="cell">{{ $purchaseBill->creator?->name ?: '—' }}</div>
            </div>
            <div class="row">
                <div class="cell label">{{ __('payables.fields.status') }}</div>
                <div class="cell">{{ __('payables.statuses.' . $summary['status']) }}</div>
            </div>
            <div class="row">
                <div class="cell label">{{ __('payables.fields.remaining') }}</div>
                <div class="cell">₪{{ number_format($summary['due'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="box">
        <h2>{{ __('payables.sections.products') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('payables.fields.products') }}</th>
                    <th>{{ __('messages.Quantity') }}</th>
                    <th>{{ __('messages.Unit Cost') }}</th>
                    <th>{{ __('payables.fields.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseBill->products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ number_format((float) $product->pivot->quantity, 2) }}</td>
                        <td class="text-end">₪{{ number_format((float) $product->pivot->unit_cost, 2) }}</td>
                        <td class="text-end">₪{{ number_format((float) $product->pivot->total_cost, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="box">
        <h2>{{ __('payables.sections.bill_payments') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('payables.fields.payment_date') }}</th>
                    <th>{{ __('payables.fields.method') }}</th>
                    <th>{{ __('payables.fields.amount') }}</th>
                    <th>{{ __('payables.fields.notes') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchaseBill->payments as $payment)
                    <tr>
                        <td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                        <td>{{ __('payables.methods.' . $payment->type) }}</td>
                        <td class="text-end">₪{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ $payment->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">{{ __('payables.messages.no_payments') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>

</html>
