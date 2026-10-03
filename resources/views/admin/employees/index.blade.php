<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.employees')" :subtitle="__('permissions.picker.title')">
            <a href="{{ route('admin.employees.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.titles.create_employee') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            <x-ui.card>
                <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="md:col-span-2"><input name="search" value="{{ request('search') }}" placeholder="{{ __('admin.fields.search') }}" class="w-full rounded-lg border-gray-300"></div>
                    <div>
                        <select name="shop" class="w-full rounded-lg border-gray-300">
                            <option value="">{{ __('admin.filters.all') }}</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected((int) request('shop') === $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.filter') }}</button>
                        <a href="{{ route('admin.employees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.clear') }}</a>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('admin.titles.employees')" :subtitle="__('admin.meta.total_count', ['count' => $employees->total()])">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.owner_name') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.email') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.shop_name') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('permissions.picker.title') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('admin.fields.last_activity') }}</th>
                                <th class="px-3 py-2 text-start">—</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($employees as $employee)
                                <tr>
                                    <td class="px-3 py-2">{{ $employee->name }}</td>
                                    <td class="px-3 py-2">{{ $employee->email }}<div class="text-xs text-gray-500">{{ $employee->phone_number ?: '—' }}</div></td>
                                    <td class="px-3 py-2">{{ $employee->shopOwner?->name ?: '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600">{{ implode(', ', $employee->getPermissions()) ?: '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ isset($lastActivity[$employee->id]) ? \Carbon\Carbon::parse($lastActivity[$employee->id])->diffForHumans() : '—' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex gap-3">
                                            <a href="{{ route('admin.employees.edit', $employee) }}" class="text-xs font-semibold text-indigo-700">{{ __('admin.actions.edit') }}</a>
                                            <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-700">{{ __('admin.actions.delete') }}</button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $employees->links() }}</div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
