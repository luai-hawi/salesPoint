@props(['title' => null, 'text' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center']) }}>
    <svg class="mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
    </svg>
    @if ($title)
        <p class="text-sm font-semibold text-gray-700">{{ $title }}</p>
    @endif
    @if ($text)
        <p class="mt-1 max-w-sm text-xs text-gray-500">{{ $text }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
