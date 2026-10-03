<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('team.form.edit_title')" :subtitle="__('team.form.edit_subtitle')">
            <a href="{{ route('shopowner.team.index') }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                {{ __('team.actions.back') }}
            </a>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />

            @if (session('generated_password'))
                <x-ui.card :title="__('team.form.created_password_label')" :subtitle="__('team.form.password_flash')">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <code class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-900">{{ session('generated_password') }}</code>
                        <button type="button" class="copy-password rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            data-value="{{ session('generated_password') }}">
                            {{ __('team.actions.copy_password') }}
                        </button>
                    </div>
                </x-ui.card>
            @endif

            @include('shopowner.team.partials.form', [
                'submitRoute' => route('shopowner.team.update', $employee),
                'method' => 'PUT',
            ])

            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.card :title="__('team.form.security')" :subtitle="__('team.form.security_hint')">
                    <div class="space-y-6">
                        <form action="{{ route('shopowner.team.reset-password', $employee) }}" method="POST" class="space-y-4">
                            @csrf
                            <div class="grid gap-4 md:grid-cols-2" x-data="{ visible: false }">
                                <div>
                                    <label for="reset_password" class="block text-sm font-medium text-gray-700">{{ __('team.form.new_password') }}</label>
                                    <div class="mt-1 flex gap-2">
                                        <input id="reset_password" type="password" name="password" autocomplete="new-password"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            x-bind:type="visible ? 'text' : 'password'">
                                        <button type="button" @click="visible = !visible"
                                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                            {{ __('team.actions.show_password') }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label for="reset_password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('team.form.new_password_confirmation') }}</label>
                                    <input id="reset_password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                            <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ __('team.actions.reset_password') }}
                            </button>
                        </form>

                        <form action="{{ route('shopowner.team.logout', $employee) }}" method="POST" id="logout-form">
                            @csrf
                            <button type="button" id="force-logout-button"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('team.actions.force_logout') }}
                            </button>
                        </form>

                        @if ($teammates->isNotEmpty())
                            <form action="{{ route('shopowner.team.copy-permissions', $employee) }}" method="POST" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="source_user_id" class="block text-sm font-medium text-gray-700">{{ __('team.form.copy_from') }}</label>
                                    <select id="source_user_id" name="source_user_id"
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('team.form.copy_from_placeholder') }}</option>
                                        @foreach ($teammates as $teammate)
                                            <option value="{{ $teammate->id }}">{{ $teammate->name }} — {{ $teammate->email }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('team.form.copy_hint') }}</p>
                                </div>
                                <button type="submit"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    {{ __('team.actions.copy_permissions') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('team.form.activity')" :subtitle="__('team.form.activity_hint')">
                    @if ($activityLogs->isEmpty())
                        <x-ui.empty :title="__('team.form.no_activity')" />
                    @else
                        <div class="space-y-3">
                            @foreach ($activityLogs as $activity)
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <p class="text-sm font-medium text-gray-900">{{ $activity->describe() }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $activity->created_at?->diffForHumans() }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <x-ui.card :title="__('team.form.danger')" :subtitle="__('team.form.danger_hint')">
                <form method="POST" action="{{ route('shopowner.team.destroy', $employee) }}"
                    data-confirm="{{ __('team.confirm.delete_title') }}"
                    data-confirm-message="{{ __('team.confirm.delete_message') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        {{ __('team.actions.delete') }}
                    </button>
                </form>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.copy-password').forEach(button => {
                    button.addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(button.dataset.value);
                            SP.toast(@js(__('team.messages.copied')), 'success');
                        } catch (error) {
                            SP.toast(@js(__('team.messages.copy_failed')), 'error');
                        }
                    });
                });

                const logoutButton = document.getElementById('force-logout-button');
                logoutButton?.addEventListener('click', async () => {
                    const confirmed = await SP.confirm({
                        title: @js(__('team.confirm.logout_title')),
                        message: @js(__('team.confirm.logout_message')),
                        confirmText: @js(__('team.actions.force_logout')),
                        danger: true,
                    });

                    if (confirmed) {
                        document.getElementById('logout-form').submit();
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
