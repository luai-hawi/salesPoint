<!DOCTYPE html>
@php
    $reportTitle = \App\Support\ReportCatalog::label($definition['label']);
    $isProductReport = ($definition['group'] ?? null) === 'products';
    $returnUrl = route('reports.index', request()->except(['popup'])) . ($isProductReport ? '#product-reports' : '');
    $filterNotes = array_filter([
        request('product_search') ? __('charts.products.search') . ': ' . request('product_search') : null,
        request('category') ? __('charts.products.columns.category') . ': ' . (request('category') === '__none' ? __('charts.products.uncategorized') : request('category')) : null,
    ]);
@endphp
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; padding: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 9px; border: 1px solid #ddd; text-align: start; }
        th { background: #f3f4f6; }
        tfoot td { font-weight: bold; background: #f9fafb; }
        .muted { color: #6b7280; font-size: 13px; }
        .no-print { display: flex; justify-content: space-between; margin-bottom: 20px; }
        button, a { padding: 10px; cursor: pointer; }
        @media print { .no-print { display: none !important; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <x-report-actions :return-url="$returnUrl" />
    <h1>{{ $reportTitle }}</h1>
    @if (! empty($definition['description']))
        <p class="muted">{{ __($definition['description']) }}</p>
    @endif
    @if ($definition['dated'] ?? true)
        <p>{{ $from }} — {{ $to }}</p>
    @else
        <p>{{ __('charts.products.as_of', ['date' => \App\Support\ShopTime::today(auth()->user()->ownerId())]) }}</p>
    @endif
    @if ($filterNotes)
        <p class="muted">{{ implode(' · ', $filterNotes) }}</p>
    @endif
    @if ($customer)
        <p>{{ $customer->name }} · {{ $customer->phone }} · {{ __('messages.Balance') }}: {{ number_format($customer->balance, 2) }}</p>
    @endif
    <p>{{ __('messages.Total Records') }}: {{ $rows->count() }}</p>
    <div class="no-print"><a href="{{ route('reports.export', request()->except(['popup'])) }}">{{ __('finance.reports.export_csv') }}</a></div>
    <table>
        <thead><tr>@foreach ($definition['columns'] as $label)<th>{{ \App\Support\ReportCatalog::label($label) }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>@foreach ($definition['columns'] as $key => $label)<td>{{ \App\Support\ReportCatalog::value($row, $key) }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($definition['columns']) }}">{{ __('finance.common.no_data') }}</td></tr>
        @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot><tr>
                @foreach ($definition['columns'] as $key => $label)
                    <td>
                        @if ($loop->first) {{ __('messages.Total') }}
                        @elseif (\App\Support\ReportCatalog::isSummable($key))
                            {{ \App\Support\ReportCatalog::footerValue($rows, $key) }}
                        @endif
                    </td>
                @endforeach
            </tr></tfoot>
        @endif
    </table>
</body>
</html>
