@props(['tone' => 'gray'])

@php
    $tones = [
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-200',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'green' => 'bg-green-50 text-green-700 ring-green-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'purple' => 'bg-purple-50 text-purple-700 ring-purple-200',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset ' . ($tones[$tone] ?? $tones['gray'])]) }}>{{ $slot }}</span>
