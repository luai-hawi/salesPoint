<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.subscriptions')" :subtitle="__('admin.fields.status')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <div class="flex flex-wrap gap-2">
                @foreach (['overdue', 'due_7', 'due_14', 'due_30', 'due_60', 'due_90', 'no_date', 'all'] as $window)
                    <a href="{{ route('admin.shop-owners.expiring-licenses', ['window' => $window]) }}" class="rounded-full border px-3 py-1.5 text-sm {{ request('window', 'all') === $window ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300 bg-white text-gray-700' }}">{{ __('admin.filters.' . $window) }}</a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($totals as $currency => $total)
                    <x-ui.card :title="$currency">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span>{{ __('admin.dashboard.overdue') }}</span><span>{{ number_format($total['overdue'], 2) }}</span></div>
                            <div class="flex justify-between"><span>{{ __('admin.dashboard.next_30_days') }}</span><span>{{ number_format($total['expected'], 2) }}</span></div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <x-ui.card :title="__('admin.titles.subscriptions')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.shop_name') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.status') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.next_payment') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.subscription_cost') }}</th>
                                <th class="px-3 py-2 text-start">—</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($shops as $shop)
                                @php($status = $statusMap[$shop->id])
                                <tr>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('admin.shop-owners.show', $shop) }}" class="font-medium text-indigo-700">{{ $shop->name }}</a>
                                        <div class="text-xs text-gray-500">{{ $shop->phone_number ?: '—' }}</div>
                                    </td>
                                    <td class="px-3 py-2"><x-ui.badge :tone="$status['tone']">{{ $status['label'] }}</x-ui.badge></td>
                                    <td class="px-3 py-2">{{ $status['next_payment_date'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ number_format((float) ($status['amount'] ?? 0), 2) }} {{ $status['currency'] }}</td>
                                    <td class="px-3 py-2"><a href="https://wa.me/{{ preg_replace('/\D+/', '', $shop->phone_number ?? '') }}" target="_blank" rel="noreferrer" class="text-xs font-semibold text-green-700">{{ __('admin.actions.whatsapp') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $shops->links() }}</div>
            </x-ui.card>

            <x-ui.card :title="__('admin.titles.online_menu_subscriptions')">
                <div class="space-y-2 text-sm">
                    @forelse ($menuExpiredUsers as $row)
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="font-medium text-gray-900">{{ $row->restaurant_name ?: $row->name }}</div>
                            <div class="text-xs text-gray-500">{{ $row->email }} — {{ $row->expires_at }}</div>
                        </div>
                    @empty
                        <x-ui.empty :title="__('admin.titles.online_menu_subscriptions')" />
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
