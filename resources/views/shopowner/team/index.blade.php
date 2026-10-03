<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('team.title')" :subtitle="__('team.subtitle')">
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <x-ui.card>
                <p class="text-sm text-gray-700">{{ __('team.admin_provisioning') }}</p>
            </x-ui.card>

            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.stat :label="__('team.stats.total')" :value="number_format($totalCount)" tone="indigo" />
                <x-ui.stat :label="__('team.stats.active')" :value="number_format($activeCount)" tone="green" />
                <x-ui.stat :label="__('team.stats.suspended')" :value="number_format($suspendedCount)" tone="amber" />
            </div>

            <x-ui.card>
                <form method="GET" action="{{ route('shopowner.team.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px_180px_auto]">
                    <div>
                        <label for="q" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.search') }}</label>
                        <input id="q" type="search" name="q" value="{{ $filters['search'] }}"
                            placeholder="{{ __('team.filters.search') }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="status" class="mb-1 block text-sm font-medium text-gray-700">{{ __('team.filters.status') }}</label>
                        <select id="status" name="status" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" @selected($filters['status'] === 'all')>{{ __('team.filters.all_statuses') }}</option>
                            <option value="active" @selected($filters['status'] === 'active')>{{ __('team.filters.active') }}</option>
                            <option value="suspended" @selected($filters['status'] === 'suspended')>{{ __('team.filters.suspended') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="mb-1 block text-sm font-medium text-gray-700">{{ __('team.filters.sort') }}</label>
                        <select id="sort" name="sort" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="latest" @selected($filters['sort'] === 'latest')>{{ __('team.filters.latest') }}</option>
                            <option value="oldest" @selected($filters['sort'] === 'oldest')>{{ __('team.filters.oldest') }}</option>
                            <option value="name" @selected($filters['sort'] === 'name')>{{ __('team.filters.name') }}</option>
                            <option value="activity" @selected($filters['sort'] === 'activity')>{{ __('team.filters.activity') }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            {{ __('ui.filter') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>

            @if ($employees->isEmpty())
                <x-ui.empty :title="__('team.empty.title')" :text="__('team.empty.text')">
                    <p class="text-sm text-gray-600">{{ __('team.admin_provisioning') }}</p>
                </x-ui.empty>
            @else
                <x-ui.card class="overflow-hidden" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('team.index.name') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('team.index.role') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('team.index.permissions') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('team.index.status') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('team.index.last_activity') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('team.index.created_at') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white align-top">
                                @foreach ($employees as $employee)
                                    @php
                                        $isActive = $employee->is_active !== false;
                                        $shownLabels = $employee->permission_labels->take(3);
                                        $remainingPermissions = max($employee->permission_count - $shownLabels->count(), 0);
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-4">
                                            <div class="font-semibold text-gray-900">{{ $employee->name }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $employee->email }}</div>
                                            @if ($employee->phone_number)
                                                <div class="mt-1 text-xs text-gray-500">{{ $employee->phone_number }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <x-ui.badge tone="blue">{{ $employee->detected_role_label }}</x-ui.badge>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-xs font-semibold text-gray-500">{{ $employee->permission_count }}</div>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                @foreach ($shownLabels as $label)
                                                    <x-ui.badge tone="gray">{{ $label }}</x-ui.badge>
                                                @endforeach
                                                @if ($remainingPermissions > 0)
                                                    <x-ui.badge tone="indigo">{{ __('team.index.more_permissions', ['count' => $remainingPermissions]) }}</x-ui.badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col gap-2">
                                                <x-ui.badge :tone="$isActive ? 'green' : 'amber'">
                                                    {{ $isActive ? __('team.filters.active') : __('team.filters.suspended') }}
                                                </x-ui.badge>
                                                @if ($employee->has_active_session)
                                                    <x-ui.badge tone="purple">{{ __('team.index.active_session') }}</x-ui.badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-gray-600">
                                            {{ $employee->resolved_last_activity ? \Illuminate\Support\Carbon::createFromTimestamp((int) $employee->resolved_last_activity)->diffForHumans() : __('team.index.never') }}
                                        </td>
                                        <td class="px-4 py-4 text-gray-600">{{ $employee->created_at?->format('Y-m-d') }}</td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button"
                                                    class="team-toggle rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                                    data-url="{{ route('shopowner.team.toggle', $employee) }}"
                                                    data-active="{{ $isActive ? '1' : '0' }}">
                                                    {{ $isActive ? __('team.actions.suspend') : __('team.actions.reactivate') }}
                                                </button>
                                                <a href="{{ route('shopowner.team.edit', $employee) }}"
                                                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                    {{ __('ui.edit') }}
                                                </a>
                                                <form method="POST" action="{{ route('shopowner.team.destroy', $employee) }}"
                                                    data-confirm="{{ __('team.confirm.delete_title') }}"
                                                    data-confirm-message="{{ __('team.confirm.delete_message') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                        {{ __('team.actions.delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>

                {{ $employees->links() }}
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.team-toggle').forEach(button => {
                    button.addEventListener('click', async () => {
                        const active = button.dataset.active === '1';
                        const confirmed = await SP.confirm({
                            title: active ? @js(__('team.confirm.suspend_title')) : @js(__('team.confirm.reactivate_title')),
                            message: active ? @js(__('team.confirm.suspend_message')) : @js(__('team.confirm.reactivate_message')),
                            confirmText: active ? @js(__('team.actions.suspend')) : @js(__('team.actions.reactivate')),
                            danger: active,
                        });

                        if (!confirmed) {
                            return;
                        }

                        try {
                            const response = await SP.fetchJson(button.dataset.url, {
                                method: 'POST',
                                body: {},
                            });
                            SP.toast(response.message, 'success');
                            window.location.reload();
                        } catch (error) {
                            SP.toast(@js(__('team.messages.toggle_failed')), 'error');
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
