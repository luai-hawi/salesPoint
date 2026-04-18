<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index');
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
