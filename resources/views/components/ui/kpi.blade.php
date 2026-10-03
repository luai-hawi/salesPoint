@props([
    'label',
    'value',
    'hint' => null,
    'delta' => null,
    'invert' => false,
    'tone' => 'indigo',
    'icon' => null,
])

@php
    $tones = [
        'indigo' => ['ring' => 'from-indigo-500 to-indigo-600', 'soft' => 'bg-indigo-50 text-indigo-600'],
        'green' => ['ring' => 'from-emerald-500 to-emerald-600', 'soft' => 'bg-emerald-50 text-emerald-600'],
        'amber' => ['ring' => 'from-amber-400 to-amber-500', 'soft' => 'bg-amber-50 text-amber-600'],
        'red' => ['ring' => 'from-rose-500 to-rose-600', 'soft' => 'bg-rose-50 text-rose-600'],
        'blue' => ['ring' => 'from-sky-500 to-sky-600', 'soft' => 'bg-sky-50 text-sky-600'],
        'purple' => ['ring' => 'from-violet-500 to-violet-600', 'soft' => 'bg-violet-50 text-violet-600'],
        'gray' => ['ring' => 'from-slate-400 to-slate-500', 'soft' => 'bg-slate-100 text-slate-600'],
    ];
    $palette = $tones[$tone] ?? $tones['indigo'];
    $icons = [
        'cash' => 'M2.25 18.75a60.07 60.07 0 0115.8 2.1c.73.2 1.45-.34 1.45-1.1V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.38c0-.62.5-1.12 1.13-1.12H20.25M2.25 6v9m18-10.5v.75c0 .41.34.75.75.75h.75m-1.5-1.5h.38c.62 0 1.12.5 1.12 1.13v9.75c0 .62-.5 1.12-1.13 1.12h-.37m1.5-1.5h-.75a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.38a1.13 1.13 0 01-1.12-1.13V15m1.5 1.5v-.75a.75.75 0 00-.75-.75H2.25m13.5-3a3 3 0 11-6 0 3 3 0 016 0zm3 0h.01M5.25 12h.01',
        'trend' => 'M2.25 18L9 11.25l4.3 4.3a11.95 11.95 0 015.81-5.52l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.94',
        'down' => 'M2.25 6L9 12.75l4.29-4.29a11.95 11.95 0 015.81 5.52l2.65 1.18m0 0l-5.94 2.28m5.94-2.28l-2.28-5.94',
        'receipt' => 'M9 14.25l6-6m4.5-3.49V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.76c0-1.1.8-2.05 1.9-2.18a48.5 48.5 0 0111.2 0c1.1.13 1.9 1.08 1.9 2.18zM9.75 9h.01m4.49 4.5h.01',
        'percent' => 'M9 14.25l6-6M9.75 9h.01M14.25 13.5h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'box' => 'M20.25 7.5l-.63 10.63a2.25 2.25 0 01-2.24 2.12H6.62a2.25 2.25 0 01-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.13v1.5c0 .62.5 1.12 1.13 1.12z',
        'users' => 'M15 19.13a9.38 9.38 0 002.63.37 9.34 9.34 0 004.12-.95 4.13 4.13 0 00-7.53-2.49M15 19.13v-.01c0-1.12-.29-2.17-.79-3.08M15 19.13v.1A12.32 12.32 0 018.62 21c-2.33 0-4.51-.65-6.37-1.77v-.11a6.38 6.38 0 0111.96-3.08M12 6.38a3.38 3.38 0 11-6.75 0 3.38 3.38 0 016.75 0zm8.25 2.25a2.63 2.63 0 11-5.25 0 2.63 2.63 0 015.25 0z',
        'wallet' => 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3',
        'return' => 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3',
    ];
    $positive = $delta !== null && $delta >= 0;
    $good = $invert ? ! $positive : $positive;
@endphp

<div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:shadow-md']) }}>
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $palette['ring'] }}"></div>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <p class="mt-2 truncate text-2xl font-bold text-gray-900" dir="ltr">{{ $value }}</p>
        </div>
        @if ($icon && isset($icons[$icon]))
            <span class="{{ $palette['soft'] }} inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] }}"/></svg>
            </span>
        @endif
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
        @if ($delta !== null)
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold',
                'bg-emerald-50 text-emerald-700' => $good,
                'bg-rose-50 text-rose-700' => ! $good,
            ]) dir="ltr">
                {{ $positive ? '▲' : '▼' }} {{ number_format(abs($delta), 1) }}%
            </span>
            <span class="text-gray-400">{{ __('charts.vs_previous') }}</span>
        @endif
        @if ($hint)
            <span class="truncate text-gray-500">{{ $hint }}</span>
        @endif
    </div>
</div>
