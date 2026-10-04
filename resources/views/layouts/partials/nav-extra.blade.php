{{--
    Navigation entries contributed by feature modules.
    Every item is guarded by Route::has(), so an entry only appears once its module is installed.
    Variables from layouts/navigation.blade.php ($navLink, $activeLink, ...) are inherited.
--}}
@php
    $__navUser = auth()->user();
    $__isEmployee = $__navUser->role === 'employee';
    $__isAdmin = $__navUser->role === 'admin';

    $__section = $section ?? 'shop';

    // [route, label key, active pattern, permission, flags, icon path(s)]
    $__shopGroups = [
        'operations' => [
            ['route' => 'kitchen.display', 'label' => 'kitchen', 'active' => 'kitchen.*', 'perm' => 'view_kitchen', 'restaurant' => true,
                'icon' => 'M3 10h18M5 10V7a2 2 0 012-2h10a2 2 0 012 2v3M5 10v8a2 2 0 002 2h10a2 2 0 002-2v-8M9 14h6'],
            ['route' => 'restaurant.tables.index', 'label' => 'tables', 'active' => 'restaurant.tables.*', 'perm' => 'manage_tables', 'restaurant' => true,
                'icon' => 'M4 6h16M4 10h16M6 10v8m12-8v8M9 18h6'],
            ['route' => 'restaurant.orders.index', 'label' => 'restaurant_orders', 'active' => 'restaurant.orders.*', 'perm' => 'create_bills', 'restaurant' => true,
                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['route' => 'pos.held.index', 'label' => 'held_bills', 'active' => 'pos.held.*', 'perm' => 'create_bills',
                'icon' => 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'finance.day-close.index', 'label' => 'day_close', 'active' => 'finance.day-close.*', 'perm_any' => ['close_day', 'view_financial'], 'feature' => 'financial_dashboard',
                'icon' => 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z'],
        ],
        'management' => [
            ['route' => 'shopowner.team.index', 'label' => 'team', 'active' => 'shopowner.team.*', 'owner_only' => true,
                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
            ['route' => 'shopowner.attendance.index', 'label' => 'attendance', 'active' => 'shopowner.attendance.*', 'perm' => 'manage_employees', 'feature' => 'hr',
                'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'shopowner.payroll.index', 'label' => 'payroll', 'active' => 'shopowner.payroll.*', 'perm' => 'manage_employees', 'feature' => 'hr',
                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'finance.cash-drawer.index', 'label' => 'cash_drawer', 'active' => 'finance.cash-drawer.*', 'perm' => 'view_financial', 'feature' => 'financial_dashboard',
                'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
            ['route' => 'finance.team-summary.index', 'label' => 'team_summary', 'active' => 'finance.team-summary.*', 'perm' => 'view_team_activity', 'feature' => 'team_activity', 'financial' => true,
                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['route' => 'shopowner.activity.index', 'label' => 'activity', 'active' => 'shopowner.activity.*', 'perm' => 'view_team_activity', 'feature' => 'team_activity', 'financial' => true,
                'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            ['route' => 'restaurant.insights.index', 'label' => 'restaurant_insights', 'active' => 'restaurant.insights.*', 'perm_any' => ['view_reports', 'view_financial'], 'restaurant' => true, 'feature' => 'reports',
                'icon' => 'M3 3v18h18M7 13l3-3 3 2 4-5'],
        ],
    ];

    $__adminGroups = [
        'platform' => [
            ['route' => 'admin.storage.index', 'label' => 'storage', 'active' => 'admin.storage.*',
                'icon' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4'],
            ['route' => 'admin.audit.index', 'label' => 'audit', 'active' => 'admin.audit.*',
                'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
            ['route' => 'admin.settings.index', 'label' => 'platform_settings', 'active' => 'admin.settings.*',
                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ],
    ];

    $__groups = $__section === 'admin' ? $__adminGroups : $__shopGroups;

    $__visible = [];
    foreach ($__groups as $__groupKey => $__items) {
        foreach ($__items as $__item) {
            if (! \Illuminate\Support\Facades\Route::has($__item['route'])) {
                continue;
            }
            if (! empty($__item['owner_only']) && $__isEmployee) {
                continue;
            }
            if (! empty($__item['restaurant']) && ! $__navUser->isRestaurantAccount()) {
                continue;
            }
            if (! empty($__item['feature']) && ! $__navUser->canAccessFeature($__item['feature'])) {
                continue;
            }
            if (! empty($__item['financial']) && ! $__navUser->canAccessFeature('financial_dashboard')) {
                continue;
            }
            if (! empty($__item['perm']) && $__isEmployee && ! $__navUser->hasPermission($__item['perm'])) {
                continue;
            }
            if (! empty($__item['perm_any']) && $__isEmployee) {
                $__hasAny = false;
                foreach ($__item['perm_any'] as $__perm) {
                    if ($__navUser->hasPermission($__perm)) {
                        $__hasAny = true;
                        break;
                    }
                }
                if (! $__hasAny) {
                    continue;
                }
            }
            $__visible[$__groupKey][] = $__item;
        }
    }
@endphp

@foreach ($__visible as $__groupKey => $__items)
    <div class="my-2 px-2">
        <div x-cloak x-show="$store.sidebar.expanded" class="px-3 pt-3 pb-1">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('navx.groups.' . $__groupKey) }}</span>
        </div>
        <div x-cloak x-show="!$store.sidebar.expanded">
            <hr class="border-gray-100">
        </div>
    </div>

    @foreach ($__items as $__item)
        @php
            $ac = request()->routeIs($__item['active']) ? $activeLink : $inactiveLink;
            $__label = __('navx.items.' . $__item['label']);
        @endphp
        <div @mouseenter="showTip($event, @js($__label))" @mouseleave="hideTip()" class="relative px-2 mb-0.5">
            <a href="{{ route($__item['route']) }}" class="{{ $navLink }} {{ $ac }}">
                <svg class="{{ $iconCls }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $__item['icon'] }}" />
                </svg>
                <span x-cloak x-show="$store.sidebar.expanded" class="{{ $labelCls }}">{{ $__label }}</span>
            </a>
        </div>
    @endforeach
@endforeach
