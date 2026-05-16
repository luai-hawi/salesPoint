@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-4">
            <a href="{{ route('admin.dashboard') }}"
                class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                    </path>
                </svg>
            </a>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('messages.Edit User: ') . $shopOwner->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.Edit User Information') }}</h3>
                </div>

                <form action="{{ route('admin.shop-owners.update', $shopOwner->id) }}" method="POST"
                    class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Name -->
                    <div>
                        <label for="name"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Shop Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $shopOwner->name) }}"
                            required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Owner Name -->
                    <div>
                        <label for="owner_name"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Owner Name') }}</label>
                        <input type="text" name="owner_name" id="owner_name"
                            value="{{ old('owner_name', $shopOwner->owner_name) }}"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('owner_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Email Address') }}</label>
                        <input type="email" name="email" id="email"
                            value="{{ old('email', $shopOwner->email) }}" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phone_number"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Phone Number') }}</label>
                        <input type="text" name="phone_number" id="phone_number"
                            value="{{ old('phone_number', $shopOwner->phone_number) }}"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.Enter phone number') }}">
                        @error('phone_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Subscription Cost -->
                    <div>
                        <label for="subscription_cost"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Subscription Cost ($)') }}</label>
                        <input type="number" name="subscription_cost" id="subscription_cost"
                            value="{{ old('subscription_cost', $shopOwner->subscription_cost) }}" step="0.01"
                            min="0"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.Enter subscription cost') }}">
                        @error('subscription_cost')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Image Limit -->
                    <div>
                        <label for="image_limit"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Image Upload Limit') }}</label>
                        <input type="number" name="image_limit" id="image_limit"
                            value="{{ old('image_limit', $shopOwner->image_limit ?? 1000) }}" min="0"
                            max="10000"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="1000">
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __('messages.Maximum number of images allowed across all products. Default: 1000') }}
                        </p>
                        @error('image_limit')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('messages.Password') }}
                            <span
                                class="text-xs text-gray-500">({{ __('messages.leave empty to keep current password') }})</span>
                        </label>
                        <input type="password" name="password" id="password"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.Enter new password') }}">
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.User Role') }}</label>
                        <select name="role" id="role" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                            <option value="shop_owner"
                                {{ old('role', $shopOwner->role) == 'shop_owner' ? 'selected' : '' }}>
                                {{ __('messages.Shop Owner') }}</option>
                            <option value="admin" {{ old('role', $shopOwner->role) == 'admin' ? 'selected' : '' }}>
                                {{ __('messages.Admin') }}</option>
                            <option value="disabled"
                                {{ old('role', $shopOwner->role) == 'disabled' ? 'selected' : '' }}>
                                {{ __('messages.Disabled') }}</option>
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Account Type -->
                    <div>
                        <label for="account_type"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Account Type') }}</label>
                        <select name="account_type" id="account_type"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            onchange="toggleTempFields()">
                            <option value="full"
                                {{ old('account_type', $shopOwner->account_type ?? 'temp') == 'full' ? 'selected' : '' }}>
                                {{ __('messages.Full Account') }}</option>
                            <option value="temp"
                                {{ old('account_type', $shopOwner->account_type ?? 'temp') == 'temp' ? 'selected' : '' }}>
                                {{ __('messages.Temporary (Trial)') }}</option>
                        </select>
                        @error('account_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Temp Period Days -->
                    <div id="temp_period_field"
                        class="{{ old('account_type', $shopOwner->account_type ?? 'temp') == 'temp' ? '' : 'hidden' }}">
                        <label for="temp_period_days"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Trial Period (Days)') }}</label>
                        <input type="number" name="temp_period_days" id="temp_period_days"
                            value="{{ old('temp_period_days', $calculatedTrialPeriod ?? 7) }}" min="0"
                            max="365"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="7">
                        @error('temp_period_days')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Extend Expiry Date -->
                    @if ($shopOwner->account_type === 'temp' && $shopOwner->temp_expires_at)
                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <h4 class="text-sm font-medium text-yellow-800 mb-2">
                                {{ __('messages.Current Expiry Date:') }}
                                {{ $shopOwner->temp_expires_at->format('M j, Y') }}</h4>
                            <div class="flex items-end space-x-4">
                                <div class="flex-1">
                                    <label for="extend_days"
                                        class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Extend Expiry By (Days)') }}</label>
                                    <input type="number" name="extend_days" id="extend_days" value="0"
                                        min="-365" max="365"
                                        class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                                        placeholder="7">
                                </div>
                                <button type="submit" name="extend_expiry" value="1"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg font-medium transition-colors duration-200">
                                    {{ __('messages.Extend Expiry') }}
                                </button>
                            </div>
                        </div>
                    @endif

                    <script>
                        function toggleTempFields() {
                            const accountType = document.getElementById('account_type').value;
                            const tempField = document.getElementById('temp_period_field');
                            const licenseField = document.getElementById('license_expiry_field');
                            if (accountType === 'temp') {
                                tempField.classList.remove('hidden');
                                if (licenseField) licenseField.classList.add('hidden');
                            } else {
                                tempField.classList.add('hidden');
                                if (licenseField) licenseField.classList.remove('hidden');
                            }
                        }
                    </script>

                    <!-- License Expiry Date (full accounts only) -->
                    <div id="license_expiry_field"
                        class="{{ old('account_type', $shopOwner->account_type ?? 'temp') == 'full' ? '' : 'hidden' }}">
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <h4 class="text-sm font-semibold text-blue-800 mb-3">
                                {{ __('messages.License (Full Account)') }}
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label for="license_expires_at"
                                        class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.License Expiry Date') }}</label>
                                    <input type="date" name="license_expires_at" id="license_expires_at"
                                        value="{{ old('license_expires_at', $shopOwner->license_expires_at ? $shopOwner->license_expires_at->format('Y-m-d') : '') }}"
                                        class="w-full border border-gray-300 px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                                    @if ($shopOwner->license_expires_at)
                                        @php $daysLeft = (int) now()->diffInDays($shopOwner->license_expires_at, false); @endphp
                                        <p
                                            class="text-xs mt-1 {{ $daysLeft < 0 ? 'text-red-600 font-semibold' : ($daysLeft <= 30 ? 'text-orange-600' : 'text-gray-500') }}">
                                            @if ($daysLeft < 0)
                                                {{ __('messages.Expired') }} {{ abs($daysLeft) }}
                                                {{ __('messages.days ago') }}
                                            @elseif ($daysLeft === 0)
                                                {{ __('messages.Expires today') }}
                                            @else
                                                {{ $daysLeft }} {{ __('messages.days remaining') }}
                                            @endif
                                        </p>
                                    @else
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ __('messages.No license expiry date set') }}</p>
                                    @endif
                                </div>
                                @if ($shopOwner->last_payment_months || $shopOwner->last_payment_amount)
                                    <div class="text-xs text-blue-700 bg-blue-100 rounded p-2">
                                        @if ($shopOwner->last_payment_months)
                                            <span>{{ __('messages.Last payment:') }}
                                                <strong>{{ $shopOwner->last_payment_months }}
                                                    {{ __('messages.months') }}</strong></span>
                                        @endif
                                        @if ($shopOwner->last_payment_amount)
                                            <span class="ml-2">—
                                                ₪{{ number_format($shopOwner->last_payment_amount, 2) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Current Status Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Current Status:') }}</h4>
                        <div class="flex items-center space-x-2">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $shopOwner->role === 'admin'
                                    ? 'bg-red-100 text-red-800'
                                    : ($shopOwner->role === 'disabled'
                                        ? 'bg-gray-100 text-gray-800'
                                        : 'bg-green-100 text-green-800') }}">
                                {{ ucfirst(str_replace('_', ' ', $shopOwner->role)) }}
                            </span>
                            <span class="text-sm text-gray-600">{{ __('messages.since') }}
                                {{ $shopOwner->created_at->format('M j, Y') }}</span>
                        </div>

                        @if ($shopOwner->role === 'shop_owner' && $shopOwner->employees_count > 0)
                            <p class="text-sm text-yellow-600 mt-2">
                                ⚠️ {{ __('messages.This shop owner has') }} {{ $shopOwner->employees_count }}
                                {{ __('messages.Changing role will affect their access.') }}
                            </p>
                        @endif
                    </div>

                    {{-- ═══════════════════════════════════════════════════════
                         PLAN CONTROLS
                         ▸ Quick-preset buttons (Tier 1–4) fill the form via JS.
                         ▸ Feature checkboxes = blocked_features[] values.
                         ▸ Entry limit = max combined record count (null = ∞).
                    ═══════════════════════════════════════════════════════ --}}
                    <div class="border border-indigo-200 rounded-xl overflow-hidden">
                        {{-- Header --}}
                        <div
                            class="px-5 py-3 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-indigo-100 flex items-center justify-between flex-wrap gap-3">
                            <h4 class="text-sm font-semibold text-indigo-800 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                {{ __('messages.Plan Controls') }}
                            </h4>

                            {{-- Quick-preset buttons --}}
                            <div class="flex flex-wrap gap-2">
                                @php
                                    /* tier presets: [blocked_features[], entry_limit|null] */
                                    $tierPresets = [
                                        1 => [['installments', 'sales_promotions', 'financial_dashboard'], 20000],
                                        2 => [['installments', 'sales_promotions'], 50000],
                                        3 => [['installments'], 300000],
                                        4 => [[], null],
                                    ];
                                    $tierColors = [
                                        1 => 'bg-gray-100 text-gray-700 hover:bg-gray-200 border-gray-300',
                                        2 => 'bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-300',
                                        3 => 'bg-purple-100 text-purple-700 hover:bg-purple-200 border-purple-300',
                                        4 => 'bg-indigo-600 text-white hover:bg-indigo-700 border-indigo-600',
                                    ];
                                @endphp
                                @foreach ($tierPresets as $t => [$blocked, $limit])
                                    <button type="button"
                                        onclick="applyTierPreset({{ json_encode($blocked) }}, {{ json_encode($limit) }})"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors {{ $tierColors[$t] }}">
                                        {{ __('messages.Tier') }} {{ $t }}
                                        @if ($t === 4)
                                            &nbsp;★
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="p-5 space-y-5">
                            {{-- Feature Toggles --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                    {{ __('messages.Blocked Features') }}
                                    <span
                                        class="normal-case font-normal text-gray-400 ml-1">({{ __('messages.checked = blocked for this shop') }})</span>
                                </p>

                                @php
                                    $currentBlocked = $shopOwner->blocked_features ?? [];
                                    $featureList = [
                                        'installments' => __('messages.feature_installments'),
                                        'sales_promotions' => __('messages.feature_sales_promotions'),
                                        'financial_dashboard' => __('messages.feature_financial_dashboard'),
                                    ];
                                    $featureDesc = [
                                        'installments' => __('messages.feature_desc_installments'),
                                        'sales_promotions' => __('messages.feature_desc_sales_promotions'),
                                        'financial_dashboard' => __('messages.feature_desc_financial_dashboard'),
                                    ];
                                @endphp

                                <div class="space-y-2" id="feature-checkboxes">
                                    @foreach ($featureList as $key => $label)
                                        <label
                                            class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 cursor-pointer transition-colors group has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                                            <input type="checkbox" name="blocked_features[]"
                                                value="{{ $key }}" id="feature_{{ $key }}"
                                                {{ in_array($key, $currentBlocked) ? 'checked' : '' }}
                                                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-red-500 focus:ring-red-400 flex-shrink-0">
                                            <div class="min-w-0">
                                                <span
                                                    class="text-sm font-medium text-gray-800 group-has-[:checked]:text-red-700">
                                                    {{ $label }}
                                                </span>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $featureDesc[$key] }}</p>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Entry Limit --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                    {{ __('messages.Entry Limit') }}
                                </p>
                                <p class="text-xs text-gray-400 mb-2">{{ __('messages.entry_limit_desc') }}</p>
                                <div class="flex items-center gap-3">
                                    <input type="number" name="entry_limit" id="entry_limit"
                                        value="{{ old('entry_limit', $shopOwner->entry_limit ?? '') }}"
                                        min="0" placeholder="{{ __('messages.entry_limit_placeholder') }}"
                                        class="w-48 border border-gray-300 px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors text-sm">
                                    <span class="text-xs text-gray-500">{{ __('messages.entry_limit_unit') }}</span>
                                </div>
                                @error('entry_limit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                {{-- Hint row --}}
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="text-xs text-gray-400">{{ __('messages.entry_limit_hints') }}:</span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('messages.Tier') }}
                                        1 → 20,000</span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ __('messages.Tier') }}
                                        2 → 50,000</span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">{{ __('messages.Tier') }}
                                        3 → 300,000</span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">{{ __('messages.Tier') }}
                                        4 → {{ __('messages.Unlimited') }}</span>
                                </div>

                                {{-- Current usage (if data exists) --}}
                                @php
                                    $currentUsage = $shopOwner->getEntryUsage();
                                    $entryLimitVal = $shopOwner->entry_limit;
                                @endphp
                                @if ($currentUsage > 0)
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ __('messages.Current usage') }}:
                                        <strong class="text-gray-700">{{ number_format($currentUsage) }}</strong>
                                        @if ($entryLimitVal)
                                            / {{ number_format($entryLimitVal) }}
                                            ({{ $shopOwner->getEntryUsagePercent() }}%)
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <script>
                        function applyTierPreset(blocked, limit) {
                            // Uncheck all feature boxes first
                            document.querySelectorAll('#feature-checkboxes input[type="checkbox"]').forEach(function(cb) {
                                cb.checked = blocked.includes(cb.value);
                            });
                            // Set entry limit
                            var limitInput = document.getElementById('entry_limit');
                            limitInput.value = (limit === null || limit === undefined) ? '' : limit;
                        }
                    </script>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.dashboard') }}"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                            {{ __('messages.Cancel') }}
                        </a>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                            {{ __('messages.Update User') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Quick Actions for Shop Owners -->
            @if ($shopOwner->role === 'shop_owner')
                <div class="mt-6 bg-white shadow-lg rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.Quick Actions') }}</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <a href="{{ route('admin.shop-owners.show', $shopOwner->id) }}"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
                                {{ __('messages.View Shop Details') }}
                            </a>
                            <form action="{{ route('admin.shop-owners.toggle-status', $shopOwner->id) }}"
                                method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200"
                                    onclick="return confirm('{{ __('messages.Are you sure you want to') }} {{ $shopOwner->role === 'shop_owner' ? __('messages.disable') : __('messages.enable') }} {{ __('messages.this shop owner?') }}')">
                                    {{ $shopOwner->role === 'shop_owner' ? __('messages.Disable Shop') : __('messages.Enable Shop') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
