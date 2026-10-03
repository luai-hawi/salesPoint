<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('pos.listing.title')" :subtitle="__('pos.listing_subtitle')">
            <a href="{{ route('dashboard') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('pos.create_bill') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card :title="__('pos.held_bills_full')" :subtitle="__('pos.listing_subtitle')">
                @if ($heldBills->isEmpty())
                    <x-ui.empty :title="__('pos.listing.empty')" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('pos.listing.name') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('pos.customer') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('pos.listing.items') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('pos.listing.total') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('pos.listing.who') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('pos.listing.when') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('pos.listing.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($heldBills as $heldBill)
                                    <tr @class(['bg-amber-50' => $heldBill['is_stale']])>
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            {{ $heldBill['label'] ?: __('pos.held_bill') }}
                                            @if ($heldBill['is_stale'])
                                                <div class="mt-1 text-xs text-amber-700">{{ __('pos.held_age_warning') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">
                                            {{ $heldBill['customer_name'] ?: __('pos.unknown_customer') }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $heldBill['items_count'] }}</td>
                                        <td class="px-4 py-3 text-gray-600">₪{{ number_format($heldBill['total'], 2) }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $heldBill['held_by'] ?: '—' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $heldBill['created_at_human'] }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <form method="POST" action="{{ route('pos.held.resume', $heldBill['id']) }}">
                                                    @csrf
                                                    <button type="submit"
                                                        class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                                                        {{ __('pos.resume') }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('pos.held.destroy', $heldBill['id']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">
                                                        {{ __('pos.delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
