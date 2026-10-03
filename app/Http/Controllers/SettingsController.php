<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Admin\ShopStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const VISIBILITY_KEYS = [
        'show_bills_total_sales',
        'show_bills_total_profit',
        'show_bills_count',
        'show_bill_total_value',
        'show_bill_profit_column',
        'show_dashboard_total_sales',
        'show_product_cost_price',
    ];

    public function __construct(private readonly ShopStorageService $shopStorageService)
    {
    }

    public function index(): View
    {
        $user = Auth::user();
        $ownerId = $user->ownerId() ?? $user->id;
        $imageStats = $ownerId ? $this->shopStorageService->imageStats($ownerId) : ['count' => 0, 'bytes' => 0, 'missing' => 0];

        return view('settings.index', [
            'user' => $user,
            'ownerAccount' => $user->role === 'employee' ? $user->shopOwner : $user,
            'visibilityKeys' => self::VISIBILITY_KEYS,
            'imageStats' => $imageStats,
        ]);
    }

    public function updateProductSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_warning_period' => ['required', 'integer', 'min:1', 'max:24'],
            'product_deactivation_period' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        if ($validated['product_deactivation_period'] <= $validated['product_warning_period']) {
            return back()
                ->withErrors(['product_deactivation_period' => __('settings.products.deactivation_after_warning')])
                ->withInput();
        }

        $user = Auth::user();
        $user->update($validated);

        return back()->with('success', __('settings.messages.products_updated'));
    }

    public function updateVisibilitySettings(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $user->update([
            'visibility_settings' => $this->mergedVisibilitySettings($user, $request),
        ]);

        return back()->with('success', __('settings.messages.visibility_updated'));
    }

    public function updatePosViewMode(Request $request): JsonResponse
    {
        $user = Auth::user();
        $settings = $user->visibility_settings ?? [];
        $settings['pos_slim_mode'] = $request->boolean('slim_mode');
        $user->update(['visibility_settings' => $settings]);

        return response()->json(['success' => true]);
    }

    public function updateEmployeeVisibilitySettings(Request $request, User $user): RedirectResponse
    {
        $owner = Auth::user();

        abort_unless($owner->isOwnerAccount(), 403, __('settings.messages.unauthorized'));
        abort_unless($user->role === 'employee' && $user->shop_owner_id === $owner->id, 403, __('settings.messages.unauthorized'));

        $user->update([
            'visibility_settings' => $this->mergedVisibilitySettings($user, $request),
        ]);

        return back()->with('success', __('settings.messages.employee_visibility_updated'));
    }

    public function updateImageLimit(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->role === 'admin', 403, __('settings.messages.unauthorized'));

        $validated = $request->validate([
            'image_limit' => ['required', 'integer', 'min:0', 'max:10000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $target = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : Auth::user();

        $target->update([
            'image_limit' => $validated['image_limit'],
        ]);

        return back()->with('success', __('settings.messages.image_limit_updated'));
    }

    private function mergedVisibilitySettings(User $user, Request $request): array
    {
        $existing = $user->visibility_settings ?? [];
        $preserved = array_diff_key($existing, array_flip(self::VISIBILITY_KEYS));
        $settings = [];

        foreach (self::VISIBILITY_KEYS as $key) {
            $settings[$key] = $request->boolean($key, false);
        }

        return array_merge($preserved, $settings);
    }
}
