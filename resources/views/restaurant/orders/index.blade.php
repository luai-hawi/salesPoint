<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('restaurant.titles.orders')" :subtitle="__('restaurant.subtitles.orders')">
            <a href="{{ route('restaurant.tables.index') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('restaurant.titles.tables') }}
            </a>
            <a href="{{ route('kitchen.display') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ __('restaurant.buttons.open_kitchen') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.table') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.order_type') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.customer') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.status') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.total') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.opened') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('restaurant.fields.bill') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($orders as $order)
                                <tr>
                                    <td class="px-4 py-3">{{ $order->table?->name ?? $order->label }}</td>
                                    <td class="px-4 py-3">{{ __('restaurant.order_types.' . $order->order_type) }}</td>
                                    <td class="px-4 py-3">{{ $order->customer_name ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <x-ui.badge tone="{{ $order->latestTicket?->status === 'ready' ? 'amber' : ($order->status === 'paid' ? 'green' : 'indigo') }}">
                                            {{ $order->latestTicket?->status ? __('restaurant.labels.' . $order->latestTicket->status) : __('restaurant.labels.' . $order->status) }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3">₪{{ number_format((float) $order->total, 2) }}</td>
                                    <td class="px-4 py-3">{{ $order->created_at ? \App\Support\ShopTime::local($order->created_at, $order->user_id)->format('Y-m-d H:i') : '—' }}</td>
                                    <td class="px-4 py-3">{{ $order->bill_id ? '#' . $order->bill_id : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('restaurant.messages.no_orders') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $orders->links() }}
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
