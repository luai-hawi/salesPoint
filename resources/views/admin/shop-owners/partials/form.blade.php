@php
    $owner = $shopOwner;
    $isEdit = $owner !== null;
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('admin.fields.shop_name')">
            <div class="space-y-4">
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.shop_name') }}</label><input name="name" value="{{ old('name', $owner?->name) }}" class="w-full rounded-lg border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.owner_name') }}</label><input name="owner_name" value="{{ old('owner_name', $owner?->owner_name) }}" class="w-full rounded-lg border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.email') }}</label><input type="email" name="email" value="{{ old('email', $owner?->email) }}" class="w-full rounded-lg border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.phone') }}</label><input name="phone_number" value="{{ old('phone_number', $owner?->phone_number) }}" class="w-full rounded-lg border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.password') }}</label><input name="password" value="{{ old('password') }}" class="w-full rounded-lg border-gray-300"></div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('admin.fields.business_type')">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('admin.fields.business_type') }}</label>
                    @if ($isEdit && $owner?->role === 'disabled')
                        <input type="hidden" name="role" value="disabled">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">{{ __('admin.types.' . $owner->businessRole()) }} · {{ __('admin.statuses.disabled') }}</div>
                    @else
                        <select name="role" class="w-full rounded-lg border-gray-300">
                            @foreach (['shop_owner', 'restaurant', 'merchant'] as $role)
                                <option value="{{ $role }}" @selected(old('role', $owner?->role ?? 'shop_owner') === $role)>{{ __('admin.types.' . $role) }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('admin.fields.account_type') }}</label>
                    <select name="account_type" class="w-full rounded-lg border-gray-300">
                        @foreach (['temp', 'full'] as $type)
                            <option value="{{ $type }}" @selected(old('account_type', $owner?->account_type ?? 'temp') === $type)>{{ __('admin.types.' . $type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.trial_days') }}</label><input type="number" name="temp_period_days" value="{{ old('temp_period_days', $owner?->temp_period_days ?? $settings['default_trial_days']) }}" class="w-full rounded-lg border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.extend_days') }}</label><input type="number" name="extend_days" value="{{ old('extend_days') }}" class="w-full rounded-lg border-gray-300"></div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.license_expires_at') }}</label><input type="date" name="license_expires_at" value="{{ old('license_expires_at', optional($owner?->license_expires_at)->toDateString()) }}" class="w-full rounded-lg border-gray-300"></div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('admin.fields.subscription_cost')">
            <div class="space-y-4">
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.subscription_cost') }}</label><input type="number" step="0.01" name="subscription_cost" value="{{ old('subscription_cost', $owner?->subscription_cost ?? $settings['default_subscription_cost']) }}" class="w-full rounded-lg border-gray-300"></div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('admin.fields.currency') }}</label>
                    <select name="subscription_currency" class="w-full rounded-lg border-gray-300">
                        @foreach ($currencyOptions as $currency)
                            <option value="{{ $currency['code'] }}" @selected(old('subscription_currency', $owner?->subscription_currency ?? $settings['default_currency']) === $currency['code'])>{{ $currency['code'] }} — {{ app()->getLocale() === 'ar' ? $currency['name_ar'] : $currency['name_en'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.image_limit') }}</label><input type="number" name="image_limit" value="{{ old('image_limit', $owner?->image_limit) }}" class="w-full rounded-lg border-gray-300"></div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('admin.fields.entry_limit')">
            <div class="space-y-4">
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.entry_limit') }}</label><input type="number" name="entry_limit" value="{{ old('entry_limit', $owner?->entry_limit) }}" class="w-full rounded-lg border-gray-300"></div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('admin.fields.entry_limit_mode') }}</label>
                    <select name="entry_limit_mode" class="w-full rounded-lg border-gray-300">
                        @foreach (['off', 'warn', 'block'] as $mode)
                            <option value="{{ $mode }}" @selected(old('entry_limit_mode', $owner?->entry_limit_mode ?? 'off') === $mode)>{{ __('admin.entry_limit.modes.' . $mode) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('admin.fields.blocked_features') }}</label>
                    <input type="hidden" name="blocked_features[]" value="">
                    <p class="mb-2 text-xs text-gray-500">{{ __('admin.features_hint') }}</p>
                    <div class="space-y-2 text-sm">
                        @foreach (\App\Support\FeatureCatalog::KEYS as $feature)
                            <label class="flex items-center gap-2"><input type="checkbox" name="blocked_features[]" value="{{ $feature }}" @checked(in_array($feature, old('blocked_features', $owner?->blocked_features ?? []), true)) class="rounded border-gray-300 text-indigo-600"> <span>{{ __('admin.features.' . $feature) }}</span></label>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card :title="__('admin.fields.admin_notes')" :subtitle="__('admin.messages.shop_private_note_hint')">
        <textarea name="admin_notes" rows="5" class="w-full rounded-lg border-gray-300">{{ old('admin_notes', $owner?->admin_notes) }}</textarea>
    </x-ui.card>

    @if ($isEdit)
        <x-ui.card :title="__('admin.actions.disable')">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.disabled_reason') }}</label><input name="disabled_reason" value="{{ old('disabled_reason', $owner?->disabled_reason) }}" class="w-full rounded-lg border-gray-300"></div>
                <p class="self-end text-sm text-gray-500">{{ __('admin.messages.enable_disable_hint') }}</p>
            </div>
        </x-ui.card>
    @endif

    <div class="sticky bottom-4 z-10 flex justify-end gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
        <a href="{{ $isEdit ? route('admin.shop-owners.show', $owner) : route('admin.shop-owners.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('admin.actions.cancel') }}</a>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.save') }}</button>
    </div>
</form>
