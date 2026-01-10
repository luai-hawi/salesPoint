<nav x-data="{ open: false, moreMenuOpen: false }" class="bg-white border-b border-gray-100 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="transition-transform duration-200 hover:scale-105">
                        <x-application-logo class="block fill-current text-gray-800" width="130" height="auto" />
                    </a>
                </div>

                <!-- Navigation Links - Different for Admin vs Shop Owner/Employee -->
                <div class="hidden lg:flex lg:items-center lg:ml-8">
                    @if (Auth::user()->role === 'admin')
                        <!-- Admin Navigation Links -->
                        <div class="flex space-x-1">
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')"
                                class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-blue-100 text-blue-700 border-blue-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                    </path>
                                </svg>
                                {{ __('navigation.Dashboard') }}
                            </x-nav-link>

                            <x-nav-link :href="route('admin.shop-owners.index')" :active="request()->routeIs('admin.shop-owners.*')"
                                class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('admin.shop-owners.*') ? 'bg-green-100 text-green-700 border-green-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h4a1 1 0 011 1v5m-6 0V9a1 1 0 011-1h4a1 1 0 011 1v11">
                                    </path>
                                </svg>
                                {{ __('navigation.Shop Owners') }}
                            </x-nav-link>

                            <x-nav-link :href="route('admin.employees.index')" :active="request()->routeIs('admin.employees.*')"
                                class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('admin.employees.*') ? 'bg-purple-100 text-purple-700 border-purple-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                                {{ __('navigation.All Employees') }}
                            </x-nav-link>
                        </div>
                    @else
                        <!-- Shop Owner/Employee Navigation Links -->
                        <div class="flex items-center space-x-1">
                            <!-- Core Navigation Items (Always Visible) -->
                            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')"
                                class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('dashboard') ? 'bg-blue-100 text-blue-700 border-blue-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 5a2 2 0 012-2h4a2 2 0 012 2v4H8V5z"></path>
                                </svg>
                                {{ __('navigation.Dashboard') }}
                            </x-nav-link>

                            @if (auth()->user()->role !== 'employee' ||
                                    auth()->user()->hasPermission('view_products') ||
                                    auth()->user()->hasPermission('create_products') ||
                                    auth()->user()->hasPermission('edit_products') ||
                                    auth()->user()->hasPermission('delete_products'))
                                <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.index')"
                                    class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('products.index') ? 'bg-green-100 text-green-700 border-green-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    {{ __('navigation.Products') }}
                                </x-nav-link>
                            @endif

                            @if (auth()->user()->role !== 'employee' ||
                                    auth()->user()->hasPermission('view_products') ||
                                    auth()->user()->hasPermission('create_products') ||
                                    auth()->user()->hasPermission('edit_products') ||
                                    auth()->user()->hasPermission('delete_products'))
                                <x-nav-link :href="route('products.out-of-stock')" :active="request()->routeIs('products.out-of-stock')"
                                    class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('products.out-of-stock') ? 'bg-orange-100 text-orange-700 border-orange-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z">
                                        </path>
                                    </svg>
                                    {{ __('navigation.Out of Stock') }}
                                    @php
                                        $user = auth()->user();
                                        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
                                        $warningMonths = $user->product_warning_period ?? 4;
                                        $warningCount = \App\Models\Product::where('user_id', $ownerId)
                                            ->where('quantity', 0) // Changed from '>' to '=' to match out-of-stock page
                                            ->where('is_active', true)
                                            ->whereNotNull('last_sale_date')
                                            ->where('last_sale_date', '<=', now()->subMonths($warningMonths))
                                            ->where(function ($query) {
                                                $query
                                                    ->whereNull('extended_until')
                                                    ->orWhere('extended_until', '<=', now());
                                            })
                                            ->count();
                                    @endphp
                                    @if ($warningCount > 0)
                                        <span
                                            class="mx-2 mr-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
                                            {{ $warningCount }}
                                        </span>
                                    @endif
                                </x-nav-link>
                            @endif

                            @if (auth()->user()->role !== 'employee' ||
                                    auth()->user()->hasPermission('view_bills') ||
                                    auth()->user()->hasPermission('create_bills') ||
                                    auth()->user()->hasPermission('edit_bills') ||
                                    auth()->user()->hasPermission('delete_bills'))
                                <x-nav-link :href="route('bills.index')" :active="request()->routeIs('bills.index')"
                                    class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('bills.index') ? 'bg-purple-100 text-purple-700 border-purple-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                    {{ __('navigation.Bills') }}
                                </x-nav-link>
                            @endif

                            @if (auth()->user()->role !== 'employee' ||
                                    auth()->user()->hasPermission('view_customers') ||
                                    auth()->user()->hasPermission('create_customers') ||
                                    auth()->user()->hasPermission('edit_customers') ||
                                    auth()->user()->hasPermission('delete_customers'))
                                <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.index')"
                                    class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ request()->routeIs('customers.index') ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                        </path>
                                    </svg>
                                    {{ __('navigation.Customers') }}
                                </x-nav-link>
                            @endif

                            <!-- More Menu for Additional Items -->
                            <div class="relative" x-data="{ moreOpen: false }">
                                <button @click="moreOpen = !moreOpen"
                                    class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 text-gray-600 hover:text-gray-900 hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16"></path>
                                    </svg>
                                    {{ __('navigation.More') }}
                                    <svg class="ml-1 h-4 w-4 transition-transform duration-200"
                                        :class="{ 'rotate-180': moreOpen }" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <!-- More Menu Dropdown -->
                                <div x-show="moreOpen" @click.away="moreOpen = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 transform scale-95"
                                    x-transition:enter-end="opacity-100 transform scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 transform scale-100"
                                    x-transition:leave-end="opacity-0 transform scale-95"
                                    class="absolute z-50 right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-2">

                                    @if (auth()->user()->role !== 'employee' ||
                                            auth()->user()->hasPermission('view_tags') ||
                                            auth()->user()->hasPermission('create_tags') ||
                                            auth()->user()->hasPermission('edit_tags') ||
                                            auth()->user()->hasPermission('delete_tags'))
                                        <!-- Products Section -->
                                        <div class="px-4 py-2">
                                            <h3
                                                class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                                {{ __('navigation.Tags') }}</h3>

                                            <a href="{{ route('tags.index') }}"
                                                class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('tags.*') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                <svg class="w-4 h-4 mr-3 text-orange-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                                                    </path>
                                                </svg>
                                                {{ __('navigation.Tags') }}
                                            </a>
                                        </div>
                                    @endif

                                    @if (auth()->user()->role !== 'admin')
                                        @if (auth()->user()->role !== 'employee' ||
                                                auth()->user()->hasPermission('view_suppliers') ||
                                                auth()->user()->hasPermission('create_suppliers') ||
                                                auth()->user()->hasPermission('edit_suppliers') ||
                                                auth()->user()->hasPermission('delete_suppliers') ||
                                                auth()->user()->hasPermission('view_purchase_bills') ||
                                                auth()->user()->hasPermission('create_purchase_bills') ||
                                                auth()->user()->hasPermission('edit_purchase_bills') ||
                                                auth()->user()->hasPermission('delete_purchase_bills'))
                                            <!-- Suppliers Section -->
                                            <div class="px-4 py-2 border-t border-gray-100">
                                                <h3
                                                    class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                                    {{ __('navigation.Suppliers') }}</h3>
                                                @if (auth()->user()->role !== 'employee' ||
                                                        auth()->user()->hasPermission('view_suppliers') ||
                                                        auth()->user()->hasPermission('create_suppliers') ||
                                                        auth()->user()->hasPermission('edit_suppliers') ||
                                                        auth()->user()->hasPermission('delete_suppliers'))
                                                    <a href="{{ route('suppliers.index') }}"
                                                        class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('suppliers.index') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                        <svg class="w-4 h-4 mr-3 text-cyan-600" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0a2 2 0 01-2 2H7a2 2 0 01-2-2m2 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h4a1 1 0 011 1v5m-6 0V9a1 1 0 011-1h4a1 1 0 011 1v11">
                                                            </path>
                                                        </svg>
                                                        {{ __('navigation.Manage Suppliers') }}
                                                    </a>
                                                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_suppliers'))
                                                        <a href="{{ route('suppliers.create') }}"
                                                            class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('suppliers.create') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                            <svg class="w-4 h-4 mr-3 text-cyan-600" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                                                </path>
                                                            </svg>
                                                            {{ __('navigation.Add Supplier') }}
                                                        </a>
                                                    @endif
                                                @endif

                                                @if (auth()->user()->role !== 'employee' ||
                                                        auth()->user()->hasPermission('view_purchase_bills') ||
                                                        auth()->user()->hasPermission('create_purchase_bills') ||
                                                        auth()->user()->hasPermission('edit_purchase_bills') ||
                                                        auth()->user()->hasPermission('delete_purchase_bills'))

                                                    <a href="{{ route('purchase-bills.index') }}"
                                                        class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('purchase-bills.index') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                        <svg class="w-4 h-4 mr-3 text-blue-600" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                            </path>
                                                        </svg>
                                                        {{ __('navigation.Purchase Bills') }}
                                                    </a>



                                                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_purchase_bills'))
                                                        <a href="{{ route('purchase-bills.create') }}"
                                                            class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('purchase-bills.create') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                            <svg class="w-4 h-4 mr-3 text-blue-600" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                                                </path>
                                                            </svg>
                                                            {{ __('navigation.New Purchase Bill') }}
                                                        </a>
                                                    @endif
                                                @endif
                                            </div>
                                        @endif
                                    @endif

                                    @if (Auth::user()->role === 'shop_owner' ||
                                            Auth::user()->role === 'restaurant' ||
                                            Auth::user()->role === 'merchant' ||
                                            Auth::user()->role === 'employee')
                                        @if (auth()->user()->role !== 'employee' ||
                                                auth()->user()->hasPermission('manage_employees') ||
                                                (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_expenses')) ||
                                                (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_financial')) ||
                                                (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_settings')))
                                            <!-- Management Section -->
                                            <div class="px-4 py-2 border-t border-gray-100">
                                                <h3
                                                    class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                                    {{ __('navigation.Management') }}</h3>
                                                @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_employees'))
                                                    <a href="{{ route('shopowner.employees.index') }}"
                                                        class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('shopowner.employees.index') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                        <svg class="w-4 h-4 mr-3 text-yellow-600" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                                            </path>
                                                        </svg>
                                                        {{ __('navigation.Employees') }}
                                                    </a>
                                                @endif

                                                @if (auth()->user()->role !== 'employee' ||
                                                        auth()->user()->hasPermission('view_expenses') ||
                                                        auth()->user()->hasPermission('create_expenses') ||
                                                        auth()->user()->hasPermission('edit_expenses') ||
                                                        auth()->user()->hasPermission('delete_expenses'))
                                                    <a href="{{ route('shopowner.expenses.index') }}"
                                                        class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('shopowner.expenses.index') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                        <svg class="w-4 h-4 mr-3 text-red-600" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                                            </path>
                                                        </svg>
                                                        {{ __('navigation.Expenses') }}
                                                    </a>
                                                @endif

                                                @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_financial'))
                                                    <a href="{{ route('dashboard.financial') }}"
                                                        class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('dashboard.financial') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                        <svg class="w-4 h-4 mr-3 text-emerald-600" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                                            </path>
                                                        </svg>
                                                        {{ __('navigation.Financial Dashboard') }}
                                                    </a>
                                                @endif

                                                @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_settings'))
                                                    <a href="{{ route('settings.index') }}"
                                                        class="flex items-center p-2 rounded-md hover:bg-gray-50 transition-colors duration-200 {{ request()->routeIs('settings.*') ? 'bg-gray-50 text-gray-900' : 'text-gray-700' }}">
                                                        <svg class="w-4 h-4 mr-3 text-blue-600" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                                            </path>
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z">
                                                            </path>
                                                        </svg>
                                                        {{ __('navigation.Settings') }}
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Side: Language & Profile -->
            <div class="hidden lg:flex lg:items-center lg:space-x-4">
                <!-- Language Switcher -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                            <div
                                class="px-3 py-2 mr-2 ml-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors duration-200">
                                {{ app()->getLocale() == 'ar' ? 'AR' : 'EN' }}
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link href="{{ route('lang.switch', 'en') }}">
                            {{ __('navigation.English') }}
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('lang.switch', 'ar') }}">
                            {{ __('navigation.Arabic') }}
                        </x-dropdown-link>
                    </x-slot>
                </x-dropdown>

                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-4 py-2 border border-gray-200 text-sm leading-4 font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                            <div class="flex items-center">
                                <div
                                    class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm mr-3">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <div class="hidden xl:block text-left">
                                    <div class="font-medium">{{ Auth::user()->name }}</div>
                                    @if (Auth::user()->role === 'admin')
                                        <div class="text-xs text-gray-500">{{ __('navigation.Admin') }}</div>
                                    @elseif(Auth::user()->role === 'shop_owner' || Auth::user()->role === 'restaurant' || Auth::user()->role === 'merchant')
                                        <div class="text-xs text-gray-500">{{ __('navigation.Owner') }}</div>
                                    @else
                                        <div class="text-xs text-gray-500">{{ __('navigation.Employee') }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="ml-2">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')" class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            {{ __('navigation.Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                class="flex items-center text-red-600 hover:text-red-800"
                                onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                    </path>
                                </svg>
                                {{ __('navigation.Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="flex items-center lg:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-lg text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile/Tablet) -->
    <div :class="{ 'block': open, 'hidden': !open }" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1 px-4 bg-gray-50">
            @if (Auth::user()->role === 'admin')
                <!-- Admin Mobile Links -->
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="flex items-center">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                    {{ __('navigation.Admin Dashboard') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.shop-owners.index')" :active="request()->routeIs('admin.shop-owners.*')" class="flex items-center">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h4a1 1 0 011 1v5m-6 0V9a1 1 0 011-1h4a1 1 0 011 1v11">
                        </path>
                    </svg>
                    {{ __('navigation.Shop Owners') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.employees.index')" :active="request()->routeIs('admin.employees.*')" class="flex items-center">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    {{ __('navigation.All Employees') }}
                </x-responsive-nav-link>
            @else
                <!-- Shop Owner/Employee Mobile Links -->
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="flex items-center">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                    </svg>
                    {{ __('navigation.Dashboard') }}
                </x-responsive-nav-link>

                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_products') ||
                        auth()->user()->hasPermission('create_products') ||
                        auth()->user()->hasPermission('edit_products') ||
                        auth()->user()->hasPermission('delete_products'))
                    <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.index')" class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        {{ __('navigation.Products') }}
                    </x-responsive-nav-link>
                @endif

                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_products') ||
                        auth()->user()->hasPermission('create_products') ||
                        auth()->user()->hasPermission('edit_products') ||
                        auth()->user()->hasPermission('delete_products'))
                    @php
                        $user = auth()->user();
                        $ownerId = $user->role === 'employee' ? $user->shop_owner_id : $user->id;
                        $warningMonths = $user->product_warning_period ?? 4;
                        $warningCount = \App\Models\Product::where('user_id', $ownerId)
                            ->where('quantity', 0) // Changed from '>' to '=' to match out-of-stock page
                            ->where('is_active', true)
                            ->whereNotNull('last_sale_date')
                            ->where('last_sale_date', '<=', now()->subMonths($warningMonths))
                            ->where(function ($query) {
                                $query->whereNull('extended_until')->orWhere('extended_until', '<=', now());
                            })
                            ->count();
                    @endphp
                    <x-responsive-nav-link :href="route('products.out-of-stock')" :active="request()->routeIs('products.out-of-stock')" class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                        {{ __('navigation.Out of Stock') }}
                        @if ($warningCount > 0)
                            <span
                                class="mx-2 mr-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
                                {{ $warningCount }}
                            </span>
                        @endif
                    </x-responsive-nav-link>
                @endif

                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_bills') ||
                        auth()->user()->hasPermission('create_bills') ||
                        auth()->user()->hasPermission('edit_bills') ||
                        auth()->user()->hasPermission('delete_bills'))
                    <x-responsive-nav-link :href="route('bills.index')" :active="request()->routeIs('bills.index')" class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        {{ __('navigation.Bills') }}
                    </x-responsive-nav-link>
                @endif

                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_customers') ||
                        auth()->user()->hasPermission('create_customers') ||
                        auth()->user()->hasPermission('edit_customers') ||
                        auth()->user()->hasPermission('delete_customers'))
                    <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.index')" class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                            </path>
                        </svg>
                        {{ __('navigation.Customers') }}
                    </x-responsive-nav-link>
                @endif

                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_tags') ||
                        auth()->user()->hasPermission('create_tags') ||
                        auth()->user()->hasPermission('edit_tags') ||
                        auth()->user()->hasPermission('delete_tags'))
                    <x-responsive-nav-link :href="route('tags.index')" :active="request()->routeIs('tags.*')" class="flex items-center">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                            </path>
                        </svg>
                        {{ __('navigation.Tags') }}
                    </x-responsive-nav-link>
                @endif

                @if (Auth::user()->role === 'shop_owner' ||
                        Auth::user()->role === 'restaurant' ||
                        Auth::user()->role === 'merchant' ||
                        Auth::user()->role === 'employee')
                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_employees'))
                        <x-responsive-nav-link :href="route('shopowner.employees.index')" :active="request()->routeIs('shopowner.employees.index')" class="flex items-center">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            {{ __('navigation.Employees') }}
                        </x-responsive-nav-link>
                    @endif

                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_expenses'))
                        <x-responsive-nav-link :href="route('shopowner.expenses.index')" :active="request()->routeIs('shopowner.expenses.index')" class="flex items-center">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            {{ __('navigation.Expenses') }}
                        </x-responsive-nav-link>
                    @endif

                    @if (auth()->user()->role !== 'admin')
                        @if (auth()->user()->role !== 'employee' ||
                                auth()->user()->hasPermission('view_suppliers') ||
                                auth()->user()->hasPermission('create_suppliers') ||
                                auth()->user()->hasPermission('edit_suppliers') ||
                                auth()->user()->hasPermission('delete_suppliers') ||
                                auth()->user()->hasPermission('view_purchase_bills') ||
                                auth()->user()->hasPermission('create_purchase_bills') ||
                                auth()->user()->hasPermission('edit_purchase_bills') ||
                                auth()->user()->hasPermission('delete_purchase_bills'))
                            <!-- Mobile Suppliers Menu -->
                            <div class="space-y-1">
                                <div class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg mt-2">
                                    {{ __('navigation.Suppliers') }}
                                </div>
                                @if (auth()->user()->role !== 'employee' ||
                                        auth()->user()->hasPermission('view_suppliers') ||
                                        auth()->user()->hasPermission('create_suppliers') ||
                                        auth()->user()->hasPermission('edit_suppliers') ||
                                        auth()->user()->hasPermission('delete_suppliers'))
                                    <x-responsive-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.index')"
                                        class="flex items-center ml-4">
                                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0a2 2 0 01-2 2H7a2 2 0 01-2-2m2 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h4a1 1 0 011 1v5m-6 0V9a1 1 0 011-1h4a1 1 0 011 1v11">
                                            </path>
                                        </svg>
                                        {{ __('navigation.Manage Suppliers') }}
                                    </x-responsive-nav-link>
                                    <x-responsive-nav-link :href="route('suppliers.create')" class="flex items-center ml-4">
                                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        {{ __('navigation.Add Supplier') }}
                                    </x-responsive-nav-link>
                                @endif
                                @if (auth()->user()->role !== 'employee' ||
                                        auth()->user()->hasPermission('view_purchase_bills') ||
                                        auth()->user()->hasPermission('create_purchase_bills') ||
                                        auth()->user()->hasPermission('edit_purchase_bills') ||
                                        auth()->user()->hasPermission('delete_purchase_bills'))
                                    <x-responsive-nav-link :href="route('purchase-bills.index')" :active="request()->routeIs('purchase-bills.index')"
                                        class="flex items-center ml-4">
                                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                            </path>
                                        </svg>
                                        {{ __('navigation.Purchase Bills') }}
                                    </x-responsive-nav-link>
                                    <x-responsive-nav-link :href="route('purchase-bills.create')" class="flex items-center ml-4">
                                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        {{ __('navigation.New Purchase Bill') }}
                                    </x-responsive-nav-link>
                                @endif
                            </div>
                        @endif
                    @endif

                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_financial'))
                        <x-responsive-nav-link :href="route('dashboard.financial')" :active="request()->routeIs('dashboard.financial')" class="flex items-center">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                </path>
                            </svg>
                            {{ __('navigation.Financial Dashboard') }}
                        </x-responsive-nav-link>
                    @endif

                    @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_settings'))
                        <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')" class="flex items-center">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z">
                                </path>
                            </svg>
                            {{ __('navigation.Settings') }}
                        </x-responsive-nav-link>
                    @endif
                @endif
            @endif
        </div>

        <!-- Mobile Language Switcher -->
        <div class="pt-4 pb-1 border-t border-gray-200 bg-gray-50">
            <div class="px-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">{{ __('navigation.Language') }}</span>
                    <div class="flex space-x-2">
                        <a href="{{ route('lang.switch', 'en') }}"
                            class="px-3 py-1 text-sm rounded-md {{ app()->getLocale() == 'en' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            {{ __('navigation.EN') }}
                        </a>
                        <a href="{{ route('lang.switch', 'ar') }}"
                            class="px-3 py-1 text-sm rounded-md {{ app()->getLocale() == 'ar' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            {{ __('navigation.AR') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 bg-gray-50">
            <div class="px-4">
                <div class="flex items-center">
                    <div
                        class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="ml-3">
                        <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                        @if (Auth::user()->role === 'admin')
                            <div class="text-xs text-blue-600 font-semibold">{{ __('navigation.Administrator') }}
                            </div>
                        @elseif(Auth::user()->role === 'shop_owner' || Auth::user()->role === 'restaurant' || Auth::user()->role === 'merchant')
                            <div class="text-xs text-green-600 font-semibold">{{ __('navigation.Shop Owner') }}</div>
                        @else
                            <div class="text-xs text-gray-600">{{ __('navigation.Employee') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-3 space-y-1 px-4">
                <x-responsive-nav-link :href="route('profile.edit')" class="flex items-center">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    {{ __('navigation.Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" class="flex items-center text-red-600"
                        onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                            </path>
                        </svg>
                        {{ __('navigation.Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
