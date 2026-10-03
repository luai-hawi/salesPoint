<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\HeldBill;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tag;
use App\Models\User;
use App\Services\Restaurant\RestaurantSupport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($redirect = $this->guardDashboardAccess($request, $user)) {
            return $redirect;
        }

        $ownerId = $user->ownerId();
        $layoutState = $this->resolveLayoutState($user);

        $customers = Customer::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) use ($ownerId) {
                $lastBillData = $customer->getLastBillData($ownerId);
                $customer->last_bill_amount = $lastBillData['amount'];
                $customer->last_bill_id = $lastBillData['bill_id'];
                $customer->last_bill_date = $lastBillData['date'];

                return $customer;
            });

        $products = Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->with('barcodes')
            ->select('id', 'name', 'selling_price', 'cost_price', 'barcode', 'pictures', 'quantity', 'category', 'has_tags', 'has_imeis')
            ->get()
            ->map(function (Product $product) {
                $product->barcodes = $product->barcodes->pluck('barcode')->toArray();

                return $product;
            });

        $categories = Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->toArray();

        $tags = Tag::withoutGlobalScopes()->where('user_id', $ownerId)->get();

        $totalToday = Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('created_at', Carbon::today())
            ->sum('total_price');

        $billsCount = Bill::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $warningMonths = $user->product_warning_period ?? 4;
        $deactivationMonths = $user->product_deactivation_period ?? 6;
        $warningProducts = Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('quantity', 0)
            ->where('is_active', true)
            ->whereNotNull('last_sale_date')
            ->where('last_sale_date', '<=', now()->subMonths($warningMonths))
            ->where('last_sale_date', '>', now()->subMonths($deactivationMonths))
            ->orderBy('last_sale_date', 'asc')
            ->take(5)
            ->get();

        $today = Carbon::today()->toDateString();
        $activeSales = Sale::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->with(['rules:id,sale_id,product_id,discount_type,discount_value,applies_every_n'])
            ->get(['id', 'name']);

        return view('dashboard', [
            'products' => $products,
            'totalToday' => $totalToday,
            'customers' => $customers,
            'warningProducts' => $warningProducts,
            'warningMonths' => $warningMonths,
            'deactivationMonths' => $deactivationMonths,
            'billsCount' => $billsCount,
            'categories' => $categories,
            'tags' => $tags,
            'activeSales' => $activeSales,
            'posLayout' => $layoutState['settings'],
            'posLayoutLocked' => $layoutState['locked'],
            'posLayoutTeamDefault' => $layoutState['team_default'],
            'posLayoutTeamLock' => $layoutState['team_lock'],
            'heldBillsCount' => HeldBill::query()->count(),
            'heldRecoverySnapshot' => $request->session()->get('pos_held_recovery'),
            'heldRecoveryHeldBillId' => $request->session()->get('pos_held_recovery_id'),
        ]);
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = $this->resolveLayoutState($user);

        if ($state['locked']) {
            abort(403);
        }

        $settings = $this->sanitizeLayoutSettings($request->input('settings', []), $user);

        $current = $user->pos_settings ?? [];
        $teamMeta = $user->isOwnerAccount()
            ? array_intersect_key($current, array_flip(['team_default', 'team_lock']))
            : [];

        $user->update([
            'pos_settings' => array_merge($settings, $teamMeta),
        ]);

        $this->syncLegacySlimMode($user, $settings['preset'] === 'focus');

        return response()->json([
            'success' => true,
            'settings' => $this->resolveLayoutState($user->fresh())['settings'],
        ]);
    }

    public function resetLayout(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = $this->resolveLayoutState($user);

        if ($state['locked']) {
            abort(403);
        }

        $current = $user->pos_settings ?? [];
        $teamMeta = $user->isOwnerAccount()
            ? array_intersect_key($current, array_flip(['team_default', 'team_lock']))
            : [];

        $user->update([
            'pos_settings' => $teamMeta ?: null,
        ]);

        $resolved = $this->resolveLayoutState($user->fresh());

        return response()->json([
            'success' => true,
            'settings' => $resolved['settings'],
            'locked' => $resolved['locked'],
        ]);
    }

    public function applyTeamLayout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isOwnerAccount()) {
            abort(403);
        }

        $settings = $this->sanitizeLayoutSettings(
            $request->input('settings', $this->extractOwnLayoutSettings($user->pos_settings ?? [], $user)),
            $user
        );

        $current = $user->pos_settings ?? [];
        $own = $this->extractOwnLayoutSettings($current, $user);
        $own = $own ?: $settings;

        $user->update([
            'pos_settings' => array_merge($own, [
                'team_default' => $settings,
                'team_lock' => $request->boolean('lock'),
            ]),
        ]);

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'lock' => $request->boolean('lock'),
        ]);
    }

    public function updateLegacyViewMode(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = $this->resolveLayoutState($user);

        if ($state['locked']) {
            abort(403);
        }

        $slim = $request->boolean('slim_mode');
        $current = $user->pos_settings ?? [];
        $settings = $this->sanitizeLayoutSettings(array_merge(
            $this->extractOwnLayoutSettings($current, $user),
            ['preset' => $slim ? 'focus' : 'classic']
        ), $user);

        $teamMeta = $user->isOwnerAccount()
            ? array_intersect_key($current, array_flip(['team_default', 'team_lock']))
            : [];

        $user->update([
            'pos_settings' => array_merge($settings, $teamMeta),
        ]);

        $this->syncLegacySlimMode($user, $slim);

        return response()->json(['success' => true, 'settings' => $settings]);
    }

    /**
     * @return array{settings: array<string, mixed>, locked: bool, team_default: ?array, team_lock: bool}
     */
    private function resolveLayoutState(User $user): array
    {
        $legacySlim = (bool) data_get($user->visibility_settings, 'pos_slim_mode', false);
        $current = $user->pos_settings ?? [];
        $own = $this->extractOwnLayoutSettings($current, $user);
        $owner = $user->role === 'employee' ? $user->shopOwner : null;
        $ownerSettings = $owner?->pos_settings ?? [];
        $teamDefault = is_array($ownerSettings['team_default'] ?? null)
            ? $this->sanitizeLayoutSettings($ownerSettings['team_default'], $owner ?? $user)
            : null;
        $teamLock = (bool) ($user->role === 'employee'
            ? ($ownerSettings['team_lock'] ?? false)
            : ($current['team_lock'] ?? false));
        $locked = $user->role === 'employee' && $teamLock;

        if ($locked) {
            return [
                'settings' => $teamDefault ?: $this->defaultLayoutSettings($legacySlim),
                'locked' => true,
                'team_default' => $teamDefault,
                'team_lock' => $teamLock,
            ];
        }

        if ($own !== []) {
            return [
                'settings' => $this->sanitizeLayoutSettings($own, $user),
                'locked' => false,
                'team_default' => $teamDefault,
                'team_lock' => $teamLock,
            ];
        }

        if ($teamDefault) {
            return [
                'settings' => $teamDefault,
                'locked' => false,
                'team_default' => $teamDefault,
                'team_lock' => $teamLock,
            ];
        }

        return [
            'settings' => $this->defaultLayoutSettings($legacySlim),
            'locked' => false,
            'team_default' => $teamDefault,
            'team_lock' => $teamLock,
        ];
    }

    private function guardDashboardAccess(Request $request, User $user): ?RedirectResponse
    {
        if ($user->role !== 'employee') {
            return null;
        }

        if (RestaurantSupport::isKitchenOnly($user) && \Route::has('kitchen.display')) {
            $request->session()->forget('url.intended');

            return redirect()->route('kitchen.display');
        }

        if (! $user->hasPermission('create_bills')) {
            abort(403);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    private function extractOwnLayoutSettings(?array $settings, User $user): array
    {
        if (! is_array($settings)) {
            return [];
        }

        return collect($settings)
            ->except(['team_default', 'team_lock'])
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sanitizeLayoutSettings(array $input, User $user): array
    {
        $default = $this->defaultLayoutSettings(
            (bool) data_get($user->visibility_settings, 'pos_slim_mode', false)
        );

        $preset = (string) ($input['preset'] ?? $default['preset']);
        $allowedPresets = ['classic', 'focus', 'visual', 'cashier', 'custom'];
        if (! in_array($preset, $allowedPresets, true)) {
            $preset = $default['preset'];
        }

        $gridColumns = $input['grid_columns'] ?? $default['grid_columns'];
        if ($gridColumns !== 'auto') {
            $gridColumns = max(2, min(8, (int) $gridColumns));
        }

        $settings = [
            'preset' => $preset,
            'products_width' => max(25, min(75, (int) ($input['products_width'] ?? $default['products_width']))),
            'splitter_position' => max(25, min(75, (int) ($input['splitter_position'] ?? ($input['products_width'] ?? $default['products_width'])))),
            'product_card_size' => $this->enum($input['product_card_size'] ?? null, ['s', 'm', 'l', 'xl'], $default['product_card_size']),
            'image_aspect' => $this->enum($input['image_aspect'] ?? null, ['square', '4:3', '16:9', 'cover'], $default['image_aspect']),
            'show_image' => array_key_exists('show_image', $input) ? (bool) $input['show_image'] : $default['show_image'],
            'price_badge_size' => $this->enum($input['price_badge_size'] ?? null, ['sm', 'md', 'lg'], $default['price_badge_size']),
            'show_stock' => array_key_exists('show_stock', $input) ? (bool) $input['show_stock'] : $default['show_stock'],
            'show_category_badge' => array_key_exists('show_category_badge', $input) ? (bool) $input['show_category_badge'] : $default['show_category_badge'],
            'grid_columns' => $gridColumns,
            'category_bar' => array_key_exists('category_bar', $input) ? (bool) $input['category_bar'] : $default['category_bar'],
            'bill_side' => $this->enum($input['bill_side'] ?? null, ['start', 'end'], $default['bill_side']),
            'summary_position' => $this->enum($input['summary_position'] ?? null, ['side', 'under', 'hidden'], $default['summary_position']),
            'density' => $this->enum($input['density'] ?? null, ['comfortable', 'compact'], $default['density']),
            'font_scale' => max(90, min(130, (int) ($input['font_scale'] ?? $default['font_scale']))),
            'kiosk_mode' => array_key_exists('kiosk_mode', $input) ? (bool) $input['kiosk_mode'] : $default['kiosk_mode'],
            'quick_actions' => array_key_exists('quick_actions', $input) ? (bool) $input['quick_actions'] : $default['quick_actions'],
            'products_tall' => array_key_exists('products_tall', $input) ? (bool) $input['products_tall'] : $default['products_tall'],
        ];

        if ($settings['preset'] === 'classic') {
            $settings['summary_position'] = 'side';
            $settings['products_width'] = 33;
            $settings['splitter_position'] = 33;
            $settings['quick_actions'] = true;
            $settings['products_tall'] = false;
        } elseif ($settings['preset'] === 'focus') {
            $settings['summary_position'] = 'side';
            $settings['products_width'] = 50;
            $settings['splitter_position'] = 50;
            $settings['quick_actions'] = false;
            $settings['products_tall'] = true;
        } elseif ($settings['preset'] === 'visual') {
            $settings['summary_position'] = 'under';
            $settings['products_width'] = max($settings['products_width'], 58);
            $settings['splitter_position'] = $settings['products_width'];
            $settings['product_card_size'] = 'xl';
            $settings['grid_columns'] = $gridColumns === 'auto' ? 3 : max(2, min(4, (int) $gridColumns));
            $settings['show_image'] = true;
            $settings['category_bar'] = true;
            $settings['products_tall'] = true;
        } elseif ($settings['preset'] === 'cashier') {
            $settings['summary_position'] = 'side';
            $settings['products_width'] = 38;
            $settings['splitter_position'] = 38;
            $settings['product_card_size'] = 's';
            $settings['density'] = 'compact';
            $settings['show_image'] = (bool) ($input['show_image'] ?? false);
            $settings['products_tall'] = false;
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLayoutSettings(bool $legacySlim = false): array
    {
        return [
            'preset' => $legacySlim ? 'focus' : 'classic',
            'products_width' => $legacySlim ? 50 : 33,
            'splitter_position' => $legacySlim ? 50 : 33,
            'product_card_size' => 'm',
            'image_aspect' => 'square',
            'show_image' => true,
            'price_badge_size' => 'md',
            'show_stock' => true,
            'show_category_badge' => true,
            'grid_columns' => 'auto',
            'category_bar' => false,
            'bill_side' => 'end',
            'summary_position' => 'side',
            'density' => 'comfortable',
            'font_scale' => 100,
            'kiosk_mode' => false,
            'quick_actions' => ! $legacySlim,
            'products_tall' => $legacySlim,
        ];
    }

    private function enum(mixed $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $fallback;
    }

    private function syncLegacySlimMode(User $user, bool $slim): void
    {
        $visibility = $user->visibility_settings ?? [];
        $visibility['pos_slim_mode'] = $slim;
        $user->forceFill(['visibility_settings' => $visibility])->save();
    }
}
