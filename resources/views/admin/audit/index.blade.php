<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.audit')" :subtitle="__('admin.titles.dashboard')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <x-ui.card>
                <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <select name="action" class="rounded-lg border-gray-300"><option value="">{{ __('admin.filters.all') }}</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select>
                    <select name="shop" class="rounded-lg border-gray-300"><option value="">{{ __('admin.filters.all') }}</option>@foreach ($shops as $shop)<option value="{{ $shop->id }}" @selected((int) request('shop') === $shop->id)>{{ $shop->name }}</option>@endforeach</select>
                    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-gray-300">
                    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-gray-300">
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 md:col-span-4 md:w-fit">{{ __('admin.actions.filter') }}</button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('admin.titles.audit')">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr><th class="px-3 py-2 text-start">{{ __('admin.audit.action') }}</th><th class="px-3 py-2 text-start">{{ __('admin.audit.subject') }}</th><th class="px-3 py-2 text-start">{{ __('admin.audit.actor') }}</th><th class="px-3 py-2 text-start">{{ __('admin.audit.when') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="px-3 py-2">{{ $log->action }}</td>
                                    <td class="px-3 py-2">{{ $log->describe() }}</td>
                                    <td class="px-3 py-2">{{ $log->actor_name ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $log->created_at?->toDateTimeString() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $logs->links() }}</div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
