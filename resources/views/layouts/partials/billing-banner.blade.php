@php
    $user = auth()->user();
    $owner = $user && $user->role === 'employee' ? $user->shopOwner : $user;
    $statusService = app(\App\Services\Admin\AccountStatus::class);
    $settingsService = app(\App\Services\Admin\PlatformSettings::class);
    $settings = $settingsService->all();
    $showBilling = false;
    $billingText = null;

    if ($user && $user->role !== 'admin' && $owner && $owner->isOwnerAccount()) {
        $status = $statusService->describe($owner);
        if ($status['key'] === 'payment_due_soon') {
            $showBilling = true;
            $billingText = __('admin.messages.billing_banner_due_soon', ['days' => $status['days_left'] ?? 0]);
        } elseif ($status['key'] === 'payment_overdue') {
            $showBilling = true;
            $billingText = __('admin.messages.billing_banner_overdue');
        } elseif ($status['key'] === 'trial_ending') {
            $showBilling = true;
            $billingText = __('admin.messages.billing_banner_trial_ending', ['days' => $status['days_left'] ?? 0]);
        } elseif ($status['key'] === 'trial_ended') {
            $showBilling = true;
            $billingText = __('admin.messages.billing_banner_trial_ended');
        }
    }

    $hasAnnouncement = $user
        && $user->role !== 'admin'
        && ! empty($settings['announcement_enabled'])
        && (! $settings['announcement_expires_at'] || \Carbon\Carbon::parse($settings['announcement_expires_at'])->endOfDay()->isFuture());
    $announcementTone = $settings['announcement_tone'] ?? 'blue';
    $announcementTitle = app()->getLocale() === 'ar' ? ($settings['announcement_title_ar'] ?? null) : ($settings['announcement_title_en'] ?? null);
    $announcementBody = app()->getLocale() === 'ar' ? ($settings['announcement_body_ar'] ?? null) : ($settings['announcement_body_en'] ?? null);
@endphp

@if (session('impersonator_id'))
    <div class="bg-amber-500 px-4 py-3 text-center text-sm font-semibold text-white">
        {{ __('admin.messages.viewing_as', ['shop' => session('impersonated_shop_name')]) }}
        <form method="POST" action="{{ route('admin.impersonate.stop') }}" class="ms-2 inline">@csrf<button class="underline">{{ __('admin.actions.stop_impersonation') }}</button></form>
    </div>
@endif

@if ($showBilling || $hasAnnouncement)
    <div x-data="{ open: localStorage.getItem('sp-billing-banner-{{ now()->toDateString() }}') !== 'hidden' }" x-show="open" class="border-b border-gray-200 bg-white">
        @if ($showBilling)
            <div class="bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                    <div>{{ $billingText }}</div>
                    <div class="flex items-center gap-3">
                        @if (! empty($settings['support_whatsapp']))
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $settings['support_whatsapp']) }}" class="font-semibold underline">{{ __('admin.messages.billing_banner_contact') }}</a>
                        @endif
                        <button type="button" class="text-amber-700" @click="open = false; localStorage.setItem('sp-billing-banner-{{ now()->toDateString() }}', 'hidden')">&times;</button>
                    </div>
                </div>
            </div>
        @endif
        @if ($hasAnnouncement && ($announcementTitle || $announcementBody))
            <div class="px-4 py-3 text-sm text-white {{ $announcementTone === 'red' ? 'bg-red-600' : ($announcementTone === 'green' ? 'bg-green-600' : ($announcementTone === 'amber' ? 'bg-amber-600' : ($announcementTone === 'purple' ? 'bg-purple-600' : ($announcementTone === 'indigo' ? 'bg-indigo-600' : ($announcementTone === 'gray' ? 'bg-gray-700' : 'bg-blue-600'))))) }}">
                <div class="mx-auto flex max-w-7xl items-start justify-between gap-3">
                    <div>
                        <div class="font-semibold">{{ $announcementTitle ?: __('admin.messages.announcement') }}</div>
                        @if ($announcementBody)
                            <div class="text-white/90">{{ $announcementBody }}</div>
                        @endif
                    </div>
                    <button type="button" class="text-white/80" @click="open = false; localStorage.setItem('sp-billing-banner-{{ now()->toDateString() }}', 'hidden')">&times;</button>
                </div>
            </div>
        @endif
    </div>
@endif
