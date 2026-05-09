{{-- ═══════════════════════════════════════════════════════════════
     VERTICAL COLLAPSIBLE SIDEBAR NAVIGATION
     • Collapsed (w-16): icons only + hover tooltips
    • Expanded (w-56): icons + labels — toggled by hamburger button
    • State persisted in a cookie via Alpine store "sidebar"
═══════════════════════════════════════════════════════════════ --}}
@php
    $isRTL = app()->getLocale() === 'ar';
    $mobileClosedTransform = $isRTL ? 'translate-x-full' : '-translate-x-full';
    // Tooltip floats on the OUTER edge of the sidebar
    $tipPos = $isRTL ? 'right: calc(100% + 8px);' : 'left: calc(100% + 8px);';

    $navLink = 'flex items-center px-3 py-2.5 rounded-xl transition-all duration-150 w-full';
    $activeLink = 'bg-indigo-50 text-indigo-700 font-semibold';
    $inactiveLink = 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
    $iconCls = 'w-5 h-5 flex-shrink-0';
    $labelCls = 'ms-3 text-sm whitespace-nowrap overflow-hidden leading-5';
    $tipWrap = 'absolute top-1/2 z-[70] pointer-events-none';
    $tipInner = 'bg-gray-900 text-white text-xs px-2.5 py-1.5 rounded-lg shadow-xl whitespace-nowrap';

    // Compute out-of-stock badge count once for the whole sidebar
    $navWarningCount = 0;
    if (Auth::check() && Auth::user()->role !== 'admin') {
        $__u = auth()->user();
        $__oid = $__u->role === 'employee' ? $__u->shop_owner_id : $__u->id;
        $__mo = $__u->product_warning_period ?? 4;
        $navWarningCount = \App\Models\Product::where('user_id', $__oid)
            ->where('quantity', 0)
            ->where('is_active', true)
            ->whereNotNull('last_sale_date')
            ->where('last_sale_date', '<=', now()->subMonths($__mo))
            ->where(fn($q) => $q->whereNull('extended_until')->orWhere('extended_until', '<=', now()))
            ->count();
    }

    // Compute installment due-today count for nav badge
    $installmentDueCount = 0;
    if (Auth::check() && Auth::user()->role !== 'admin') {
        $__cu = auth()->user();
        $__cid = $__cu->id;
        $__coid = $__cu->role === 'employee' ? $__cu->shop_owner_id : $__cu->id;
        $__today = now()->toDateString();
        if ($__cu->role !== 'employee' || $__cu->hasPermission('view_installments')) {
            $installmentDueCount = \App\Models\InstallmentPayment::whereHas(
                'plan',
                fn($q) => $q->where('user_id', $__coid),
            )
                ->where('is_paid', false)
                ->whereDate('due_date', '<=', $__today)
                ->whereDoesntHave(
                    'dismissals',
                    fn($q) => $q->where('user_id', $__cid)->where('dismissed_for_date', $__today),
                )
                ->count();
        }
    }
@endphp

<div x-data="{}" x-cloak x-show="$store.sidebar.mobileOpen" x-transition.opacity
    @click="$store.sidebar.closeMobile()" class="fixed inset-0 z-40 bg-black/40 lg:hidden">
</div>

<div x-data="{}"
    class="fixed inset-x-0 top-0 z-40 h-16 border-b border-gray-200 bg-white shadow-sm lg:hidden">
    <div class="relative flex h-full items-center justify-center px-4">
        <button @click="$store.sidebar.toggle()" type="button" style="{{ $isRTL ? 'right: 1rem;' : 'left: 1rem;' }}"
            class="absolute inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            aria-label="{{ __('navigation.Dashboard') }} menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <a href="{{ route('dashboard') }}" style="{{ $isRTL ? 'left: 1rem;' : 'right: 1rem;' }}"
            class="absolute flex items-center">
            <x-application-logo class="block h-11 w-auto" />
        </a>
    </div>
</div>

