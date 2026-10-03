<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.activity.title')" :subtitle="__('finance.activity.subtitle')">
            <a href="{{ route('shopowner.activity.export', request()->query()) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.common.export') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <x-ui.card :title="__('finance.activity.filters')">
                <form method="GET" action="{{ route('shopowner.activity.index') }}" class="grid gap-4 lg:grid-cols-6">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.activity.actor') }}</label>
                        <select name="actor_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">{{ __('finance.common.all') }}</option>
                            @foreach ($actors as $actor)
                                <option value="{{ $actor->id }}" @selected(request('actor_id') == $actor->id)>{{ $actor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.from') }}</label><input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.to') }}</label><input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.activity.subject_type') }}</label><input type="text" name="subject_type" value="{{ request('subject_type') }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.activity.min_amount') }}</label><input type="number" step="0.01" name="min_amount" value="{{ request('min_amount') }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.search') }}</label><input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('finance.activity.search_placeholder') }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div class="lg:col-span-6"><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.common.filter') }}</button></div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('finance.activity.title')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('finance.activity.when') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.activity.actor') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.activity.action') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.activity.amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('finance.activity.risky') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($activities as $entry)
                                @php
                                    $changes = $entry->properties['changes'] ?? [];
                                    $isRisky = ($entry->subject_type === 'bill' && $entry->action === 'deleted')
                                        || ($entry->subject_type === 'product' && $entry->action === 'updated' && array_key_exists('selling_price', $changes))
                                        || ($entry->subject_type === 'bill' && $entry->action === 'updated' && abs((float) $entry->amount) >= 100);
                                @endphp
                                <tr>
                                    <td class="px-3 py-2">{{ $entry->created_at }}</td>
                                    <td class="px-3 py-2">{{ $entry->actor_name }}</td>
                                    <td class="px-3 py-2">{{ $entry->describe() }}</td>
                                    <td class="px-3 py-2">{{ $entry->amount === null ? '—' : '₪' . number_format((float) $entry->amount, 2) }}</td>
                                    <td class="px-3 py-2">
                                        <x-ui.badge :tone="$isRisky ? 'red' : 'gray'">{{ $isRisky ? __('finance.activity.risky') : __('finance.activity.normal') }}</x-ui.badge>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $activities->links() }}</div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
