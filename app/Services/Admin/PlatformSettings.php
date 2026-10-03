<?php

namespace App\Services\Admin;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PlatformSettings
{
    private const CACHE_KEY = 'platform_settings.all';

    private static ?array $memo = null;

    public function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        if (! Schema::hasTable('platform_settings')) {
            return self::$memo = $this->defaults();
        }

        $stored = Cache::remember(self::CACHE_KEY, 600, function (): array {
            return PlatformSetting::query()->pluck('value', 'key')->all();
        });

        $settings = $this->defaults();
        foreach ($stored as $key => $value) {
            $settings[$key] = $this->castOut($key, $value);
        }

        return self::$memo = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        foreach ($values as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $this->castIn($key, $value)]
            );
        }

        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public function defaults(): array
    {
        return [
            'support_whatsapp' => null,
            'support_phone' => null,
            'support_email' => null,
            'default_currency' => 'ILS',
            'default_country_code' => '970',
            'default_trial_days' => 14,
            'default_subscription_cost' => 300.00,
            'due_soon_days' => 30,
            'reminder_template_ar' => 'مرحباً {owner}، اشتراك متجر {shop} يستحق بتاريخ {date}. المبلغ المطلوب {amount} {currency}. المتبقي {days} يوم.',
            'reminder_template_en' => 'Hello {owner}, the subscription for {shop} is due on {date}. Amount due: {amount} {currency}. Days left: {days}.',
            'announcement_enabled' => false,
            'announcement_tone' => 'blue',
            'announcement_title_ar' => null,
            'announcement_title_en' => null,
            'announcement_body_ar' => null,
            'announcement_body_en' => null,
            'announcement_expires_at' => null,
        ];
    }

    private function castIn(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($key) {
            'announcement_enabled' => $value ? '1' : '0',
            'default_trial_days', 'due_soon_days' => (string) (int) $value,
            'default_subscription_cost' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }

    private function castOut(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($key) {
            'announcement_enabled' => in_array((string) $value, ['1', 'true'], true),
            'default_trial_days', 'due_soon_days' => (int) $value,
            'default_subscription_cost' => (float) $value,
            default => $value,
        };
    }
}
