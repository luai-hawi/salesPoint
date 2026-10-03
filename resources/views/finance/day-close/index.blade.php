<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('finance.day_close.title')" :subtitle="$blind ? __('finance.day_close.blind_subtitle') : __('finance.day_close.subtitle')">
            @unless ($blind)
                <a href="{{ route('finance.day-close.print', ['date' => $date, 'popup' => 1]) }}" target="_blank" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('finance.common.print') }}</a>
            @endunless
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <x-ui.card>
                <form method="GET" action="{{ route('finance.day-close.index') }}" class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.date') }}</label>
                        <input type="date" name="date" value="{{ $date }}" @if ($blind) max="{{ \App\Support\ShopTime::today(auth()->user()->ownerId()) }}" @endif class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.common.filter') }}</button>
                    </div>
                </form>
            </x-ui.card>

            @if ($blind)
            <x-ui.card :title="__('finance.day_close.save_closing')">
                <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                    {{ __('finance.day_close.blind_hint') }}
                </div>
                @if ($existingForDate && (int) $existingForDate->closed_by !== (int) auth()->id())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {{ __('finance.day_close.already_closed_by_other') }}
                    </div>
                @else
                    @if ($existingForDate)
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            {{ __('finance.day_close.blind_already_saved', ['amount' => number_format((float) $existingForDate->counted_cash, 2)]) }}
                        </div>
                    @endif
                    <form method="POST" action="{{ route('finance.day-close.store') }}" class="grid gap-4 lg:grid-cols-3">
                        @csrf
                        <input type="hidden" name="closing_date" value="{{ $date }}">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="blind-counted-cash">{{ __('finance.day_close.counted_cash') }}</label>
                            <input id="blind-counted-cash" type="number" step="0.01" min="0" name="counted_cash" required inputmode="decimal"
                                value="{{ old('counted_cash', $existingForDate?->counted_cash) }}"
                                class="w-full rounded-lg border-gray-300 text-lg font-semibold">
                            @error('counted_cash')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('closing_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="blind-notes">{{ __('finance.common.notes') }}</label>
                            <input id="blind-notes" type="text" name="notes" maxlength="4000" value="{{ old('notes', $existingForDate?->notes) }}"
                                class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div class="lg:col-span-3">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.day_close.save_closing') }}</button>
                        </div>
                    </form>
                @endif
            </x-ui.card>
            @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    __('finance.day_close.sales_total') => $summary['sales_total'],
                    __('finance.day_close.credit_sales') => $summary['credit_sales'],
                    __('finance.day_close.expected_cash') => $summary['expected_cash'] ?? 0,
                    __('finance.day_close.damaged_loss') => $summary['damaged_loss'],
                ] as $label => $value)
                    <x-ui.card><div class="text-sm text-gray-500">{{ $label }}</div><div class="mt-2 text-2xl font-bold">₪{{ number_format($value, 2) }}</div></x-ui.card>
                @endforeach
            </div>

            <x-ui.card :title="__('finance.day_close.received_by_method')">
                <div class="grid gap-4 md:grid-cols-4">
                    @foreach ($summary['received_by_method'] as $method => $value)
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-sm text-gray-500">{{ __('finance.methods.' . $method) }}</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">₪{{ number_format($value, 2) }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card :title="__('finance.day_close.save_closing')">
                <form method="POST" action="{{ route('finance.day-close.store') }}" class="grid gap-4 lg:grid-cols-4">
                    @csrf
                    <input type="hidden" name="closing_date" value="{{ $date }}">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.day_close.expected_cash') }}</label>
                        <input type="text" value="₪{{ number_format($summary['expected_cash'] ?? 0, 2) }}" disabled class="w-full rounded-lg border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.day_close.counted_cash') }}</label>
                        <input type="number" step="0.01" name="counted_cash" required class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('finance.common.notes') }}</label>
                        <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="lg:col-span-4">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('finance.day_close.save_closing') }}</button>
                    </div>
                </form>
            </x-ui.card>
            @endif

            <x-ui.card :title="__('finance.day_close.past_closings')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('finance.common.date') }}</th>
                                @unless ($blind)
                                    <th class="px-3 py-2 text-start">{{ __('finance.day_close.expected_cash') }}</th>
                                @endunless
                                <th class="px-3 py-2 text-start">{{ __('finance.day_close.counted_cash') }}</th>
                                @unless ($blind)
                                    <th class="px-3 py-2 text-start">{{ __('finance.day_close.variance') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('finance.day_close.closed_by') }}</th>
                                @endunless
                                <th class="px-3 py-2 text-start">{{ __('finance.common.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($closings as $closing)
                                <tr>
                                    <td class="px-3 py-2">{{ $closing->closing_date?->format('Y-m-d') }}</td>
                                    @unless ($blind)
                                        <td class="px-3 py-2">₪{{ number_format((float) $closing->expected_cash, 2) }}</td>
                                    @endunless
                                    <td class="px-3 py-2">₪{{ number_format((float) $closing->counted_cash, 2) }}</td>
                                    @unless ($blind)
                                        <td class="px-3 py-2"><x-ui.badge :tone="$closing->variance == 0 ? 'green' : 'amber'">₪{{ number_format((float) $closing->variance, 2) }}</x-ui.badge></td>
                                        <td class="px-3 py-2">{{ $closing->closer?->name ?? '—' }}</td>
                                    @endunless
                                    <td class="max-w-xs truncate px-3 py-2 text-gray-600" title="{{ $closing->notes }}">{{ $closing->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $blind ? 3 : 6 }}" class="px-3 py-6 text-center text-gray-500">{{ __('finance.common.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $closings->links() }}</div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
