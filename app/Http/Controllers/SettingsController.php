<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // If shop owner, load their employee users for the visibility management section
        $employeeUsers = collect();
        if (in_array($user->role, ['shop_owner', 'restaurant', 'merchant'])) {
            $employeeUsers = \App\Models\User::where('role', 'employee')
                ->where('shop_owner_id', $user->id)
                ->orderBy('name')
                ->get();
        }

        return view('settings.index', compact('employeeUsers'));
    }

    public function updateProductSettings(Request $request)
    {
        $request->validate([
            'product_warning_period' => 'required|integer|min:1|max:24',
            'product_deactivation_period' => 'required|integer|min:1|max:36',
        ]);

        // Ensure deactivation period is greater than warning period
        if ($request->product_deactivation_period <= $request->product_warning_period) {
            return back()->withErrors([
                'product_deactivation_period' => __('messages.Deactivation period must be greater than warning period.')
            ])->withInput();
        }

        $user = Auth::user();
        $user->update([
            'product_warning_period' => $request->product_warning_period,
            'product_deactivation_period' => $request->product_deactivation_period,
        ]);

        return back()->with('success', __('messages.Product deactivation settings updated successfully.'));
    }

    public function updateVisibilitySettings(Request $request)
    {
        $keys = [
            'show_bills_total_sales',
            'show_bills_total_profit',
            'show_bills_count',
            'show_bill_total_value',
            'show_bill_profit_column',
            'show_dashboard_total_sales',
            'show_product_cost_price',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $request->boolean($key, false);
        }

        $user = Auth::user();
        $user->update(['visibility_settings' => $settings]);

        return back()->with('success', __('messages.Visibility settings updated successfully.'));
    }

    public function updateEmployeeVisibilitySettings(Request $request, \App\Models\User $user)
    {
        $owner = Auth::user();

        // Only shop owners (and admins) can update an employee's visibility settings
        if (!in_array($owner->role, ['admin', 'shop_owner', 'restaurant', 'merchant'])) {
            abort(403, __('messages.Unauthorized action.'));
        }

        // Ensure the target user is an employee that belongs to this owner
        if ($user->role !== 'employee' || $user->shop_owner_id !== $owner->id) {
            abort(403, __('messages.Unauthorized action.'));
        }

        $keys = [
            'show_bills_total_sales',
            'show_bills_total_profit',
            'show_bills_count',
            'show_bill_total_value',
            'show_bill_profit_column',
            'show_dashboard_total_sales',
            'show_product_cost_price',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $request->boolean($key, false);
        }

        $user->update(['visibility_settings' => $settings]);

        return back()->with('success', __('messages.Employee visibility settings updated successfully.'));
    }

    public function updateImageLimit(Request $request)
    {
        // Only admin can update image limits
        if (Auth::user()->role !== 'admin') {
            abort(403, __('messages.Unauthorized action.'));
        }

        $request->validate([
            'image_limit' => 'required|integer|min:0|max:10000',
        ]);

        $user = Auth::user();
        $user->update([
            'image_limit' => $request->image_limit,
        ]);

        return back()->with('success', __('messages.Image limit updated successfully.'));
    }
}
