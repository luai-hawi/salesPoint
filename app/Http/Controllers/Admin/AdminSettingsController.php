<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CurrencyFormatter;
use App\Services\Admin\PlatformSettings;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly CurrencyFormatter $currencies,
    ) {
    }

    public function index()
    {
        $settings = $this->settings->all();
        $currencyOptions = $this->currencies->options();

        return view('admin.settings.index', compact('settings', 'currencyOptions'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'support_whatsapp' => 'nullable|string|max:30',
            'support_phone' => 'nullable|string|max:30',
            'support_email' => 'nullable|email|max:255',
            'default_currency' => 'required|string|size:3',
            'default_country_code' => 'nullable|string|max:8',
            'default_trial_days' => 'required|integer|min:1|max:365',
            'default_subscription_cost' => 'required|numeric|min:0',
            'due_soon_days' => 'required|integer|min:1|max:180',
            'reminder_template_ar' => 'nullable|string|max:1000',
            'reminder_template_en' => 'nullable|string|max:1000',
            'announcement_enabled' => 'nullable|boolean',
            'announcement_tone' => 'nullable|string|in:gray,indigo,green,red,amber,blue,purple',
            'announcement_title_ar' => 'nullable|string|max:255',
            'announcement_title_en' => 'nullable|string|max:255',
            'announcement_body_ar' => 'nullable|string|max:1000',
            'announcement_body_en' => 'nullable|string|max:1000',
            'announcement_expires_at' => 'nullable|date',
        ]);

        $validated['announcement_enabled'] = $request->boolean('announcement_enabled');

        $this->settings->setMany($validated);

        ActivityLogger::record('updated', 'platform_settings', null, array_keys($validated));

        return redirect()->route('admin.settings.index')->with('success', __('admin.messages.settings_saved'));
    }
}
