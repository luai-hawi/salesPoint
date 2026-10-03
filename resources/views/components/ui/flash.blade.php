@php
    $__flash = [
        'success' => ['bg-green-50 border-green-200 text-green-800', session('success')],
        'info' => ['bg-blue-50 border-blue-200 text-blue-800', session('info')],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-800', session('warning')],
        'error' => ['bg-red-50 border-red-200 text-red-800', session('error')],
    ];
@endphp

@foreach ($__flash as $__type => [$__classes, $__message])
    @if ($__message)
        <div role="alert" class="{{ $__classes }} mb-4 flex items-start gap-2 rounded-lg border px-4 py-3 text-sm" x-data="{ open: true }" x-show="open">
            <span class="flex-1">{{ $__message }}</span>
            <button type="button" class="opacity-60 hover:opacity-100" @click="open = false" aria-label="{{ __('ui.close') }}">&times;</button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $__error)
                <li>{{ $__error }}</li>
            @endforeach
        </ul>
    </div>
@endif
