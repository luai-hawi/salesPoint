@props(['label', 'value', 'hint' => null, 'tone' => 'gray'])

@php
    $tones = [
        'gray' => 'bg-gray-50 text-gray-700',
        'indigo' => 'bg-indigo-50 text-indigo-700',
        'green' => 'bg-green-50 text-green-700',
        'red' => 'bg-red-50 text-red-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'blue' => 'bg-blue-50 text-blue-700',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <p class="mt-1 truncate text-2xl font-bold text-gray-900">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 truncate text-xs text-gray-500">{{ $hint }}</p>
            @endif
        </div>
        @if (! $slot->isEmpty())
            <span class="{{ $tones[$tone] ?? $tones['gray'] }} inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg">{{ $slot }}</span>
        @endif
    </div>
</div>
