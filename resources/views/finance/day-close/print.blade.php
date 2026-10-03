<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('finance.day_close.title') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-white p-6 text-gray-900">
    <div class="mb-4 flex items-center justify-between print:hidden">
        <button onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('finance.common.print') }}</button>
        <a href="{{ route('finance.day-close.index', ['date' => $date]) }}" onclick="if ({{ request()->boolean('popup') ? 'true' : 'false' }} || (window.opener && !window.opener.closed)) { window.close(); }" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">{{ __('ui.close') }}</a>
    </div>
    <div class="mx-auto max-w-2xl rounded-xl border border-gray-200 p-6">
        <h1 class="text-center text-2xl font-bold">{{ __('finance.day_close.title') }}</h1>
        <p class="mt-2 text-center text-sm text-gray-500">{{ $date }}</p>
        <div class="mt-6 space-y-2 text-sm">
            @foreach ([
                __('finance.day_close.sales_total') => $summary['sales_total'],
                __('finance.day_close.returns') => $summary['returns_total'],
                __('finance.day_close.credit_sales') => $summary['credit_sales'],
                __('finance.day_close.expected_cash') => $summary['expected_cash'] ?? 0,
                __('finance.day_close.counted_cash') => (float) ($closing->counted_cash ?? 0),
                __('finance.day_close.variance') => (float) ($closing->variance ?? 0),
            ] as $label => $value)
                <div class="flex justify-between border-b border-dashed border-gray-200 py-1">
                    <span>{{ $label }}</span>
                    <strong>₪{{ number_format($value, 2) }}</strong>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
