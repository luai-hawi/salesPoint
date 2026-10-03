<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.titles.settings')" :subtitle="__('admin.titles.dashboard')" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-ui.flash />
            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.card :title="__('admin.titles.settings')">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.support_whatsapp') }}</label><input name="support_whatsapp" value="{{ old('support_whatsapp', $settings['support_whatsapp']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.support_phone') }}</label><input name="support_phone" value="{{ old('support_phone', $settings['support_phone']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.support_email') }}</label><input name="support_email" value="{{ old('support_email', $settings['support_email']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.default_country_code') }}</label><input name="default_country_code" value="{{ old('default_country_code', $settings['default_country_code']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.default_trial_days') }}</label><input type="number" name="default_trial_days" value="{{ old('default_trial_days', $settings['default_trial_days']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.default_subscription_cost') }}</label><input type="number" step="0.01" name="default_subscription_cost" value="{{ old('default_subscription_cost', $settings['default_subscription_cost']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.default_currency') }}</label><select name="default_currency" class="w-full rounded-lg border-gray-300">@foreach ($currencyOptions as $currency)<option value="{{ $currency['code'] }}" @selected(old('default_currency', $settings['default_currency']) === $currency['code'])>{{ $currency['code'] }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.due_soon_days') }}</label><input type="number" name="due_soon_days" value="{{ old('due_soon_days', $settings['due_soon_days']) }}" class="w-full rounded-lg border-gray-300"></div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('admin.messages.announcement')">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="announcement_enabled" value="1" @checked(old('announcement_enabled', $settings['announcement_enabled'])) class="rounded border-gray-300 text-indigo-600"> <span>{{ __('admin.fields.announcement_enabled') }}</span></label>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.announcement_tone') }}</label><select name="announcement_tone" class="w-full rounded-lg border-gray-300">@foreach (['gray','indigo','green','red','amber','blue','purple'] as $tone)<option value="{{ $tone }}" @selected(old('announcement_tone', $settings['announcement_tone']) === $tone)>{{ $tone }}</option>@endforeach</select></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.announcement_expires_at') }}</label><input type="date" name="announcement_expires_at" value="{{ old('announcement_expires_at', $settings['announcement_expires_at']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.announcement_title_ar') }}</label><input name="announcement_title_ar" value="{{ old('announcement_title_ar', $settings['announcement_title_ar']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.announcement_title_en') }}</label><input name="announcement_title_en" value="{{ old('announcement_title_en', $settings['announcement_title_en']) }}" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.announcement_body_ar') }}</label><textarea name="announcement_body_ar" rows="4" class="w-full rounded-lg border-gray-300">{{ old('announcement_body_ar', $settings['announcement_body_ar']) }}</textarea></div>
                        <div><label class="mb-1 block text-sm font-medium">{{ __('admin.fields.announcement_body_en') }}</label><textarea name="announcement_body_en" rows="4" class="w-full rounded-lg border-gray-300">{{ old('announcement_body_en', $settings['announcement_body_en']) }}</textarea></div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('admin.fields.reminder_template_ar')">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><textarea name="reminder_template_ar" rows="5" class="w-full rounded-lg border-gray-300">{{ old('reminder_template_ar', $settings['reminder_template_ar']) }}</textarea></div>
                        <div><textarea name="reminder_template_en" rows="5" class="w-full rounded-lg border-gray-300">{{ old('reminder_template_en', $settings['reminder_template_en']) }}</textarea></div>
                    </div>
                </x-ui.card>

                <div class="flex justify-end">
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
