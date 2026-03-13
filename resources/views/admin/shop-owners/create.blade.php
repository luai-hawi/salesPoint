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
                {{ __('messages.Add New User') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-lg rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('messages.User Information') }}</h3>
                </div>

                <form action="{{ route('admin.shop-owners.store') }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    <!-- Name -->
                    <div>
                        <label for="name"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Shop Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="Enter shop name">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Owner Name -->
                    <div>
                        <label for="owner_name"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Owner Name') }}</label>
                        <input type="text" name="owner_name" id="owner_name" value="{{ old('owner_name') }}"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="Enter owner name">
                        @error('owner_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Email Address') }}</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.Enter email address') }}">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phone_number"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Phone Number') }}</label>
                        <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number') }}"
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
                            value="{{ old('subscription_cost', 300) }}" step="0.01" min="0"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="Enter subscription cost">
                        @error('subscription_cost')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Password') }}</label>
                        <input type="text" name="password" id="password" value="password" required
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="{{ __('messages.Enter password') }}">
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.User Role') }}</label>
                        <select name="role" id="role" required
                            class="w-full border border-gray-300 px-8 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                            <option value="">{{ __('messages.Select a role') }}</option>
                            <option value="shop_owner" {{ old('role') == 'shop_owner' ? 'selected' : '' }}>
                                {{ __('messages.Shop Owner') }}
                            </option>
                            <option value="restaurant" {{ old('role') == 'restaurant' ? 'selected' : '' }}>
                                {{ __('messages.Restaurant') }}
                            </option>
                            <option value="merchant" {{ old('role') == 'merchant' ? 'selected' : '' }}>
                                {{ __('messages.Merchant') }}
                            </option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>
                                {{ __('messages.Admin') }}</option>
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
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200">
                            <option value="temp" {{ old('account_type', 'temp') == 'temp' ? 'selected' : '' }}>
                                {{ __('messages.Temporary (Trial)') }}</option>
                            <option value="full" {{ old('account_type') == 'full' ? 'selected' : '' }}>{{ __('messages.Full Account') }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('messages.Temporary accounts will be marked as expired after the trial period') }}
                        </p>
                        @error('account_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Temp Period Days -->
                    <div id="temp_period_container">
                        <label for="temp_period_days" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Trial Period (Days)') }}</label>
                        <input type="number" name="temp_period_days" id="temp_period_days"
                            value="{{ old('temp_period_days', 7) }}" min="1" max="365"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="Enter number of days">
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('messages.Account will expire after this many days from creation') }}
                        </p>
                        @error('temp_period_days')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Image Limit -->
                    <div>
                        <label for="image_limit"
                            class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.Image Limit') }}</label>
                        <input type="number" name="image_limit" id="image_limit"
                            value="{{ old('image_limit', 100) }}" min="0" max="10000"
                            class="w-full border border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors duration-200"
                            placeholder="Enter image limit">
                        @error('image_limit')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role Descriptions -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('messages.Role Descriptions:') }}
                        </h4>
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div>
                                    <span class="font-medium">{{ __('messages.Shop Owner:') }}</span>
                                    {{ __('messages.Can manage their own shop, products, employees, and sales data.') }}
                                </div>
                            </div>
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div>
                                    <span class="font-medium">{{ __('messages.Restaurant:') }}</span>
                                    {{ __('messages.Can manage orders, customers, and payments with restaurant-specific features.') }}
                                </div>
                            </div>
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div>
                                    <span class="font-medium">{{ __('messages.Merchant:') }}</span>
                                    {{ __('messages.Can manage online sales, inventory, and customer orders for e-commerce.') }}
                                </div>
                            </div>
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 bg-red-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div>
                                    <span class="font-medium">{{ __('messages.Admin:') }}</span>
                                    {{ __('messages.Full system access, can manage all shop owners and their data.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.dashboard') }}"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                            {{ __('messages.Cancel') }}
                        </a>
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200">
                            {{ __('messages.Create User') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