<aside x-data="{}"
    :class="[
        $store.sidebar.mobileOpen ? 'translate-x-0' : '{{ $mobileClosedTransform }} lg:translate-x-0',
        $store.sidebar.expanded ? 'lg:w-56' : 'lg:w-16'
    ]"
    class="fixed top-0 h-screen bg-white shadow-lg z-50 transition-all duration-300 ease-in-out flex flex-col overflow-visible border-gray-200
           {{ $isRTL ? 'border-l' : 'border-r' }} w-72 lg:w-16"
    style="{{ $isRTL ? 'right: 0;' : 'left: 0;' }}">

    {{-- ── HEADER: Logo + Toggle ─────────────────────────────────────── --}}
    <div class="hidden border-b border-gray-100 flex-shrink-0 px-3 lg:flex"
        :class="$store.sidebar.expanded ? 'items-center justify-between h-16' :
            'flex-col items-center justify-center gap-2 py-3'">

        <a href="{{ route('dashboard') }}" x-cloak class="overflow-hidden flex-shrink-0">
            <x-application-logo x-cloak x-show="$store.sidebar.expanded" class="block fill-current text-gray-800"
                width="110" height="auto" />
            <x-application-logo x-cloak x-show="!$store.sidebar.expanded"
                class="block h-10 w-auto fill-current text-gray-800" />
        </a>

        <button @click="$store.sidebar.toggle()"
            class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    {{-- ── SCROLLABLE NAV ─────────────────────────────────────────────── --}}
    <nav x-data="{
        tip: {
            show: false,
            text: '',
            style: ''
        },
        showTip(event, text) {
            if ($store.sidebar.expanded || !text) {
                this.hideTip();
                return;
            }
    
            const rect = event.currentTarget.getBoundingClientRect();
            const gap = 8;
            const sideStyle = {{ $isRTL ? 'true' : 'false' }} ?
                `right:${Math.max(8, window.innerWidth - rect.left + gap)}px;` :
                `left:${Math.max(8, rect.right + gap)}px;`;
    
            this.tip = {
                show: true,
                text,
                style: `top:${rect.top + (rect.height / 2)}px;${sideStyle}transform:translateY(-50%);`
            };
        },
        hideTip() {
            this.tip.show = false;
        }
    }" class="flex-1 overflow-hidden">
        <div class="h-full overflow-y-auto overflow-x-hidden py-2"
            style="scrollbar-width: thin; scrollbar-color: #d1d5db transparent;">

            @if (Auth::user()->role === 'admin')
                {{-- ══ ADMIN ══════════════════════════════════════════════════════ --}}

                @php $ac = request()->routeIs('admin.dashboard') ? $activeLink : $inactiveLink; @endphp
                <div @mouseenter="showTip($event, @js(__('navigation.Dashboard')))" @mouseleave="hideTip()"
                    class="relative px-2 mb-0.5">
                    <a href="{{ route('admin.dashboard') }}" class="{{ $navLink }} {{ $ac }}">
                        <svg class="{{ $iconCls }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span x-cloak x-show="$store.sidebar.expanded"
                            class="{{ $labelCls }}">{{ __('navigation.Dashboard') }}</span>
                    </a>
                </div>

                @php $ac = request()->routeIs('admin.shop-owners.*') ? $activeLink : $inactiveLink; @endphp
                <div @mouseenter="showTip($event, @js(__('navigation.Shop Owners')))" @mouseleave="hideTip()"
                    class="relative px-2 mb-0.5">
                    <a href="{{ route('admin.shop-owners.index') }}" class="{{ $navLink }} {{ $ac }}">
                        <svg class="{{ $iconCls }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h4a1 1 0 011 1v5m-6 0V9a1 1 0 011-1h4a1 1 0 011 1v11" />
                        </svg>
                        <span x-cloak x-show="$store.sidebar.expanded"
                            class="{{ $labelCls }}">{{ __('navigation.Shop Owners') }}</span>
                    </a>
                </div>

                @php $ac = request()->routeIs('admin.employees.*') ? $activeLink : $inactiveLink; @endphp
                <div @mouseenter="showTip($event, @js(__('navigation.All Employees')))" @mouseleave="hideTip()"
                    class="relative px-2 mb-0.5">
                    <a href="{{ route('admin.employees.index') }}" class="{{ $navLink }} {{ $ac }}">
                        <svg class="{{ $iconCls }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span x-cloak x-show="$store.sidebar.expanded"
                            class="{{ $labelCls }}">{{ __('navigation.All Employees') }}</span>
                    </a>
                </div>

                @php
                    $expiredLicenseCount = \App\Models\User::where('account_type', 'full')
                        ->whereIn('role', ['shop_owner', 'restaurant', 'merchant', 'disabled'])
                        ->whereNotNull('license_expires_at')
                        ->where('license_expires_at', '<', now())
                        ->count();
                    $ac = request()->routeIs('admin.shop-owners.expiring-licenses') ? $activeLink : $inactiveLink;
                @endphp
                <div @mouseenter="showTip($event, @js(__('navigation.License Monitor')))" @mouseleave="hideTip()"
                    class="relative px-2 mb-0.5">
                    <a href="{{ route('admin.shop-owners.expiring-licenses') }}" class="{{ $navLink }} {{ $ac }}">
                        <div class="relative flex-shrink-0">
                            <svg class="{{ $iconCls }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            @if ($expiredLicenseCount > 0)
                                <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[16px] h-4 px-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full leading-none">
                                    {{ $expiredLicenseCount > 99 ? '99+' : $expiredLicenseCount }}
                                </span>
                            @endif
                        </div>
                        <span x-cloak x-show="$store.sidebar.expanded"
                            class="{{ $labelCls }} flex items-center gap-2">
                            {{ __('navigation.License Monitor') }}
                            @if ($expiredLicenseCount > 0)
                                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-xs font-bold rounded-full leading-none">
                                    {{ $expiredLicenseCount > 99 ? '99+' : $expiredLicenseCount }}
                                </span>
                            @endif
                        </span>
                    </a>
                </div>
            @else
                {{-- ══ SHOP OWNER / EMPLOYEE ══════════════════════════════════════ --}}

                {{-- Dashboard --}}
                @php $ac = request()->routeIs('dashboard') ? $activeLink : $inactiveLink; @endphp
                <div @mouseenter="showTip($event, @js(__('navigation.Dashboard')))" @mouseleave="hideTip()"
                    class="relative px-2 mb-0.5">
                    <a href="{{ route('dashboard') }}" class="{{ $navLink }} {{ $ac }}">
                        <svg class="{{ $iconCls }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 5a2 2 0 012-2h4a2 2 0 012 2v4H8V5z" />
                        </svg>
                        <span x-cloak x-show="$store.sidebar.expanded"
                            class="{{ $labelCls }}">{{ __('navigation.Dashboard') }}</span>
                    </a>
                </div>

                {{-- Products --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_products') ||
                        auth()->user()->hasPermission('create_products') ||
                        auth()->user()->hasPermission('edit_products') ||
                        auth()->user()->hasPermission('delete_products'))
                    @php $ac = request()->routeIs('products.index') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Products')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('products.index') }}" class="{{ $navLink }} {{ $ac }}">
                            <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }}">{{ __('navigation.Products') }}</span>
                        </a>
                    </div>
                @endif

                {{-- Out of Stock --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_products') ||
                        auth()->user()->hasPermission('create_products') ||
                        auth()->user()->hasPermission('edit_products') ||
                        auth()->user()->hasPermission('delete_products'))
                    @php $ac = request()->routeIs('products.out-of-stock') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Out of Stock')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('products.out-of-stock') }}"
                            class="{{ $navLink }} {{ $ac }}">
                            <span class="relative flex-shrink-0">
                                <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                @if ($navWarningCount > 0)
                                    <span
                                        class="absolute -top-1.5 -right-1.5 bg-red-500 text-white font-bold w-4 h-4 rounded-full flex items-center justify-center"
                                        style="font-size:9px;line-height:1;">{{ $navWarningCount > 9 ? '9+' : $navWarningCount }}</span>
                                @endif
                            </span>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }} flex items-center gap-2">
                                {{ __('navigation.Out of Stock') }}
                                @if ($navWarningCount > 0)
                                    <span
                                        class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $navWarningCount }}</span>
                                @endif
                            </span>
                        </a>
                    </div>
                @endif

                {{-- Bills --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_bills') ||
                        auth()->user()->hasPermission('create_bills') ||
                        auth()->user()->hasPermission('edit_bills') ||
                        auth()->user()->hasPermission('delete_bills'))
                    @php $ac = request()->routeIs('bills.index') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Bills')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('bills.index') }}" class="{{ $navLink }} {{ $ac }}">
                            <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }}">{{ __('navigation.Bills') }}</span>
                        </a>
                    </div>
                @endif

                {{-- Sales & Promotions --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_sales') ||
                        auth()->user()->hasPermission('create_sales') ||
                        auth()->user()->hasPermission('edit_sales') ||
                        auth()->user()->hasPermission('delete_sales'))
                    @php $ac = request()->routeIs('sales.*') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Sales & Promotions')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('sales.index') }}" class="{{ $navLink }} {{ $ac }}">
                            <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }}">{{ __('navigation.Sales & Promotions') }}</span>
                        </a>
                    </div>
                @endif

                {{-- Customers --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_customers') ||
                        auth()->user()->hasPermission('create_customers') ||
                        auth()->user()->hasPermission('edit_customers') ||
                        auth()->user()->hasPermission('delete_customers'))
                    @php $ac = request()->routeIs('customers.index') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Customers')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('customers.index') }}" class="{{ $navLink }} {{ $ac }}">
                            <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                            </svg>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }}">{{ __('navigation.Customers') }}</span>
                        </a>
                    </div>
                @endif

                {{-- Installments / Deferred Payments --}}
                @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_installments'))
                    @php $ac = request()->routeIs('installments.*') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Installments')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('installments.index') }}"
                            class="{{ $navLink }} {{ $ac }} relative">
                            <span class="relative flex-shrink-0">
                                <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                @if ($installmentDueCount > 0)
                                    <span
                                        class="absolute -top-1.5 -right-1.5 bg-red-500 text-white font-bold w-4 h-4 rounded-full flex items-center justify-center"
                                        style="font-size:9px;line-height:1;">{{ $installmentDueCount > 9 ? '9+' : $installmentDueCount }}</span>
                                @endif
                            </span>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }} flex items-center gap-2">
                                {{ __('navigation.Installments') }}
                                @if ($installmentDueCount > 0)
                                    <span class="bg-red-500 text-white font-bold px-1.5 py-0.5 rounded-full"
                                        style="font-size:9px;line-height:1.4;">{{ $installmentDueCount > 9 ? '9+' : $installmentDueCount }}</span>
                                @endif
                            </span>
                        </a>
                    </div>
                @endif

                {{-- ── Commerce divider ── --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_tags') ||
                        auth()->user()->hasPermission('view_suppliers') ||
                        auth()->user()->hasPermission('view_purchase_bills'))
                    <div class="my-2 px-2">
                        <div x-cloak x-show="$store.sidebar.expanded" class="px-3 pt-3 pb-1">
                            <span
                                class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('navigation.Commerce') }}</span>
                        </div>
                        <div x-cloak x-show="!$store.sidebar.expanded">
                            <hr class="border-gray-100">
                        </div>
                    </div>
                @endif

                {{-- Tags --}}
                @if (auth()->user()->role !== 'employee' ||
                        auth()->user()->hasPermission('view_tags') ||
                        auth()->user()->hasPermission('create_tags') ||
                        auth()->user()->hasPermission('edit_tags') ||
                        auth()->user()->hasPermission('delete_tags'))
                    @php $ac = request()->routeIs('tags.*') ? $activeLink : $inactiveLink; @endphp
                    <div @mouseenter="showTip($event, @js(__('navigation.Tags')))" @mouseleave="hideTip()"
                        class="relative px-2 mb-0.5">
                        <a href="{{ route('tags.index') }}" class="{{ $navLink }} {{ $ac }}">
                            <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span x-cloak x-show="$store.sidebar.expanded"
                                class="{{ $labelCls }}">{{ __('navigation.Tags') }}</span>
                        </a>
                    </div>
                @endif

                @if (auth()->user()->role !== 'admin')
                    {{-- Suppliers --}}
                    @if (auth()->user()->role !== 'employee' ||
                            auth()->user()->hasPermission('view_suppliers') ||
                            auth()->user()->hasPermission('create_suppliers') ||
                            auth()->user()->hasPermission('edit_suppliers') ||
                            auth()->user()->hasPermission('delete_suppliers'))
                        @php $ac = request()->routeIs('suppliers.*') ? $activeLink : $inactiveLink; @endphp
                        <div @mouseenter="showTip($event, @js(__('navigation.Suppliers')))" @mouseleave="hideTip()"
                            class="relative px-2 mb-0.5">
                            <a href="{{ route('suppliers.index') }}"
                                class="{{ $navLink }} {{ $ac }}">
                                <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0a2 2 0 01-2 2H7a2 2 0 01-2-2m2 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h4a1 1 0 011 1v5m-6 0V9a1 1 0 011-1h4a1 1 0 011 1v11" />
                                </svg>
                                <span x-cloak x-show="$store.sidebar.expanded"
                                    class="{{ $labelCls }}">{{ __('navigation.Suppliers') }}</span>
                            </a>
                        </div>
                    @endif

                    {{-- Purchase Bills --}}
                    @if (auth()->user()->role !== 'employee' ||
                            auth()->user()->hasPermission('view_purchase_bills') ||
                            auth()->user()->hasPermission('create_purchase_bills') ||
                            auth()->user()->hasPermission('edit_purchase_bills') ||
                            auth()->user()->hasPermission('delete_purchase_bills'))
                        @php $ac = request()->routeIs('purchase-bills.*') ? $activeLink : $inactiveLink; @endphp
                        <div @mouseenter="showTip($event, @js(__('navigation.Purchase Bills')))" @mouseleave="hideTip()"
                            class="relative px-2 mb-0.5">
                            <a href="{{ route('purchase-bills.index') }}"
                                class="{{ $navLink }} {{ $ac }}">
                                <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span x-cloak x-show="$store.sidebar.expanded"
                                    class="{{ $labelCls }}">{{ __('navigation.Purchase Bills') }}</span>
                            </a>
                        </div>

                        @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('create_purchase_bills'))
                            @php $ac = request()->routeIs('purchase-bills.create') ? $activeLink : $inactiveLink; @endphp
                            <div @mouseenter="showTip($event, @js(__('navigation.New Purchase Bill')))" @mouseleave="hideTip()"
                                class="relative px-2 mb-0.5">
                                <a href="{{ route('purchase-bills.create') }}"
                                    class="{{ $navLink }} {{ $ac }}">
                                    <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span x-cloak x-show="$store.sidebar.expanded"
                                        class="{{ $labelCls }}">{{ __('navigation.New Purchase Bill') }}</span>
                                </a>
                            </div>
                        @endif
                    @endif
                @endif

                {{-- ── Management section ── --}}
                @if (Auth::user()->role === 'shop_owner' ||
                        Auth::user()->role === 'restaurant' ||
                        Auth::user()->role === 'merchant' ||
                        Auth::user()->role === 'employee')
                    @if (auth()->user()->role !== 'employee' ||
                            auth()->user()->hasPermission('manage_employees') ||
                            auth()->user()->hasPermission('manage_expenses') ||
                            auth()->user()->hasPermission('view_financial') ||
                            auth()->user()->hasPermission('manage_settings') ||
                            auth()->user()->hasPermission('view_expenses') ||
                            auth()->user()->hasPermission('manage_payments_receipts'))

                        <div class="my-2 px-2">
                            <div x-cloak x-show="$store.sidebar.expanded" class="px-3 pt-3 pb-1">
                                <span
                                    class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('navigation.Management') }}</span>
                            </div>
                            <div x-cloak x-show="!$store.sidebar.expanded">
                                <hr class="border-gray-100">
                            </div>
                        </div>

                        {{-- Employees --}}
                        @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_employees'))
                            @php $ac = request()->routeIs('shopowner.employees.*') ? $activeLink : $inactiveLink; @endphp
                            <div @mouseenter="showTip($event, @js(__('navigation.Employees')))" @mouseleave="hideTip()"
                                class="relative px-2 mb-0.5">
                                <a href="{{ route('shopowner.employees.index') }}"
                                    class="{{ $navLink }} {{ $ac }}">
                                    <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span x-cloak x-show="$store.sidebar.expanded"
                                        class="{{ $labelCls }}">{{ __('navigation.Employees') }}</span>
                                </a>
                            </div>
                        @endif

                        {{-- Expenses --}}
                        @if (auth()->user()->role !== 'employee' ||
                                auth()->user()->hasPermission('view_expenses') ||
                                auth()->user()->hasPermission('create_expenses') ||
                                auth()->user()->hasPermission('edit_expenses') ||
                                auth()->user()->hasPermission('delete_expenses'))
                            @php $ac = request()->routeIs('shopowner.expenses.*') ? $activeLink : $inactiveLink; @endphp
                            <div @mouseenter="showTip($event, @js(__('navigation.Expenses')))" @mouseleave="hideTip()"
                                class="relative px-2 mb-0.5">
                                <a href="{{ route('shopowner.expenses.index') }}"
                                    class="{{ $navLink }} {{ $ac }}">
                                    <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span x-cloak x-show="$store.sidebar.expanded"
                                        class="{{ $labelCls }}">{{ __('navigation.Expenses') }}</span>
                                </a>
                            </div>
                        @endif

                        {{-- Financial Dashboard --}}
                        @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('view_financial'))
                            @php $ac = request()->routeIs('dashboard.financial') ? $activeLink : $inactiveLink; @endphp
                            <div @mouseenter="showTip($event, @js(__('navigation.Financial Dashboard')))" @mouseleave="hideTip()"
                                class="relative px-2 mb-0.5">
                                <a href="{{ route('dashboard.financial') }}"
                                    class="{{ $navLink }} {{ $ac }}">
                                    <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <span x-cloak x-show="$store.sidebar.expanded"
                                        class="{{ $labelCls }}">{{ __('navigation.Financial Dashboard') }}</span>
                                </a>
                            </div>
                        @endif

                        {{-- Payments and Receipts --}}
                        @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_payments_receipts'))
                            @php $ac = request()->routeIs('payments-receipts.*') ? $activeLink : $inactiveLink; @endphp
                            <div @mouseenter="showTip($event, @js(__('navigation.Payments and Receipts')))" @mouseleave="hideTip()"
                                class="relative px-2 mb-0.5">
                                <a href="{{ route('payments-receipts.index') }}"
                                    class="{{ $navLink }} {{ $ac }}">
                                    <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span x-cloak x-show="$store.sidebar.expanded"
                                        class="{{ $labelCls }}">{{ __('navigation.Payments and Receipts') }}</span>
                                </a>
                            </div>
                        @endif

                        {{-- Settings --}}
                        @if (auth()->user()->role !== 'employee' || auth()->user()->hasPermission('manage_settings'))
                            @php $ac = request()->routeIs('settings.*') ? $activeLink : $inactiveLink; @endphp
                            <div @mouseenter="showTip($event, @js(__('navigation.Settings')))" @mouseleave="hideTip()"
                                class="relative px-2 mb-0.5">
                                <a href="{{ route('settings.index') }}"
                                    class="{{ $navLink }} {{ $ac }}">
                                    <svg class="{{ $iconCls }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span x-cloak x-show="$store.sidebar.expanded"
                                        class="{{ $labelCls }}">{{ __('navigation.Settings') }}</span>
                                </a>
                            </div>
                        @endif

                    @endif
                @endif

            @endif
            <div x-cloak x-show="tip.show" x-transition class="fixed z-[140] pointer-events-none"
                :style="tip.style">
                <div class="{{ $tipInner }}" x-text="tip.text"></div>
            </div>
        </div>
    </nav>

    {{-- ── BOTTOM: Language + User ────────────────────────────────────── --}}
    <div class="border-t border-gray-100 flex-shrink-0 py-2 space-y-0.5">

        {{-- Language Switcher --}}
        <div x-data="{ h: false }" @mouseenter="h=true" @mouseleave="h=false" class="relative px-2">
            <div class="flex items-center px-3 py-2 rounded-xl text-gray-500"
                :class="$store.sidebar.expanded ? '' : 'justify-center'">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                <div x-cloak x-show="$store.sidebar.expanded" class="ms-3 flex gap-2 items-center">
                    <a href="{{ route('lang.switch', 'en') }}"
                        class="px-2 py-0.5 text-xs rounded-md font-medium {{ app()->getLocale() === 'en' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">EN</a>
                    <a href="{{ route('lang.switch', 'ar') }}"
                        class="px-2 py-0.5 text-xs rounded-md font-medium {{ app()->getLocale() === 'ar' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">AR</a>
                </div>
            </div>
            <div x-cloak x-show="!$store.sidebar.expanded && h" x-transition class="{{ $tipWrap }}"
                style="{{ $tipPos }} transform:translateY(-50%);">
                <div class="{{ $tipInner }} flex gap-2">
                    <a href="{{ route('lang.switch', 'en') }}"
                        class="px-1.5 py-0.5 rounded text-xs {{ app()->getLocale() === 'en' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">EN</a>
                    <a href="{{ route('lang.switch', 'ar') }}"
                        class="px-1.5 py-0.5 rounded text-xs {{ app()->getLocale() === 'ar' ? 'bg-indigo-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}">AR</a>
                </div>
            </div>
        </div>

        {{-- User Profile + Dropdown --}}
        <div x-data="{ h: false, open: false }" @mouseenter="h=true" @mouseleave="h=false" class="relative px-2">
            <button @click="open = !open"
                class="flex items-center w-full px-3 py-2 rounded-xl text-gray-600 hover:bg-gray-50 transition-colors focus:outline-none"
                :class="$store.sidebar.expanded ? '' : 'justify-center'">
                <div
                    class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div x-cloak x-show="$store.sidebar.expanded" class="ms-3 text-start overflow-hidden flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate">{{ Auth::user()->name }}</div>
                    <div class="text-xs text-gray-500">
                        @if (Auth::user()->role === 'admin')
                            {{ __('navigation.Administrator') }}
                        @elseif (in_array(Auth::user()->role, ['shop_owner', 'restaurant', 'merchant']))
                            {{ __('navigation.Owner') }}
                        @else
                            {{ __('navigation.Employee') }}
                        @endif
                    </div>
                </div>
                <svg x-cloak x-show="$store.sidebar.expanded"
                    class="w-4 h-4 ms-auto text-gray-400 transition-transform duration-200 flex-shrink-0"
                    :class="open ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Dropdown popup --}}
            <div x-cloak x-show="open" @click.away="open = false"
                x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="absolute bottom-full mb-1 w-60 bg-white rounded-xl shadow-xl border border-gray-200 py-1 z-[120]"
                style="{{ $isRTL ? 'right: 0;' : 'left: 0;' }}">

                <div class="px-4 py-2.5 border-b border-gray-100">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ Auth::user()->name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</div>
                </div>

                <a href="{{ route('profile.edit') }}"
                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 me-2.5 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ __('navigation.Profile') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="flex items-center w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4 me-2.5 text-red-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        {{ __('navigation.Log Out') }}
                    </button>
                </form>
            </div>

            {{-- Tooltip showing user name when collapsed --}}
            <div x-cloak x-show="!$store.sidebar.expanded && h && !open" x-transition class="{{ $tipWrap }}"
                style="{{ $tipPos }} transform:translateY(-50%);">
                <div class="{{ $tipInner }}">{{ Auth::user()->name }}</div>
            </div>
        </div>
    </div>

</aside>
