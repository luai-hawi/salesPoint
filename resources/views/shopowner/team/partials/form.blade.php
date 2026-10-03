@php
    $visibilityOptions = __('team.visibility');
@endphp

<form action="{{ $submitRoute }}" method="POST" class="space-y-6" x-data="teamForm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-ui.card :title="__('team.form.account')" :subtitle="__('team.form.account_hint')">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('team.form.name') }}</label>
                <input id="name" type="text" name="name" value="{{ old('name', $employee?->name) }}"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('team.form.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $employee?->email) }}"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
            </div>
            <div>
                <label for="phone_number" class="block text-sm font-medium text-gray-700">{{ __('team.form.phone') }}</label>
                <input id="phone_number" type="text" name="phone_number" value="{{ old('phone_number', $employee?->phone_number) }}"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">{{ __('team.form.password') }}</label>
                <div class="mt-1 flex flex-wrap gap-2">
                    <input id="password" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="new-password"
                        class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @input="password = $event.target.value">
                    <button type="button" @click="generatePassword()"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('team.actions.generate_password') }}
                    </button>
                    <button type="button" @click="showPassword = !showPassword"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('team.actions.show_password') }}
                    </button>
                    <button type="button" @click="copyPassword()"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('team.actions.copy_password') }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('team.form.password_hint') }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('team.form.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>
    </x-ui.card>

    <x-ui.card :title="__('team.form.access')" :subtitle="__('team.form.access_hint')">
        <label class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 p-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">{{ __('team.form.active_toggle') }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('team.form.active_help') }}</p>
            </div>
            <span class="relative mt-1 inline-flex h-6 w-11 flex-shrink-0 items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="peer sr-only"
                    {{ old('is_active', $employee?->is_active === false ? false : true) ? 'checked' : '' }}>
                <span class="absolute inset-0 rounded-full bg-gray-200 transition peer-checked:bg-indigo-600"></span>
                <span class="absolute start-[2px] h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
            </span>
        </label>
    </x-ui.card>

    <x-ui.card :title="__('team.form.permissions')" :subtitle="__('team.form.permissions_hint')">
        <x-permission-picker name="permissions" :selected="old('permissions', $employee?->getPermissions() ?? [])" :showPresets="true" />
    </x-ui.card>

    <x-ui.card :title="__('team.form.visibility')" :subtitle="__('team.form.visibility_hint')">
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($visibilityKeys as $key)
                @php($option = $visibilityOptions[$key] ?? ['label' => $key, 'hint' => null])
                <label class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 p-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $option['label'] }}</p>
                        @if (! empty($option['hint']))
                            <p class="mt-1 text-xs text-gray-500">{{ $option['hint'] }}</p>
                        @endif
                    </div>
                    <span class="relative mt-1 inline-flex h-6 w-11 flex-shrink-0 items-center">
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" class="peer sr-only"
                            {{ old($key, $employee?->getVisibilitySetting($key) ?? true) ? 'checked' : '' }}>
                        <span class="absolute inset-0 rounded-full bg-gray-200 transition peer-checked:bg-indigo-600"></span>
                        <span class="absolute start-[2px] h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></span>
                    </span>
                </label>
            @endforeach
        </div>
    </x-ui.card>

    <div class="flex justify-end">
        <button type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500">
            {{ $method === 'POST' ? __('team.actions.save') : __('team.actions.update') }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('teamForm', () => ({
                password: document.getElementById('password')?.value ?? '',
                showPassword: false,
                generatePassword() {
                    const value = Math.random().toString(36).slice(-6) + Math.random().toString(36).slice(-6).toUpperCase() + '9!';
                    this.password = value;
                    document.getElementById('password').value = value;
                    document.getElementById('password_confirmation').value = value;
                    this.showPassword = true;
                },
                async copyPassword() {
                    if (!document.getElementById('password').value) {
                        return;
                    }
                    try {
                        await navigator.clipboard.writeText(document.getElementById('password').value);
                        SP.toast(@js(__('team.messages.copied')), 'success');
                    } catch (error) {
                        SP.toast(@js(__('team.messages.copy_failed')), 'error');
                    }
                },
            }));
        });
    </script>
@endpush
