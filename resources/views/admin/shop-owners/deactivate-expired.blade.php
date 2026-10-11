<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.deactivate_expired')" :subtitle="__('admin.meta.total_count', ['count' => $counts['total']])">
            <a href="{{ route('admin.shop-owners.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.cancel') }}</a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @if ($counts['total'] === 0)
                <x-ui.card>
                    <x-ui.empty :title="__('admin.filters.trial_ended')" :text="__('admin.messages.no_expired_trials')" />
                </x-ui.card>
            @else
                <form method="POST" action="{{ route('admin.shop-owners.disable-expired') }}" class="space-y-6" x-data="bulkChecklist()">
                    @csrf

                    <x-ui.card :title="__('admin.titles.deactivate_expired')" :subtitle="__('admin.messages.deactivate_expired_hint')">
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <button type="button" @click="selectAll()" class="text-xs font-semibold text-indigo-700 hover:underline">{{ __('admin.actions.select_all') }}</button>
                            <button type="button" @click="clearAll()" class="text-xs font-semibold text-gray-700 hover:underline">{{ __('admin.actions.clear_all') }}</button>
                            <span class="text-xs text-gray-500">
                                {{ __('admin.messages.selected_prefix') }} <span x-text="selectedCount"></span>
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-start">
                                            <input type="checkbox" @click="toggleAll($el)" class="rounded border-gray-300 text-indigo-600">
                                        </th>
                                        <th class="px-3 py-2 text-start">{{ __('admin.fields.shop_name') }}</th>
                                        <th class="px-3 py-2 text-start">{{ __('admin.fields.email') }}</th>
                                        <th class="px-3 py-2 text-start">{{ __('admin.fields.phone') }}</th>
                                        <th class="px-3 py-2 text-start">{{ __('admin.fields.business_type') }}</th>
                                        <th class="px-3 py-2 text-start">{{ __('admin.fields.trial_expires') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach ($accounts as $account)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <input type="checkbox" name="user_ids[]" value="{{ $account->id }}" checked class="rounded border-gray-300 text-indigo-600 checklist-item">
                                            </td>
                                            <td class="px-3 py-2 font-medium text-gray-900">{{ $account->name }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $account->email }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $account->phone_number ?: '—' }}</td>
                                            <td class="px-3 py-2"><x-ui.badge tone="gray">{{ __('admin.types.' . $account->businessRole()) }}</x-ui.badge></td>
                                            <td class="px-3 py-2 text-gray-600">{{ $account->temp_expires_at?->toDateString() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>

                    <x-ui.card :title="__('admin.messages.will_disable')" :subtitle="__('admin.messages.deactivate_expired_confirm_hint')">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">{{ __('admin.fields.confirmation') }}</label>
                            <input type="text" name="confirmation" class="w-full rounded-lg border-gray-300 md:w-96" placeholder="{{ __('admin.confirmations.bulk_deactivate_expired') }}" required>
                            <p class="text-xs text-gray-500">{{ __('admin.messages.type_confirmation_hint', ['phrase' => __('admin.confirmations.bulk_deactivate_expired')]) }}</p>
                        </div>
                    </x-ui.card>

                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">{{ __('admin.actions.disable_expired_trials') }}</button>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function bulkChecklist() {
            return {
                toggleAll(el) {
                    const checked = el.checked;
                    document.querySelectorAll('.checklist-item').forEach(cb => cb.checked = checked);
                },
                selectAll() {
                    document.querySelectorAll('.checklist-item').forEach(cb => cb.checked = true);
                },
                clearAll() {
                    document.querySelectorAll('.checklist-item').forEach(cb => cb.checked = false);
                },
                get selectedCount() {
                    return document.querySelectorAll('.checklist-item:checked').length;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>