<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.Comprehensive Financial Report') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .page-break {
                page-break-before: always;
            }

            .no-print {
                display: none;
            }
        }

        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .amount-positive {
            color: #059669;
            font-weight: 600;
        }

        .amount-negative {
            color: #dc2626;
            font-weight: 600;
        }

        .amount-neutral {
            color: #6b7280;
            font-weight: 600;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">

    <!-- Print Controls -->
    <div class="no-print fixed top-4 right-4 z-50">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-lg">
            🖨️ {{ __('messages.Print Report') }}
        </button>
        <button onclick="window.close()"
            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow-lg ml-2">
            ✕ {{ __('messages.Close') }}
        </button>
    </div>

    <!-- Report Header -->
    <div class="report-header p-8 mb-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">{{ __('messages.Comprehensive Financial Report') }}</h1>
                    <p class="text-lg opacity-90">{{ __('messages.Period') }}:
                        {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} -
                        {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm opacity-75">{{ __('messages.Generated on') }}: {{ $generated_at }}</p>
                    <p class="text-sm opacity-75">{{ __('messages.By') }}: {{ $generated_by }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-8 pb-8">

        <!-- Executive Summary -->
        <div class="section-card p-6 mb-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">📊 {{ __('messages.Executive Summary') }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <div class="bg-green-50 p-4 rounded-lg border-l-4 border-green-500">
                    <h3 class="font-semibold text-green-800">{{ __('messages.Total Revenue') }}</h3>
                    <p class="text-2xl font-bold text-green-600">₪{{ number_format($summary['total_revenue'], 0) }}</p>
                </div>

                <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-500">
                    <h3 class="font-semibold text-blue-800">{{ __('messages.Total Costs') }}</h3>
                    <p class="text-2xl font-bold text-blue-600">₪{{ number_format($summary['total_costs'], 0) }}</p>
                </div>

                <div class="bg-purple-50 p-4 rounded-lg border-l-4 border-purple-500">
                    <h3 class="font-semibold text-purple-800">{{ __('messages.Net Profit') }}</h3>
                    <p
                        class="text-2xl font-bold {{ $summary['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ₪{{ number_format($summary['net_profit'], 0) }}
                    </p>
                </div>

                <div class="bg-orange-50 p-4 rounded-lg border-l-4 border-orange-500">
                    <h3 class="font-semibold text-orange-800">{{ __('messages.Net Cash Flow') }}</h3>
                    <p
                        class="text-2xl font-bold {{ $summary['net_cash_flow'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ₪{{ number_format($summary['net_cash_flow'], 0) }}
                    </p>
                </div>

                <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-500">
                    <h3 class="font-semibold text-blue-800">{{ __('messages.Profit') }}</h3>
                    <p
                        class="text-2xl font-bold {{ $summary['financial_dashboard_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        ₪{{ number_format($summary['financial_dashboard_profit'], 0) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Regular Sales Bills Section -->
        @if ($sales_bills->count() > 0)
            <div class="section-card p-6 mb-8 page-break">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">🛒 {{ __('messages.Sales Bills') }} -
                    {{ __('messages.Regular Sales') }} ({{ $sales_bills->count() }} bills)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Bill #') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Customer') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Items') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Total') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Created By') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($sales_bills as $bill)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium">#{{ $bill->id }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $bill->created_at->format('M d, Y H:i') }}</td>
                                    <td class="px-4 py-3">{{ $bill->customer->name ?? 'Walk-in' }}</td>
                                    <td class="px-4 py-3">
                                        @foreach ($bill->products as $product)
                                            <div class="text-sm">{{ $product->name }}
                                                ({{ $product->pivot->quantity }}×)</div>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold amount-positive">
                                        ₪{{ number_format($bill->total_price, 2) }}</td>
                                    <td class="px-4 py-3">{{ $bill->creator->name ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Sales') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-positive">
                                    ₪{{ number_format($summary['total_sales'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Purchase Bills Section -->
        @if ($purchase_bills->count() > 0)
            <div class="section-card p-6 mb-8 page-break">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">📦 {{ __('messages.Purchase Bills') }}
                    ({{ $purchase_bills->count() }} bills)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Bill #') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Supplier') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Items') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Total') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Created By') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($purchase_bills as $bill)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium">#{{ $bill->id }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $bill->purchase_date->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">{{ $bill->supplier->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">
                                        @foreach ($bill->products as $product)
                                            <div class="text-sm">{{ $product->name }}
                                                ({{ $product->pivot->quantity }}× @
                                                ₪{{ number_format($product->pivot->unit_cost, 2) }})</div>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold amount-negative">
                                        ₪{{ number_format($bill->total_amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $bill->creator->name ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Purchases') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-negative">
                                    ₪{{ number_format($summary['total_purchases'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Damaged Bills Section - SEPARATE MAJOR TOPIC -->
        @if ($damaged_bills->count() > 0)
            <div class="section-card p-6 mb-8 page-break">
                <h2 class="text-3xl font-bold mb-6 text-red-800 border-b-2 border-red-300 pb-2">⚠️
                    {{ __('messages.Damaged Bills') }} - {{ __('messages.Losses & Damages') }}
                    ({{ $damaged_bills->count() }} bills)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Bill #') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Items') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Loss Value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($damaged_bills as $bill)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium">#{{ $bill->id }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $bill->created_at->format('M d, Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        @foreach ($bill->products as $product)
                                            <div class="text-sm">{{ $product->name }}
                                                ({{ $product->pivot->quantity }}×)</div>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold amount-negative">
                                        ₪{{ number_format(
                                            $bill->products->sum(function ($product) {
                                                return $product->pivot->cost_price * $product->pivot->quantity;
                                            }),
                                            2,
                                        ) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Damage Loss') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-negative">
                                    ₪{{ number_format($summary['total_damaged_loss'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Expenses Section -->
        @if ($expenses->count() > 0)
            <div class="section-card p-6 mb-8 page-break">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">💸 {{ __('messages.Expenses') }}
                    ({{ $expenses->count() }} entries)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Title') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Amount') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($expenses as $expense)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($expense->expense_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $expense->title }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold amount-negative">
                                        ₪{{ number_format($expense->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $expense->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Expenses') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-negative">
                                    ₪{{ number_format($summary['total_expenses'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Customer Payments Section -->
        @if ($customer_payments->count() > 0)
            <div class="section-card p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">💰
                    {{ __('messages.Customer Payments') }} ({{ $customer_payments->count() }} payments)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Customer') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Type') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Amount') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Note') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($customer_payments as $payment)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $payment->created_at->format('M d, Y H:i') }}</td>
                                    <td class="px-4 py-3">{{ $payment->customer->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 capitalize">{{ $payment->type }}</td>
                                    <td
                                        class="px-4 py-3 whitespace-nowrap font-semibold {{ $payment->amount > 0 ? 'amount-positive' : 'amount-negative' }}">
                                        ₪{{ number_format(abs($payment->amount), 2) }}
                                    </td>
                                    <td class="px-4 py-3">{{ $payment->note ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Customer Payments') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-positive">
                                    ₪{{ number_format($summary['total_customer_payments'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Supplier Payments Section -->
        @if ($supplier_payments->count() > 0)
            <div class="section-card p-6 mb-8 page-break">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">🏪
                    {{ __('messages.Supplier Payments') }} ({{ $supplier_payments->count() }} payments)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Supplier') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Type') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Amount') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Note') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($supplier_payments as $payment)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">{{ $payment->supplier->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 capitalize">{{ $payment->type }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold amount-negative">
                                        ₪{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $payment->note ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Supplier Payments') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-negative">
                                    ₪{{ number_format($summary['total_supplier_payments'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Employee Payments Section -->
        @if ($employee_payments->count() > 0)
            <div class="section-card p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">👥
                    {{ __('messages.Employee Payments') }} ({{ $employee_payments->count() }} payments)</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Employee') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    {{ __('messages.Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($employee_payments as $payment)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">{{ $payment->employee->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold amount-negative">
                                        ₪{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-4 py-3 font-semibold text-right">
                                    {{ __('messages.Total Employee Payments') }}:</td>
                                <td class="px-4 py-3 font-bold text-lg amount-negative">
                                    ₪{{ number_format($summary['total_employee_payments'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- Final Summary -->
        <div class="section-card p-6 mb-8 bg-gradient-to-r from-gray-800 to-gray-900 text-white">
            <h2 class="text-2xl font-bold mb-6 border-b border-gray-600 pb-2">📈 {{ __('messages.Final Summary') }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">{{ __('messages.Total Revenue') }}</h3>
                    <p class="text-2xl font-bold text-green-400">₪{{ number_format($summary['total_revenue'], 0) }}
                    </p>
                </div>

                <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">{{ __('messages.Total Expenses') }}</h3>
                    <p class="text-2xl font-bold text-red-400">₪{{ number_format($summary['total_costs'], 0) }}</p>
                </div>

                <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">{{ __('messages.Net Profit') }}</h3>
                    <p
                        class="text-2xl font-bold {{ $summary['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                        ₪{{ number_format($summary['net_profit'], 0) }}
                    </p>
                </div>

                <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">{{ __('messages.Net Cash Flow') }}</h3>
                    <p
                        class="text-2xl font-bold {{ $summary['net_cash_flow'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                        ₪{{ number_format($summary['net_cash_flow'], 0) }}
                    </p>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-600">
                <p class="text-sm opacity-75 text-center">
                    {{ __('messages.Report generated on') }} {{ $generated_at }} {{ __('messages.by') }}
                    {{ $generated_by }}
                </p>
            </div>
        </div>

    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 1000);
        // };
    </script>
</body>

</html>
