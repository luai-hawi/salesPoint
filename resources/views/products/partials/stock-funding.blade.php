@props([
    'prefix' => 'funding',
    'suppliers' => collect(),
    'values' => [],
    'compact' => false,
])

@php
    $mode = old('funding_mode', $values['funding_mode'] ?? 'none') ?: 'none';
    $supplierId = old('funding_supplier_id', $values['funding_supplier_id'] ?? null);
@endphp

<div class="space-y-4" data-funding-block data-prefix="{{ $prefix }}">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-900">{{ __('products_ui.funding.title') }}</h4>
            <p class="mt-1 text-xs text-gray-500">{{ __('products_ui.help.funding') }}</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
            {{ __('products_ui.buttons.new_supplier') }}
        </a>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        @foreach (['none', 'credit', 'paid', 'partial'] as $fundingMode)
            <label class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                <input
                    type="radio"
                    class="sr-only"
                    name="funding_mode"
                    value="{{ $fundingMode }}"
                    {{ $mode === $fundingMode ? 'checked' : '' }}
                >
                <span class="block text-sm font-semibold text-gray-900">{{ __('products_ui.funding.mode_' . $fundingMode) }}</span>
                <span class="mt-1 block text-xs text-gray-500">{{ __('products_ui.help.funding_' . $fundingMode) }}</span>
            </label>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2" data-funding-extra>
        <div class="space-y-2" data-funding-supplier>
            <label for="{{ $prefix }}_supplier_id" class="block text-sm font-medium text-gray-700">
                {{ __('products_ui.labels.supplier') }}
            </label>
            <select
                id="{{ $prefix }}_supplier_id"
                name="funding_supplier_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">—</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
            @error('funding_supplier_id')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2" data-funding-method>
            <label for="{{ $prefix }}_payment_method" class="block text-sm font-medium text-gray-700">
                {{ __('products_ui.labels.payment_method') }}
            </label>
            <select
                id="{{ $prefix }}_payment_method"
                name="funding_payment_method"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">—</option>
                @foreach (['cash', 'transfer', 'card', 'check'] as $method)
                    <option value="{{ $method }}" @selected(old('funding_payment_method', $values['funding_payment_method'] ?? '') === $method)>
                        {{ __('products_ui.funding.payment_' . $method) }}
                    </option>
                @endforeach
            </select>
            @error('funding_payment_method')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2" data-funding-paid>
            <label for="{{ $prefix }}_paid_amount" class="block text-sm font-medium text-gray-700">
                {{ __('products_ui.labels.paid_amount') }}
            </label>
            <input
                id="{{ $prefix }}_paid_amount"
                type="number"
                step="0.01"
                min="0"
                name="funding_paid_amount"
                value="{{ old('funding_paid_amount', $values['funding_paid_amount'] ?? '') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                placeholder="{{ __('products_ui.placeholders.cost_price') }}"
            >
            @error('funding_paid_amount')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="{{ $prefix }}_date" class="block text-sm font-medium text-gray-700">
                {{ __('products_ui.labels.date') }}
            </label>
            <input
                id="{{ $prefix }}_date"
                type="date"
                name="funding_date"
                value="{{ old('funding_date', $values['funding_date'] ?? now()->toDateString()) }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
            >
            @error('funding_date')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="space-y-2">
        <label for="{{ $prefix }}_note" class="block text-sm font-medium text-gray-700">
            {{ __('products_ui.labels.note') }}
        </label>
        <textarea
            id="{{ $prefix }}_note"
            name="funding_note"
            rows="{{ $compact ? 2 : 3 }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
            placeholder="{{ __('products_ui.placeholders.note') }}"
        >{{ old('funding_note', $values['funding_note'] ?? '') }}</textarea>
        @error('funding_note')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
