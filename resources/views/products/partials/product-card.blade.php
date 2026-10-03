@props([
    'product',
    'canManage' => false,
    'canDelete' => false,
])

@php
    $pictures = is_array($product->pictures) ? $product->pictures : json_decode($product->pictures ?? '[]', true);
    $primary = is_array($pictures) ? ($pictures[0] ?? null) : null;
    $lowStock = $product->quantity > 0 && $product->quantity <= ($product->low_stock_threshold ?? 0);
@endphp

<article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    @if ($canManage)
        <label class="mb-3 flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" class="rounded border-gray-300" value="{{ $product->id }}" x-model="selectedIds">
            {{ __('messages.Select') }} {{ $product->name }}
        </label>
    @endif
    <div class="flex items-start gap-3">
        <div class="h-20 w-20 overflow-hidden rounded-xl bg-gray-100">
            @if ($primary)
                <img src="{{ asset('storage/' . ltrim($primary, '/')) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center text-gray-400">—</div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $product->name }}</h3>
                    <p class="mt-1 text-xs text-gray-500">#{{ $product->id }}</p>
                </div>
                @if (! $product->is_active)
                    <x-ui.badge tone="gray">{{ __('products_ui.filters.inactive') }}</x-ui.badge>
                @elseif ($product->quantity <= 0)
                    <x-ui.badge tone="red">{{ __('products_ui.status.out_of_stock') }}</x-ui.badge>
                @elseif ($lowStock)
                    <x-ui.badge tone="amber">{{ __('products_ui.status.low_stock') }}</x-ui.badge>
                @else
                    <x-ui.badge tone="green">{{ __('products_ui.status.in_stock') }}</x-ui.badge>
                @endif
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600">
                <div class="rounded-lg bg-gray-50 p-2">
                    <p>{{ __('products_ui.labels.selling_price') }}</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">₪{{ number_format((float) $product->selling_price, 2) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-2">
                    <p>{{ __('products_ui.labels.cost_price') }}</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">₪{{ number_format((float) $product->cost_price, 2) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-2">
                    <p>{{ __('products_ui.labels.quantity') }}</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ number_format((float) $product->quantity, 2) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-2">
                    <p>{{ __('products_ui.labels.barcode') }}</p>
                    <p class="mt-1 truncate font-mono text-sm text-gray-900">{{ $product->barcode ?: '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        @if ($canManage)
            <button
                type="button"
                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                @click="openStockForm({ id: {{ $product->id }}, name: @js($product->name), costPrice: @js((float) $product->cost_price) })"
            >
                {{ __('products_ui.buttons.add_stock') }}
            </button>
            <a href="{{ route('products.edit', $product) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                {{ __('products_ui.buttons.edit') }}
            </a>
            <form action="{{ route('products.toggle-active', $product) }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="rounded-lg px-3 py-2 text-xs font-medium text-white {{ $product->is_active ? 'bg-amber-500 hover:bg-amber-600' : 'bg-green-600 hover:bg-green-700' }}"
                >
                    {{ $product->is_active ? __('products_ui.buttons.deactivate') : __('products_ui.buttons.activate') }}
                </button>
            </form>
        @endif
        @if ($canDelete)
            <form action="{{ route('products.destroy', $product) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700">
                    {{ __('products_ui.buttons.delete') }}
                </button>
            </form>
        @endif
    </div>
</article>
