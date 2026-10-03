@props([
    'products',
    'canManage' => false,
    'canDelete' => false,
])

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3 text-start">
                    @if ($canManage)
                        <input type="checkbox" class="rounded border-gray-300" @change="toggleAll($event.target.checked)">
                    @endif
                </th>
                <th class="px-4 py-3 text-start">{{ __('products_ui.table.product') }}</th>
                <th class="px-4 py-3 text-start">{{ __('products_ui.table.barcode') }}</th>
                <th class="px-4 py-3 text-start">{{ __('products_ui.table.pricing') }}</th>
                <th class="px-4 py-3 text-start">{{ __('products_ui.table.stock') }}</th>
                <th class="px-4 py-3 text-start">{{ __('products_ui.labels.status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('products_ui.labels.actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @foreach ($products as $product)
                @php
                    $pictures = is_array($product->pictures) ? $product->pictures : json_decode($product->pictures ?? '[]', true);
                    $primary = is_array($pictures) ? ($pictures[0] ?? null) : null;
                    $lowStock = $product->quantity > 0 && $product->quantity <= ($product->low_stock_threshold ?? 0);
                @endphp
                <tr class="align-top">
                    <td class="px-4 py-4 text-start">
                        @if ($canManage)
                            <input type="checkbox" class="rounded border-gray-300" value="{{ $product->id }}" x-model="selectedIds">
                        @endif
                    </td>
                    <td class="px-4 py-4 text-start">
                        <div class="flex items-start gap-3">
                            <div class="h-14 w-14 overflow-hidden rounded-lg bg-gray-100">
                                @if ($primary)
                                    <img src="{{ asset('storage/' . ltrim($primary, '/')) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-gray-400">—</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ $product->name }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <span>#{{ $product->id }}</span>
                                    @if ($product->category)
                                        <x-ui.badge tone="gray">{{ $product->category }}</x-ui.badge>
                                    @endif
                                    @if ($product->variant_group_id)
                                        <x-ui.badge tone="purple">{{ __('products_ui.table.variant_group') }}</x-ui.badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-start font-mono text-xs text-gray-600">{{ $product->barcode ?: '—' }}</td>
                    <td class="px-4 py-4 text-start">
                        <p class="font-semibold text-gray-900">₪{{ number_format((float) $product->selling_price, 2) }}</p>
                        <p class="text-xs text-gray-500">₪{{ number_format((float) $product->cost_price, 2) }}</p>
                    </td>
                    <td class="px-4 py-4 text-start">
                        <p class="font-semibold text-gray-900">{{ number_format((float) $product->quantity, 2) }}</p>
                        <p class="text-xs text-gray-500">{{ __('products_ui.labels.low_stock_threshold') }}: {{ number_format((int) ($product->low_stock_threshold ?? 0)) }}</p>
                    </td>
                    <td class="px-4 py-4 text-start">
                        @if (! $product->is_active)
                            <x-ui.badge tone="gray">{{ __('products_ui.filters.inactive') }}</x-ui.badge>
                        @elseif ($product->quantity <= 0)
                            <x-ui.badge tone="red">{{ __('products_ui.status.out_of_stock') }}</x-ui.badge>
                        @elseif ($lowStock)
                            <x-ui.badge tone="amber">{{ __('products_ui.status.low_stock') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="green">{{ __('products_ui.status.in_stock') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-end">
                        <div class="flex flex-wrap justify-end gap-2">
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
                                    <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-medium text-white hover:bg-red-700" data-confirm="{{ __('products_ui.buttons.delete') }}">
                                        {{ __('products_ui.buttons.delete') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
