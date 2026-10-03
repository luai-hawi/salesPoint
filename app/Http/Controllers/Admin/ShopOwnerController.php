<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Admin\AccountStatus;
use App\Services\Admin\CurrencyFormatter;
use App\Services\Admin\PlatformSettings;
use App\Services\Admin\ShopPerformanceService;
use App\Services\Admin\ShopPurger;
use App\Services\Admin\ShopStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopOwnerController extends Controller
{
    public function __construct(
        private readonly AccountStatus $statusService,
        private readonly CurrencyFormatter $currencies,
        private readonly PlatformSettings $settings,
        private readonly ShopStorageService $storage,
        private readonly ShopPurger $purger,
        private readonly ShopPerformanceService $performance,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));
        $query = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->when($request->string('search')->value(), function ($users, $search) {
                $users->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('owner_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone_number', 'like', '%' . $search . '%');
                });
            })
            ->withCount('employees')
            ->select('users.*')
            ->selectSub(DB::table('products')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'products_count')
            ->selectSub(DB::table('customers')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'customers_count')
            ->selectSub(DB::table('bills')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'bills_count')
            ->selectSub(DB::table('purchase_bills')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id'), 'purchase_bills_count')
            ->selectSub(DB::table('activity_logs')->selectRaw('MAX(created_at)')->whereColumn('owner_id', 'users.id'), 'last_activity_at');

        $this->statusService->applyFilter($query, $request->string('status')->value());

        $sort = $request->string('sort')->value() ?: 'latest';
        match ($sort) {
            'name' => $query->orderBy('name'),
            'next_payment' => $query->orderByRaw('COALESCE(license_expires_at, temp_expires_at) asc'),
            'last_activity' => $query->orderByDesc('last_activity_at'),
            'usage' => $query->orderByRaw('(COALESCE(products_count,0)+COALESCE(customers_count,0)+COALESCE(bills_count,0)+COALESCE(purchase_bills_count,0)) desc'),
            default => $query->latest(),
        };

        $users = $query->paginate($perPage)->withQueryString();
        $imageStats = $this->storage->imageStatsForShops($users->pluck('id')->map(fn ($id) => (int) $id)->all(), $request->boolean('refresh'));

        $statusMap = [];
        foreach ($users as $user) {
            $statusMap[$user->id] = $this->statusService->describe($user);
        }
        $performance = $this->performance->monthSummaries(null, $users->pluck('id')->map(fn ($id) => (int) $id)->all());

        $counts = [
            'all' => User::query()->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))->count(),
            'active' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'active')->count(),
            'has_to_pay' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'has_to_pay')->count(),
            'trial' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'trial')->count(),
            'trial_ended' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'trial_ended')->count(),
            'disabled' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'disabled')->count(),
            'needs_attention' => $this->statusService->applyFilter($this->statusService->baseQuery(), 'needs_attention')->count(),
        ];

        return view('admin.shop-owners.index', [
            'users' => $users,
            'counts' => $counts,
            'statusMap' => $statusMap,
            'imageStats' => $imageStats,
            'performance' => $performance,
            'currencies' => $this->currencies,
        ]);
    }

    public function create()
    {
        return view('admin.shop-owners.create', [
            'settings' => $this->settings->all(),
            'currencyOptions' => $this->currencies->options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateShop($request);

        $validated['password'] = Hash::make($validated['password']);
        $validated['subscription_currency'] = $validated['subscription_currency'] ?: $this->settings->get('default_currency', 'ILS');
        $validated['subscription_cost'] = $validated['subscription_cost'] ?? $this->settings->get('default_subscription_cost', 300);
        $validated['entry_limit_mode'] = $validated['entry_limit_mode'] ?? 'off';
        $validated['role'] = $validated['role'] === 'disabled' ? 'shop_owner' : $validated['role'];

        if (($validated['account_type'] ?? 'temp') === 'temp') {
            $days = (int) ($validated['temp_period_days'] ?: $this->settings->get('default_trial_days', 14));
            $validated['temp_period_days'] = $days;
            $validated['temp_expires_at'] = now()->addDays($days)->toDateString();
            $validated['license_expires_at'] = null;
        }

        $shop = User::create($validated);
        ActivityLogger::record('created', 'shop_account', $shop, ['role' => $shop->role], null, $shop->name, $shop->id);

        return redirect()->route('admin.shop-owners.show', $shop)->with('success', __('admin.messages.shop_created'));
    }

    public function show(User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);

        $employees = User::query()->where('role', 'employee')->where('shop_owner_id', $shopOwner->id)->latest()->get();
        $status = $this->statusService->describe($shopOwner);
        $imageStats = $this->storage->imageStats($shopOwner->id, request()->boolean('refresh'));
        $activity = ActivityLog::query()->where('owner_id', $shopOwner->id)->latest('created_at')->limit(15)->get();
        $payments = $shopOwner->subscriptionPayments()->latest('paid_at')->latest('id')->get();
        $usage = [
            'bills' => (int) DB::table('bills')->where('user_id', $shopOwner->id)->count(),
            'products' => (int) DB::table('products')->where('user_id', $shopOwner->id)->count(),
            'customers' => (int) DB::table('customers')->where('user_id', $shopOwner->id)->count(),
            'purchase_bills' => (int) DB::table('purchase_bills')->where('user_id', $shopOwner->id)->count(),
        ];
        $preview = $this->purger->preview($shopOwner);
        $performance = $this->performance->forShop($shopOwner);
        $salesStats = [
            'today' => (float) $performance['today']['revenue'],
            'month' => (float) $performance['month']['revenue'],
        ];

        return view('admin.shop-owners.show', compact(
            'shopOwner',
            'employees',
            'status',
            'imageStats',
            'activity',
            'payments',
            'usage',
            'preview',
            'salesStats',
            'performance'
        ) + [
            'currencies' => $this->currencies,
            'currencyOptions' => $this->currencies->options(),
            'paymentIdempotencyKey' => $this->issuePaymentIdempotencyKey($shopOwner),
        ]);
    }

    public function edit(User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);

        return view('admin.shop-owners.edit', [
            'shopOwner' => $shopOwner,
            'settings' => $this->settings->all(),
            'currencyOptions' => $this->currencies->options(),
            'paymentHistory' => $shopOwner->subscriptionPayments()->latest('paid_at')->limit(5)->get(),
        ]);
    }

    public function update(Request $request, User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);
        $validated = $this->validateShop($request, $shopOwner);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (($validated['account_type'] ?? $shopOwner->account_type) === 'temp') {
            $days = (int) ($validated['temp_period_days'] ?: $shopOwner->temp_period_days ?: $this->settings->get('default_trial_days', 14));
            $validated['temp_period_days'] = $days;
            if ($request->filled('extend_days')) {
                $base = $shopOwner->temp_expires_at ? Carbon::parse($shopOwner->temp_expires_at) : now();
                $validated['temp_expires_at'] = $base->addDays((int) $request->integer('extend_days'))->toDateString();
            } elseif (! $shopOwner->temp_expires_at) {
                $validated['temp_expires_at'] = now()->addDays($days)->toDateString();
            }
        } elseif (! empty($validated['license_expires_at'])) {
            $validated['license_expires_at'] = Carbon::parse($validated['license_expires_at'])->toDateString();
        }

        if ($shopOwner->role === 'disabled') {
            $validated['role'] = 'disabled';
            $validated['disabled_from_role'] = $shopOwner->disabled_from_role ?: $shopOwner->businessRole();
            $validated['disabled_at'] = $shopOwner->disabled_at ?: now();
        }

        $shopOwner->update($validated);
        ActivityLogger::record('updated', 'shop_account', $shopOwner, ['role' => $shopOwner->role], null, $shopOwner->name, $shopOwner->id);

        return redirect()->route('admin.shop-owners.show', $shopOwner)->with('success', __('admin.messages.shop_updated'));
    }

    public function destroy(Request $request, User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);
        $request->validate([
            'confirmation' => 'required|string',
        ]);

        $expected = [$shopOwner->name, $shopOwner->email];
        if (! in_array($request->input('confirmation'), $expected, true)) {
            return back()->withErrors(['confirmation' => __('admin.messages.delete_confirmation_mismatch')]);
        }

        $name = $shopOwner->name;
        $ownerId = $shopOwner->id;
        $this->purger->purge($shopOwner);
        ActivityLogger::record('deleted', 'shop_account', null, ['name' => $name], null, $name, $ownerId);

        return redirect()->route('admin.shop-owners.index')->with('success', __('admin.messages.shop_deleted'));
    }

    public function toggleStatus(Request $request, User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);
        $request->validate([
            'disabled_reason' => 'nullable|string|max:60',
        ]);

        if ($shopOwner->role === 'disabled') {
            $restoredRole = $shopOwner->disabled_from_role ?: 'shop_owner';
            $shopOwner->update([
                'role' => $restoredRole,
                'disabled_at' => null,
                'disabled_reason' => null,
            ]);
            ActivityLogger::record('enabled', 'shop_account', $shopOwner, [], null, $shopOwner->name, $shopOwner->id);

            return back()->with('success', __('admin.messages.shop_enabled'));
        }

        $shopOwner->update([
            'disabled_from_role' => $shopOwner->role,
            'disabled_at' => now(),
            'disabled_reason' => $request->string('disabled_reason')->value() ?: null,
            'role' => 'disabled',
        ]);

        ActivityLogger::record('disabled', 'shop_account', $shopOwner, ['reason' => $shopOwner->disabled_reason], null, $shopOwner->name, $shopOwner->id);

        return back()->with('success', __('admin.messages.shop_disabled'));
    }

    public function markPaid(Request $request, User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);
        $validated = $request->validate([
            'months' => 'required|integer|min:1|max:120',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'method' => 'required|string|in:cash,transfer,card,check,other',
            'paid_at' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'continue_mode' => 'nullable|string|in:auto,current_expiry,today',
            'idempotency_key' => 'required|string|max:100',
        ]);

        $this->consumePaymentIdempotencyKey($shopOwner, $validated['idempotency_key']);
        $paidAt = Carbon::parse($validated['paid_at'])->startOfDay();
        $today = now()->startOfDay();

        DB::transaction(function () use ($shopOwner, $validated, $paidAt, $today) {
            $lockedShop = User::query()->whereKey($shopOwner->id)->lockForUpdate()->firstOrFail();
            $currentExpiry = $lockedShop->license_expires_at ? Carbon::parse($lockedShop->license_expires_at)->startOfDay() : null;
            $continueMode = $validated['continue_mode'] ?? 'auto';
            $start = match ($continueMode) {
                'current_expiry' => $currentExpiry && $currentExpiry->gte($today) ? $currentExpiry : $today,
                'today' => $today,
                default => $currentExpiry && $currentExpiry->gte($today) ? $currentExpiry : $today,
            };
            $periodStart = $start->copy();
            $periodEnd = $start->copy()->addMonthsNoOverflow((int) $validated['months']);

            SubscriptionPayment::create([
                'user_id' => $lockedShop->id,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'months' => $validated['months'],
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'paid_at' => $paidAt->toDateString(),
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $lockedShop->update([
                'subscription_paid' => true,
                'account_type' => 'full',
                'temp_expires_at' => null,
                'temp_period_days' => null,
                'license_expires_at' => $periodEnd->toDateString(),
                'last_payment_months' => $validated['months'],
                'last_payment_amount' => $validated['amount'],
                'subscription_cost' => $validated['amount'],
                'subscription_currency' => $validated['currency'],
            ]);
        });

        $shopOwner->refresh();

        ActivityLogger::record('payment_recorded', 'subscription', $shopOwner, [
            'months' => $validated['months'],
            'currency' => $validated['currency'],
            'amount' => $validated['amount'],
        ], (float) $validated['amount'], $shopOwner->name, $shopOwner->id);

        return back()->with('success', __('admin.messages.payment_recorded'));
    }

    public function deletePayment(User $shopOwner, SubscriptionPayment $payment)
    {
        $this->ensureManageableShop($shopOwner);
        abort_unless($payment->user_id === $shopOwner->id, 404);

        $amount = (float) $payment->amount;
        $payment->delete();

        $lastPayment = $shopOwner->subscriptionPayments()->latest('period_end')->latest('id')->first();
        $shopOwner->update([
            'license_expires_at' => $lastPayment?->period_end,
            'last_payment_months' => $lastPayment?->months,
            'last_payment_amount' => $lastPayment?->amount,
            'subscription_currency' => $lastPayment?->currency ?: $shopOwner->subscription_currency,
        ]);

        ActivityLogger::record('payment_deleted', 'subscription', $shopOwner, [], $amount, $shopOwner->name, $shopOwner->id);

        return back()->with('success', __('admin.messages.payment_deleted'));
    }

    public function note(Request $request, User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $shopOwner->update(['admin_notes' => $validated['admin_notes'] ?? null]);
        ActivityLogger::record('note_changed', 'shop_account', $shopOwner, [], null, $shopOwner->name, $shopOwner->id);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('admin.messages.note_saved')]);
        }

        return back()->with('success', __('admin.messages.note_saved'));
    }

    public function expiringLicenses(Request $request)
    {
        $filter = $request->string('window')->value() ?: 'all';
        $query = User::query()->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']));
        $this->statusService->applyFilter($query, $filter);
        $shops = $query->orderByRaw('COALESCE(license_expires_at, temp_expires_at) asc')->paginate(25)->withQueryString();

        $statusMap = [];
        $totals = [];
        foreach ($shops as $shop) {
            $statusMap[$shop->id] = $this->statusService->describe($shop);
            $currency = $statusMap[$shop->id]['currency'];
            $totals[$currency] ??= ['overdue' => 0, 'expected' => 0];
            if ($statusMap[$shop->id]['key'] === 'payment_overdue') {
                $totals[$currency]['overdue'] += (float) ($statusMap[$shop->id]['amount'] ?? 0);
            } else {
                $totals[$currency]['expected'] += (float) ($statusMap[$shop->id]['amount'] ?? 0);
            }
        }

        $menuExpiredUsers = collect();
        try {
            $menuDbPath = env('MENU_DB_PATH') ?: public_path('menu/database/database.sqlite');
            if (! file_exists($menuDbPath)) {
                $menuDbPath = base_path('../Menu/database/database.sqlite');
            }

            if (file_exists($menuDbPath)) {
                config(['database.connections.menu_sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $menuDbPath,
                    'prefix' => '',
                ]]);

                $menuExpiredUsers = collect(DB::connection('menu_sqlite')->select("
                    SELECT u.id, u.name, u.email, u.phone, s.amount, s.paid_at, s.expires_at, r.name AS restaurant_name
                    FROM subscriptions s
                    INNER JOIN users u ON u.id = s.user_id
                    LEFT JOIN restaurants r ON r.user_id = u.id
                    WHERE u.role != 'admin'
                      AND (s.paid_at IS NULL OR s.expires_at < datetime('now'))
                    ORDER BY s.expires_at ASC
                "));
            }
        } catch (\Throwable) {
            $menuExpiredUsers = collect();
        }

        return view('admin.shop-owners.expiring-licenses', compact('shops', 'statusMap', 'totals', 'menuExpiredUsers'));
    }

    public function convertToFull(User $shopOwner)
    {
        $this->ensureManageableShop($shopOwner);
        $shopOwner->update([
            'account_type' => 'full',
            'temp_expires_at' => null,
            'temp_period_days' => null,
            'license_expires_at' => $shopOwner->license_expires_at ?: now()->addMonths(1)->toDateString(),
        ]);

        ActivityLogger::record('converted', 'shop_account', $shopOwner, [], null, $shopOwner->name, $shopOwner->id);

        return back()->with('success', __('admin.messages.converted_to_full'));
    }

    public function deleteExpiredTempAccounts(Request $request)
    {
        $request->validate([
            'confirmation' => 'required|string',
        ]);
        if ($request->input('confirmation') !== __('admin.confirmations.bulk_delete_expired')) {
            return back()->withErrors(['confirmation' => __('admin.messages.bulk_confirmation_mismatch')]);
        }

        $accounts = User::query()
            ->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']))
            ->where('account_type', 'temp')
            ->whereNotNull('temp_expires_at')
            ->whereDate('temp_expires_at', '<', today())
            ->when($request->filled('user_ids'), fn ($query) => $query->whereIn('id', (array) $request->input('user_ids')))
            ->get();

        foreach ($accounts as $account) {
            $this->purger->purge($account);
        }

        return back()->with('success', __('admin.messages.expired_deleted', ['count' => $accounts->count()]));
    }

    public function disableExpiredTempAccounts()
    {
        $accounts = User::query()
            ->whereIn('role', User::OWNER_ROLES)
            ->where('account_type', 'temp')
            ->whereNotNull('temp_expires_at')
            ->whereDate('temp_expires_at', '<', today())
            ->get();

        foreach ($accounts as $account) {
            $account->update([
                'disabled_from_role' => $account->role,
                'disabled_at' => now(),
                'disabled_reason' => __('admin.messages.auto_disabled_reason'),
                'role' => 'disabled',
            ]);
        }

        return back()->with('success', __('admin.messages.expired_disabled', ['count' => $accounts->count()]));
    }

    public function deleteDisabledExpiredAccounts(Request $request)
    {
        $request->validate([
            'confirmation' => 'required|string',
        ]);
        if ($request->input('confirmation') !== __('admin.confirmations.bulk_delete_disabled_expired')) {
            return back()->withErrors(['confirmation' => __('admin.messages.bulk_confirmation_mismatch')]);
        }

        $accounts = User::query()
            ->where('role', 'disabled')
            ->where('account_type', 'temp')
            ->whereNotNull('temp_expires_at')
            ->whereDate('temp_expires_at', '<', today())
            ->when($request->filled('user_ids'), fn ($query) => $query->whereIn('id', (array) $request->input('user_ids')))
            ->get();

        foreach ($accounts as $account) {
            $this->purger->purge($account);
        }

        return back()->with('success', __('admin.messages.disabled_expired_deleted', ['count' => $accounts->count()]));
    }

    public function impersonate(User $shopOwner)
    {
        if ($shopOwner->role === 'admin') {
            return back()->withErrors(['error' => __('admin.messages.cannot_impersonate_admin')]);
        }

        $this->ensureManageableShop($shopOwner);

        if (! $shopOwner->isOwnerAccount() || $shopOwner->is_active === false) {
            return back()->withErrors(['error' => __('admin.messages.cannot_impersonate_inactive')]);
        }

        $adminId = auth()->id();
        ActivityLogger::record('impersonation_started', 'shop_account', $shopOwner, [], null, $shopOwner->name, $shopOwner->id);

        Auth::loginUsingId($shopOwner->id);
        request()->session()->regenerate();
        session([
            'impersonator_id' => $adminId,
            'impersonated_shop_name' => $shopOwner->name,
        ]);

        return redirect()->route('dashboard');
    }

    public function stopImpersonating()
    {
        $adminId = (int) session('impersonator_id');
        if ($adminId <= 0) {
            abort(403);
        }

        $shopName = session('impersonated_shop_name');
        Auth::loginUsingId($adminId);
        request()->session()->regenerate();
        session()->forget(['impersonator_id', 'impersonated_shop_name']);

        ActivityLogger::record('impersonation_stopped', 'shop_account', auth()->user(), ['shop' => $shopName], null, $shopName, $adminId);

        return redirect()->route('admin.dashboard')->with('success', __('admin.messages.impersonation_stopped'));
    }

    private function validateShop(Request $request, ?User $shopOwner = null): array
    {
        $allowedRoles = $shopOwner && $shopOwner->role === 'disabled'
            ? ['shop_owner', 'restaurant', 'merchant', 'disabled']
            : ['shop_owner', 'restaurant', 'merchant'];

        return $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($shopOwner?->id)],
            'password' => [$shopOwner ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in($allowedRoles)],
            'phone_number' => 'nullable|string|max:30',
            'subscription_cost' => 'nullable|numeric|min:0',
            'subscription_currency' => 'nullable|string|size:3',
            'image_limit' => 'nullable|integer|min:0|max:100000',
            'account_type' => 'nullable|in:full,temp',
            'temp_period_days' => 'nullable|integer|min:1|max:365',
            'extend_days' => 'nullable|integer|min:-365|max:365',
            'license_expires_at' => 'nullable|date',
            'blocked_features' => 'nullable|array',
            'blocked_features.*' => 'string|in:installments,sales_promotions,financial_dashboard',
            'entry_limit' => 'nullable|integer|min:0',
            'entry_limit_mode' => 'nullable|in:off,warn,block',
            'admin_notes' => 'nullable|string|max:5000',
            'disabled_reason' => 'nullable|string|max:60',
        ]);
    }

    private function ensureManageableShop(User $shopOwner): void
    {
        abort_unless(in_array($shopOwner->role, array_merge(User::OWNER_ROLES, ['disabled']), true), 404);
    }

    private function paymentTokenSessionKey(User $shopOwner): string
    {
        return 'admin.payment_tokens.' . $shopOwner->id;
    }

    private function issuePaymentIdempotencyKey(User $shopOwner): string
    {
        $token = (string) Str::uuid();
        $tokens = session($this->paymentTokenSessionKey($shopOwner), []);
        $tokens[$token] = true;
        session([$this->paymentTokenSessionKey($shopOwner) => $tokens]);

        return $token;
    }

    private function consumePaymentIdempotencyKey(User $shopOwner, string $token): void
    {
        $tokens = session($this->paymentTokenSessionKey($shopOwner), []);
        abort_unless(isset($tokens[$token]), 422, __('admin.messages.payment_already_processed'));

        unset($tokens[$token]);
        session([$this->paymentTokenSessionKey($shopOwner) => $tokens]);
    }
}
