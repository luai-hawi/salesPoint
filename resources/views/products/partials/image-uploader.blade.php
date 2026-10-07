@props([
    'inputId' => 'pictures',
    'existing' => [],
    'remainingSlots' => 0,
    'maxUploadKb' => 8192,
    'maxTotal' => null,
])

@php
    $uploadConstraints = \App\Services\ImageProcessor::uploadConstraints();
    $strings = __('products_ui.image_editor');
    $config = [
        'inputId' => $inputId,
        'existing' => array_values($existing),
        'remainingSlots' => $remainingSlots,
        'maxTotal' => $maxTotal,
        'maxUploadKb' => $uploadConstraints['file_kilobytes'],
        'maxRequestKb' => (int) floor($uploadConstraints['request_bytes'] / 1024),
        'maxFilesPerRequest' => $uploadConstraints['max_files'],
        'storageBaseUrl' => rtrim(asset('storage'), '/'),
        'strings' => $strings,
        'buttons' => [
            'remove' => __('products_ui.buttons.remove'),
            'edit' => __('products_ui.buttons.edit_image'),
            'apply' => __('products_ui.buttons.apply_image'),
            'cancel' => __('products_ui.buttons.cancel'),
            'keep' => __('products_ui.buttons.keep_original'),
            'reset' => __('products_ui.buttons.reset'),
            'undo' => __('products_ui.buttons.undo'),
            'rotateLeft' => __('products_ui.buttons.rotate_left'),
            'rotateRight' => __('products_ui.buttons.rotate_right'),
        ],
    ];
@endphp

<div
    class="space-y-4"
    data-image-uploader
    data-image-uploader-config='@json($config)'
