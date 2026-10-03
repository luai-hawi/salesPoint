<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('finance.dashboard.title') }}</title>
    @vite('resources/css/app.css')
    <style>@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }</style>
</head>
<body class="bg-white text-gray-900">
    <div class="mx-auto max-w-5xl space-y-6 p-6">
        <x-report-actions :return-url="route('dashboard.financial', request()->query())" />

        <div class="rounded-xl border border-gray-200 p-6">
            <h1 class="text-2xl font-bold">{{ __('finance.dashboard.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $startDate }} → {{ $endDate }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ $generatedBy->name }} · {{ $generatedAt->format('Y-m-d H:i') }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                __('finance.dashboard.revenue') => $summary['revenue'],
                __('finance.dashboard.profit') => $summary['profit'],
                __('finance.dashboard.expenses') => $summary['expenses'],
                __('finance.dashboard.net_income') => $summary['net_income'],
            ] as $label => $value)
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-bold">₪{{ number_format($value, 2) }}</div>
                </div>
            @endforeach
        </div>

        @include('dashboard.partials.financial-details', ['printing' => true])
        @include('dashboard.partials.financial-cash-flow')

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-5">
                <h2 class="text-lg font-semibold">{{ __('finance.reports.profit_loss') }}</h2>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.revenue') }}</span><strong>₪{{ number_format($profitLoss['revenue'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.day_close.returns') }}</span><strong>₪{{ number_format($profitLoss['returns'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.reports.cogs') }}</span><strong>₪{{ number_format($profitLoss['cogs'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.profit') }}</span><strong>₪{{ number_format($profitLoss['gross_profit'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.day_close.discounts') }}</span><strong>₪{{ number_format($profitLoss['discounts'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.expenses') }}</span><strong>₪{{ number_format($profitLoss['expenses_total'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.staff_payments') }}</span><strong>₪{{ number_format($profitLoss['staff_payments'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.damaged_loss') }}</span><strong>₪{{ number_format($profitLoss['damaged_loss'], 2) }}</strong></div>
                    <div class="flex justify-between border-t pt-2"><span>{{ __('finance.reports.profit_loss') }}</span><strong>₪{{ number_format($profitLoss['net_profit'], 2) }}</strong></div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-5">
                <h2 class="text-lg font-semibold">{{ __('finance.dashboard.cash_flow') }}</h2>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.settlement_in') }}</span><strong>₪{{ number_format($summary['cash_flow']['settlement']['cash_in'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.settlement_out') }}</span><strong>₪{{ number_format($summary['cash_flow']['settlement']['cash_out'], 2) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('finance.dashboard.cash_drawer_balance') }}</span><strong>{{ $cashDrawer['cash_closing_balance'] === null ? '—' : '₪' . number_format($cashDrawer['cash_closing_balance'], 2) }}</strong></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
