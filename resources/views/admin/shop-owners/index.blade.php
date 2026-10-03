<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.shops')" :subtitle="__('admin.filters.needs_attention')">
            <a href="{{ route('admin.shop-owners.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.titles.create_shop') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">{{ __('admin.fields.search') }}</label>
                        <input type="search" name="search" value="{{ request('search') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">{{ __('admin.fields.status') }}</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['all', 'active', 'has_to_pay', 'trial', 'trial_ended', 'disabled', 'needs_attention'] as $filter)
                                <option value="{{ $filter === 'all' ? '' : $filter }}" @selected(request('status', 'all') === $filter || (request('status') === null && $filter === 'all'))>{{ __('admin.filters.' . $filter) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">{{ __('admin.actions.filter') }}</label>
                        <select name="sort" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="latest" @selected(request('sort') === 'latest')>{{ __('admin.sort.latest') }}</option>
                            <option value="name" @selected(request('sort') === 'name')>{{ __('admin.fields.shop_name') }}</option>
                            <option value="next_payment" @selected(request('sort') === 'next_payment')>{{ __('admin.fields.next_payment') }}</option>
                            <option value="last_activity" @selected(request('sort') === 'last_activity')>{{ __('admin.fields.last_activity') }}</option>
                            <option value="usage" @selected(request('sort') === 'usage')>{{ __('admin.fields.usage') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.filter') }}</button>
                        <a href="{{ route('admin.shop-owners.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.clear') }}</a>
                    </div>
                </form>
            </x-ui.card>

            <div class="flex flex-wrap gap-2">
                @foreach ($counts as $filter => $count)
                    <a href="{{ route('admin.shop-owners.index', array_filter(array_merge(request()->query(), ['status' => $filter === 'all' ? null : $filter]))) }}"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm {{ (request('status', 'all') === $filter || ($filter === 'all' && ! request('status'))) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300 bg-white text-gray-700' }}">
                        <span>{{ __('admin.filters.' . $filter) }}</span>
                        <span class="rounded-full bg-white/80 px-2 py-0.5 text-xs font-semibold">{{ $count }}</span>
                    </a>
                @endforeach
            </div>

            <x-ui.card :title="__('admin.titles.shops')" :subtitle="__('admin.meta.total_count', ['count' => $users->total()])">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.shop_name') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.business_type') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.status') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.next_payment') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.usage') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('charts.admin.month_performance') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.images') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.employees') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.last_activity') }}</th>
                                <th class="px-3 py-3 text-start">{{ __('admin.fields.notes') }}</th>
                                <th class="px-3 py-3 text-start">—</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($users as $user)
                                @php
                                    $status = $statusMap[$user->id];
                                    $used = (int) ($user->products_count + $user->customers_count + $user->bills_count + $user->purchase_bills_count);
                                    $limit = $user->entry_limit;
                                    $remaining = $limit ? max(0, $limit - $used) : null;
                                    $percent = $limit ? min(100, (int) round(($used / max(1, $limit)) * 100)) : 0;
                                    $stats = $imageStats[$user->id] ?? ['count' => 0, 'bytes' => 0];
                                    $perf = $performance->get((int) $user->id) ?? \App\Services\Admin\ShopPerformanceService::emptySummary();
                                @endphp
                                <tr class="align-top">
                                    <td class="px-3 py-3">
                                        <a href="{{ route('admin.shop-owners.show', $user) }}" class="font-semibold text-indigo-700">{{ $user->name }}</a>
                                        <div class="text-xs text-gray-500">{{ $user->owner_name ?: '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->phone_number ?: '—' }}</div>
                                    </td>
                                    <td class="px-3 py-3"><x-ui.badge tone="gray">{{ __('admin.types.' . $user->businessRole()) }}</x-ui.badge></td>
                                    <td class="px-3 py-3">
                                        <x-ui.badge :tone="$status['tone']">{{ $status['label'] }}</x-ui.badge>
                                        @if ($status['reason'])
                                            <div class="mt-1 text-xs text-red-600">{{ $status['reason'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <div>{{ $status['next_payment_date'] ?: '—' }}</div>
                                        @if ($status['days_left'] !== null)
                                            <div class="text-xs text-gray-500">{{ $status['days_left'] }}</div>
                                        @endif
                                        @if ($status['amount'] !== null)
                                            <div class="text-xs text-gray-500">{{ $currencies->format($status['amount'], $status['currency']) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="text-xs">{{ $used }} / {{ $limit ?: '∞' }} @if (! is_null($remaining)) ({{ $remaining }}) @endif</div>
                                        @if ($limit)
                                            <div class="mt-1 h-2 w-28 overflow-hidden rounded-full bg-gray-100"><div class="h-2 {{ $percent >= 100 ? 'bg-red-500' : ($percent >= 90 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $percent }}%"></div></div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-xs">
                                        <div><span class="text-gray-500">{{ __('charts.sales') }}:</span> <span class="font-semibold tabular-nums">{{ number_format($perf['sales_month'], 2) }}</span></div>
                                        <div><span class="text-gray-500">{{ __('charts.gross_profit') }}:</span> <span @class(['font-semibold tabular-nums', 'text-emerald-700' => $perf['profit_month'] > 0, 'text-rose-700' => $perf['profit_month'] < 0])>{{ number_format($perf['profit_month'], 2) }}</span></div>
                                        <div class="text-gray-400">{{ __('charts.admin.bills_count', ['count' => $perf['bills_month']]) }}@if ($perf['margin'] !== null) · {{ $perf['margin'] }}%@endif</div>
                                    </td>
                                    <td class="px-3 py-3 text-xs">
                                        <div>{{ $stats['count'] }}</div>
                                        <div class="text-gray-500">{{ \App\Services\Admin\ShopStorageService::humanBytes($stats['bytes']) }}</div>
                                    </td>
                                    <td class="px-3 py-3">{{ $user->employees_count }}</td>
                                    <td class="px-3 py-3 text-xs">{{ $user->last_activity_at ? \Carbon\Carbon::parse($user->last_activity_at)->diffForHumans() : '—' }}</td>
                                    <td class="px-3 py-3">
                                        <form method="POST" action="{{ route('admin.shop-owners.note', $user) }}" class="space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <textarea name="admin_notes" rows="2" class="w-52 rounded-lg border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">{{ $user->admin_notes }}</textarea>
                                            <button class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.save_note') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-col gap-2">
                                            <a href="{{ route('admin.shop-owners.show', $user) }}" class="text-xs font-semibold text-indigo-700">{{ __('admin.actions.details') }}</a>
                                            <a href="{{ route('admin.shop-owners.edit', $user) }}" class="text-xs font-semibold text-gray-700">{{ __('admin.actions.edit') }}</a>
                                            <form method="POST" action="{{ route('admin.shop-owners.toggle-status', $user) }}">@csrf<button class="text-xs font-semibold {{ $user->role === 'disabled' ? 'text-green-700' : 'text-red-700' }}">{{ $user->role === 'disabled' ? __('admin.actions.enable') : __('admin.actions.disable') }}</button></form>
                                            <form method="POST" action="{{ route('admin.shop-owners.impersonate', $user) }}">@csrf<button class="text-xs font-semibold text-gray-700">{{ __('admin.actions.login_as') }}</button></form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="px-3 py-10"><x-ui.empty :title="__('admin.titles.shops')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $users->links() }}</div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
