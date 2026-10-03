@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="min-w-0">
        <h2 class="truncate text-xl font-bold leading-tight text-gray-900 sm:text-2xl">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
