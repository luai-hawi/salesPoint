@props(['title' => null, 'subtitle' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:px-5">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['px-4 py-4 sm:px-5' => $padding])>{{ $slot }}</div>
</section>