>
    <input
        id="{{ $inputId }}"
        type="file"
        name="pictures[]"
        accept="image/jpeg,image/png,image/gif,image/webp"
        multiple
        class="sr-only"
        data-image-input
    >

    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-indigo-600 shadow-sm">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <h4 class="mt-3 text-sm font-semibold text-gray-900">{{ __('products_ui.image_editor.choose') }}</h4>
        <p class="mt-1 text-xs text-gray-500">{{ __('products_ui.image_editor.drop') }}</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
            <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" data-open-file-dialog>
                {{ __('products_ui.image_editor.choose') }}
            </button>
            <span class="text-xs text-gray-500">
                {{ __('products_ui.labels.remaining_images') }}:
                <span class="font-semibold text-gray-900" data-remaining-slot-count>{{ $remainingSlots }}</span>
            </span>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            {{ __('products_ui.help.upload_limits', ['file' => $uploadConstraints['file_label'], 'request' => $uploadConstraints['request_label'], 'count' => $uploadConstraints['max_files']]) }}
        </p>
        <p class="mt-2 hidden text-xs text-amber-600" data-image-warning></p>
    </div>

    <div class="hidden" data-existing-inputs></div>
    <div class="hidden" data-order-inputs></div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-image-grid></div>

    <template data-image-item-template>
        <div class="group rounded-xl border border-gray-200 bg-white p-2 shadow-sm" draggable="true" data-image-item>
            <div class="relative overflow-hidden rounded-lg bg-gray-100">
                <img alt="" class="h-32 w-full object-cover" data-image-preview>
                <span class="absolute start-2 top-2 rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white" data-primary-badge></span>
                <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 transition group-hover:opacity-100">
                    <button type="button" class="rounded-full bg-white/90 p-1 text-gray-800 hover:bg-white" data-edit-image>
                        <span class="sr-only">{{ __('products_ui.buttons.edit_image') }}</span>
                        ✎
                    </button>
                    <button type="button" class="rounded-full bg-white/90 p-1 text-red-600 hover:bg-white" data-remove-image>
                        <span class="sr-only">{{ __('products_ui.buttons.remove') }}</span>
                        ✕
                    </button>
                </div>
            </div>
            <div class="mt-2 flex items-center justify-between gap-2 text-xs text-gray-500">
                <span class="truncate" data-image-kind></span>
                <span class="shrink-0" data-image-size></span>
            </div>
        </div>
    </template>

    <div class="fixed inset-0 z-[120] hidden items-center justify-center bg-black/60 p-4" data-image-editor-modal x-cloak>
        <div class="flex max-h-[95vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h4 class="text-lg font-semibold text-gray-900">{{ __('products_ui.image_editor.title') }}</h4>
                    <p class="text-sm text-gray-500" data-editor-queue>{{ __('products_ui.image_editor.queue', ['current' => 1, 'total' => 1]) }}</p>
                </div>
                <button type="button" class="text-2xl leading-none text-gray-400 hover:text-gray-600" data-close-editor>&times;</button>
            </div>
            <div class="flex flex-1 flex-col overflow-hidden lg:flex-row">
                <div class="relative flex-shrink-0 bg-gray-900 p-4 lg:w-2/3">
                    <canvas class="h-[40vh] w-full rounded-xl bg-gray-800 lg:h-[60vh]" style="touch-action: none;" data-editor-canvas tabindex="0"></canvas>
                    <div class="pointer-events-none absolute inset-6 rounded-xl border border-dashed border-white/60" data-crop-overlay></div>
                    <div class="absolute bottom-6 start-6 rounded-lg bg-black/60 px-3 py-2 text-xs text-white">
                        {{ __('products_ui.image_editor.keyboard_help') }}
                    </div>
                </div>
                <div class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex-1 overflow-y-auto p-5">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-rotate-left>{{ __('products_ui.buttons.rotate_left') }}</button>
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-rotate-right>{{ __('products_ui.buttons.rotate_right') }}</button>
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-flip-horizontal>{{ __('products_ui.image_editor.flip_horizontal') }}</button>
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-flip-vertical>{{ __('products_ui.image_editor.flip_vertical') }}</button>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">{{ __('products_ui.image_editor.free') }}</label>
                                <div class="grid grid-cols-5 gap-2">
                                    @foreach (['free', '1:1', '4:3', '3:4', '16:9'] as $preset)
                                        <button type="button" class="rounded-lg border border-gray-300 px-2 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" data-crop-preset="{{ $preset }}">
                                            {{ $preset === 'free' ? __('products_ui.image_editor.free') : $preset }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            @foreach ([
                                'brightness' => ['min' => -100, 'max' => 100, 'step' => 1],
                                'contrast' => ['min' => -100, 'max' => 100, 'step' => 1],
                                'saturation' => ['min' => -100, 'max' => 100, 'step' => 1],
                                'rotation' => ['min' => -45, 'max' => 45, 'step' => 1],
                            ] as $slider => $meta)
                                <div>
                                    <label for="{{ $inputId }}_{{ $slider }}" class="mb-1 block text-sm font-medium text-gray-700">
                                        {{ __('products_ui.image_editor.' . ($slider === 'rotation' ? 'fine_rotation' : $slider)) }}
                                    </label>
                                    <input
                                        id="{{ $inputId }}_{{ $slider }}"
                                        type="range"
                                        min="{{ $meta['min'] }}"
                                        max="{{ $meta['max'] }}"
                                        step="{{ $meta['step'] }}"
                                        value="0"
                                        class="w-full"
                                        data-editor-slider="{{ $slider }}"
                                    >
                                </div>
                            @endforeach

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-auto-enhance>{{ __('products_ui.image_editor.auto_enhance') }}</button>
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-reset-editor>{{ __('products_ui.buttons.reset') }}</button>
                                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-undo-editor>{{ __('products_ui.buttons.undo') }}</button>
                            </div>

                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="rounded border-gray-300" data-fit-square>
                                {{ __('products_ui.image_editor.fit_square') }}
                            </label>

                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-700">{{ __('products_ui.image_editor.live_preview') }}</p>
                                <canvas class="w-full rounded-xl border border-gray-200 bg-gray-50" data-preview-canvas></canvas>
                                <p class="mt-2 text-xs text-gray-500">{{ __('products_ui.image_editor.transparent_png') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 p-5">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-keep-original>{{ __('products_ui.buttons.keep_original') }}</button>
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-close-editor>{{ __('products_ui.buttons.cancel') }}</button>
                        <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" data-apply-editor>{{ __('products_ui.buttons.apply_image') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
