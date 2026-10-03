@props([
    'type' => 'bar',
    'labels' => [],
    'datasets' => [],
    'height' => 280,
    'currency' => '₪',
    'percent' => false,
    'horizontal' => false,
    'stacked' => false,
    'legend' => null,
    'label' => null,
])

@php
    $labels = collect($labels)->values()->all();
    $datasets = collect($datasets)->map(fn ($dataset) => array_merge($dataset, [
        'data' => collect($dataset['data'] ?? [])->map(fn ($value) => round((float) $value, 2))->values()->all(),
    ]))->values()->all();
    $hasData = count($labels) > 0 && collect($datasets)->contains(fn ($dataset) => collect($dataset['data'])->contains(fn ($value) => $value != 0));
    $spec = array_filter([
        'type' => $type,
        'labels' => $labels,
        'datasets' => $datasets,
        'currency' => $percent ? '' : $currency,
        'percent' => $percent,
        'horizontal' => $horizontal,
        'stacked' => $stacked,
        'legend' => $legend,
    ], fn ($value) => $value !== null);
@endphp

<div {{ $attributes->merge(['class' => 'relative w-full']) }} style="height: {{ (int) $height }}px">
    @if ($hasData)
        <canvas data-chart="{{ json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}" role="img" aria-label="{{ $label }}"></canvas>
    @else
        <div class="flex h-full flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-sm text-gray-400">
            <svg class="mb-2 h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>
            {{ __('charts.no_data') }}
        </div>
    @endif
</div>
