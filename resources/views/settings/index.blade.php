@php
    // FORCE locale setting - this is a temporary fix to test
    $sessionLocale = session('locale', 'en');
    if (in_array($sessionLocale, ['en', 'ar'])) {
        app()->setLocale($sessionLocale);
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                {{ __('messages.Settings') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Image Limit Information (Read-Only) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        {{ __('messages.Image Upload Limit') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('messages.Your current image upload limit set by the administrator') }}
                    </p>
                </div>

                <div class="p-6">
                    <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            <div>
                                <p class="text-sm text-green-700">{{ __('messages.Maximum Images') }}</p>
                                <p class="text-xs text-green-600 mt-1">
                                    {{ __('messages.Total number of images allowed across all products') }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-green-800">{{ auth()->user()->image_limit ?? 1000 }}</p>
                            <p class="text-xs text-green-600 mt-1">{{ __('messages.images') }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mt-4">
                        {{ __('messages.To change this limit, please contact the administrator') }}
                    </p>
                </div>
            </div>

            <!-- Product Deactivation Settings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        {{ __('messages.Product Deactivation Settings') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('messages.Configure when products should be warned about or automatically deactivated.') }}
                    </p>
                </div>

                <form action="{{ route('settings.update-product') }}" method="POST" class="p-6">
                    @csrf

                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <strong
                                    class="text-red-800">{{ __('messages.Please fix the following errors:') }}</strong>
                            </div>
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-green-800">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Warning Period -->
                        <div>
                            <label for="product_warning_period" class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ __('messages.Warning Period (Months)') }}
                            </label>
                            <div class="relative">
                                <input type="number" name="product_warning_period" id="product_warning_period"
                                    value="{{ old('product_warning_period', auth()->user()->product_warning_period ?? 4) }}"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    min="1" max="24" required />
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <span class="text-gray-400 text-sm">{{ __('messages.months') }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ __('messages.After how many months out of stock should products appear in the warning list?') }}
                            </p>
                        </div>

                        <!-- Deactivation Period -->
                        <div>
                            <label for="product_deactivation_period"
                                class="block text-sm font-semibold text-gray-700 mb-2">
                                {{ __('messages.Deactivation Period (Months)') }}
                            </label>
                            <div class="relative">
                                <input type="number" name="product_deactivation_period"
                                    id="product_deactivation_period"
                                    value="{{ old('product_deactivation_period', auth()->user()->product_deactivation_period ?? 6) }}"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    min="1" max="36" required />
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <span class="text-gray-400 text-sm">{{ __('messages.months') }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ __('messages.After how many months out of stock should products be automatically deactivated?') }}
                            </p>
                        </div>
                    </div>

                    <!-- Settings Info -->
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-500 mr-3 mt-0.5 flex-shrink-0" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-blue-800 mb-1">{{ __('messages.How it works:') }}
                                </h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>•
                                        {{ __('messages.Warning Period: Products out of stock for this many months will show warnings on dashboard') }}
                                    </li>
                                    <li>•
                                        {{ __('messages.Deactivation Period: Products out of stock for this many months will be automatically deactivated') }}
                                    </li>
                                    <li>• {{ __('messages.Deactivation period must be greater than warning period') }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-8 flex items-center justify-between">
                        <a href="{{ route('dashboard') }}"
                            class="text-gray-600 hover:text-gray-800 font-medium flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            {{ __('messages.Back to Dashboard') }}
                        </a>

                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('messages.Save Settings') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Visibility Settings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-violet-50 to-purple-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-violet-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        {{ __('messages.Visibility Settings') }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('messages.Control which financial numbers are visible on different pages. These settings apply only to your account.') }}
                    </p>
                </div>

                <form action="{{ route('settings.update-visibility') }}" method="POST" class="p-6">
                    @csrf

                    @php
                        $vs = auth()->user();
                    @endphp

                    <div class="space-y-6">
                        <!-- Bills Index Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ __('messages.Bills Page') }}
                            </h4>
                            <div class="space-y-3 pl-6">
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Total Sales summary box') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The green box showing total sales amount in Bills page') }}
                                        </p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_bills_total_sales"
                                            id="show_bills_total_sales" class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_bills_total_sales') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Total Profit summary box') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The blue box showing total profit amount in Bills page') }}
                                        </p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_bills_total_profit"
                                            id="show_bills_total_profit" class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_bills_total_profit') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Number of Bills summary box') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The purple box showing total bill count in Bills page') }}
                                        </p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_bills_count" id="show_bills_count"
                                            class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_bills_count') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Bill total value column') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The total amount of each bill shown in the bills table') }}
                                        </p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_bill_total_value"
                                            id="show_bill_total_value" class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_bill_total_value') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Bill profit column') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The profit of each bill shown in the bills table') }}</p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_bill_profit_column"
                                            id="show_bill_profit_column" class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_bill_profit_column') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Dashboard Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                {{ __('messages.Dashboard / Sales Point') }}
                            </h4>
                            <div class="space-y-3 pl-6">
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Total sales today') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The today\'s sales total shown in the header and performance panel') }}
                                        </p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_dashboard_total_sales"
                                            id="show_dashboard_total_sales" class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_dashboard_total_sales') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Products Section -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                {{ __('messages.Products Page') }}
                            </h4>
                            <div class="space-y-3 pl-6">
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700">{{ __('messages.Cost price on product cards') }}</span>
                                        <p class="text-xs text-gray-500">
                                            {{ __('messages.The orange cost price box shown on each product card') }}
                                        </p>
                                    </div>
                                    <div class="relative ml-4 flex-shrink-0">
                                        <input type="checkbox" name="show_product_cost_price"
                                            id="show_product_cost_price" class="sr-only peer"
                                            {{ $vs->getVisibilitySetting('show_product_cost_price') ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-checked:bg-violet-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-violet-500 peer-focus:ring-offset-1">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button type="submit"
                            class="bg-violet-600 hover:bg-violet-700 text-white font-medium py-3 px-8 rounded-lg transition-colors flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            {{ __('messages.Save Visibility Settings') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Employee Visibility Settings (shop owners only) -->
            @if (in_array(auth()->user()->role, ['shop_owner', 'restaurant', 'merchant']) && $employeeUsers->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-amber-50 to-orange-50">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-amber-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ __('messages.Employee Visibility Settings') }}
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ __('messages.Control which financial numbers each employee account can see. Settings apply per employee.') }}
                        </p>
                    </div>

                    <div class="p-6 space-y-6">
                        @foreach ($employeeUsers as $emp)
                            <div class="border border-gray-200 rounded-xl overflow-hidden">
                                <!-- Employee Header -->
                                <div
                                    class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            {{ mb_substr($emp->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">{{ $emp->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $emp->email }}</p>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                        {{ __('messages.Employee') }}
                                    </span>
                                </div>

                                <!-- Toggles Form -->
                                <form action="{{ route('settings.employee-visibility', $emp->id) }}" method="POST"
                                    class="p-5">
                                    @csrf

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        @php
                                            $empSettings = [
                                                'show_bills_total_sales' => __('messages.Total Sales summary box'),
                                                'show_bills_total_profit' => __('messages.Total Profit summary box'),
                                                'show_bills_count' => __('messages.Number of Bills summary box'),
                                                'show_bill_total_value' => __('messages.Bill total value column'),
                                                'show_bill_profit_column' => __('messages.Bill profit column'),
                                                'show_dashboard_total_sales' => __('messages.Total sales today'),
                                                'show_product_cost_price' => __('messages.Cost price on product cards'),
                                            ];
                                        @endphp

                                        @foreach ($empSettings as $settingKey => $settingLabel)
                                            <label
                                                class="flex items-center justify-between cursor-pointer bg-gray-50 rounded-lg px-3 py-2.5">
                                                <span class="text-sm text-gray-700">{{ $settingLabel }}</span>
                                                <div class="relative ml-3 flex-shrink-0">
                                                    <input type="checkbox" name="{{ $settingKey }}"
                                                        class="sr-only peer"
                                                        {{ $emp->getVisibilitySetting($settingKey) ? 'checked' : '' }}>
                                                    <div
                                                        class="w-11 h-6 bg-gray-200 peer-checked:bg-amber-500 rounded-full transition-colors duration-200">
                                                    </div>
                                                    <div
                                                        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5">
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="mt-4 flex justify-end">
                                        <button type="submit"
                                            class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium py-2 px-5 rounded-lg transition-colors flex items-center shadow-sm">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ __('messages.Save') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Current Settings Summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                    {{ __('messages.Current Settings Summary') }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-blue-700">{{ __('messages.Warning Period:') }}</span>
                            <span class="font-bold text-blue-800">{{ auth()->user()->product_warning_period ?? 4 }}
                                {{ __('messages.months') }}</span>
                        </div>
                        <p class="text-xs text-blue-600 mt-1">
                            {{ __('messages.Shows warnings for products out of stock this long') }}</p>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-red-700">{{ __('messages.Deactivation Period:') }}</span>
                            <span
                                class="font-bold text-red-800">{{ auth()->user()->product_deactivation_period ?? 6 }}
                                {{ __('messages.months') }}</span>
                        </div>
                        <p class="text-xs text-red-600 mt-1">
                            {{ __('messages.Automatically deactivates products out of stock this long') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
