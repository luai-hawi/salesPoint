@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('messages.Barcode Search') }}
            </h2>
            <a href="{{ route('products.index') }}"
                class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                {{ __('messages.Back to Products') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            {{ __('messages.Search Barcode in Purchase Bills') }}</h3>

                        <form method="GET" action="{{ route('products.search-barcode') }}" class="flex gap-4">
                            <div class="flex-1">
                                <input type="text" name="barcode" value="{{ old('barcode', $barcode ?? '') }}"
                                    placeholder="{{ __('messages.Enter barcode to search...') }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                            </div>
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                {{ __('messages.Search') }}
                            </button>
                        </form>
                    </div>

                    @if (isset($searched) && $searched)
                        @if ($results->count() > 0)
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4">
                                    {{ __('messages.found_results_for_barcode', ['count' => $results->count(), 'barcode' => $barcode]) }}
                                </h4>

                                <div class="overflow-x-auto">
                                    <table
                                        class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Product') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Supplier') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Purchase Date') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Quantity') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Unit Cost') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Reference') }}</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                    {{ __('messages.Barcodes') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach ($results as $result)
                                                <tr>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $result->product_name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-gray-900">
                                                            {{ $result->supplier_name }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            {{ $result->purchase_date->format('Y-m-d') }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">{{ $result->quantity }}
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            ₪{{ number_format($result->unit_cost, 2) }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            {{ $result->reference_number ?? '-' }}</div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="text-sm text-gray-900">
                                                            @if (is_array($result->barcodes))
                                                                {{ implode(', ', $result->barcodes) }}
                                                            @else
                                                                {{ $result->barcodes }}
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">
                                    {{ __('messages.No results found') }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ __('messages.No purchase bills found containing barcode: :barcode', ['barcode' => $barcode]) }}
                                </p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
