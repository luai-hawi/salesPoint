<?php

namespace App\Services\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AccountStatus
{
    public function __construct(
        private readonly PlatformSettings $settings,
    ) {
    }

    /**
     * @return array{key:string,tone:string,label:string,days_left:?int,next_payment_date:?string,amount:?float,currency:string,needs_attention:bool,sort_priority:int,reason:?string}
     */
    public function describe(User $shop): array
    {
        $today = now()->startOfDay();
        $dueSoon = $this->dueSoonDays();
        $currency = $shop->subscription_currency ?: (string) $this->settings->get('default_currency', 'ILS');
        $amount = $shop->subscription_cost !== null ? (float) $shop->subscription_cost : null;

        if ($shop->role === 'disabled') {
            return $this->payload('disabled', 'red', null, $shop->license_expires_at ?? $shop->temp_expires_at, $amount, $currency, 0, true, $shop->disabled_reason);
        }

        if ($shop->account_type === 'temp') {
            $daysLeft = $shop->temp_expires_at ? $today->diffInDays(Carbon::parse($shop->temp_expires_at)->startOfDay(), false) : null;

            if ($daysLeft !== null && $daysLeft < 0) {
                return $this->payload('trial_ended', 'red', $daysLeft, $shop->temp_expires_at, $amount, $currency, 1, true);
            }

            if ($daysLeft !== null && $daysLeft <= 7) {
                return $this->payload('trial_ending', 'amber', $daysLeft, $shop->temp_expires_at, $amount, $currency, 2, true);
            }

            return $this->payload('trial_active', 'blue', $daysLeft, $shop->temp_expires_at, $amount, $currency, 6, false);
        }

        if (! $shop->license_expires_at) {
            return $this->payload('license_missing', 'amber', null, null, $amount, $currency, 3, true);
        }

        $daysLeft = $today->diffInDays(Carbon::parse($shop->license_expires_at)->startOfDay(), false);

        if ($daysLeft < 0) {
            return $this->payload('payment_overdue', 'red', $daysLeft, $shop->license_expires_at, $amount, $currency, 1, true);
        }

        if ($daysLeft <= $dueSoon) {
            return $this->payload('payment_due_soon', 'amber', $daysLeft, $shop->license_expires_at, $amount, $currency, 2, true);
        }

        return $this->payload('active', 'green', $daysLeft, $shop->license_expires_at, $amount, $currency, 9, false);
    }

    public function applyFilter(Builder $query, ?string $filter): Builder
    {
        $filter = $filter ?: 'all';
        $today = now()->toDateString();
        $soon = now()->addDays($this->dueSoonDays())->toDateString();

        return match ($filter) {
            'active' => $query->whereIn('role', User::OWNER_ROLES)
                ->where('account_type', 'full')
                ->whereNotNull('license_expires_at')
                ->whereDate('license_expires_at', '>', $soon),
            'has_to_pay' => $query->whereIn('role', User::OWNER_ROLES)
                ->where('account_type', 'full')
                ->whereNotNull('license_expires_at')
                ->whereDate('license_expires_at', '<=', $soon),
            'trial' => $query->whereIn('role', User::OWNER_ROLES)
                ->where('account_type', 'temp')
                ->where(function (Builder $trial) use ($today) {
                    $trial->whereNull('temp_expires_at')
                        ->orWhereDate('temp_expires_at', '>=', $today);
                }),
            'trial_ended' => $query->whereIn('role', User::OWNER_ROLES)
                ->where('account_type', 'temp')
                ->whereNotNull('temp_expires_at')
                ->whereDate('temp_expires_at', '<', $today),
            'disabled' => $query->where('role', 'disabled'),
            'needs_attention' => $query->where(function (Builder $attention) use ($today, $soon) {
                $attention->where('role', 'disabled')
                    ->orWhere(function (Builder $trial) use ($today) {
                        $trial->whereIn('role', User::OWNER_ROLES)
                            ->where('account_type', 'temp')
                            ->whereNotNull('temp_expires_at')
                            ->whereDate('temp_expires_at', '<=', now()->addDays(7)->toDateString());
                    })
                    ->orWhere(function (Builder $full) use ($soon) {
                        $full->whereIn('role', User::OWNER_ROLES)
                            ->where('account_type', 'full')
                            ->where(function (Builder $license) use ($soon) {
                                $license->whereNull('license_expires_at')
                                    ->orWhereDate('license_expires_at', '<=', $soon);
                            });
                    });
            }),
            'overdue' => $query->whereIn('role', User::OWNER_ROLES)
                ->where('account_type', 'full')
                ->whereNotNull('license_expires_at')
                ->whereDate('license_expires_at', '<', $today),
            'due_7' => $this->windowFilter($query, 7),
            'due_14' => $this->windowFilter($query, 14),
            'due_30' => $this->windowFilter($query, 30),
            'due_60' => $this->windowFilter($query, 60),
            'due_90' => $this->windowFilter($query, 90),
            'no_date' => $query->whereIn('role', User::OWNER_ROLES)
                ->where('account_type', 'full')
                ->whereNull('license_expires_at'),
            'all' => $query,
            default => $query,
        };
    }

    public function dueSoonDays(): int
    {
        return max(1, (int) $this->settings->get('due_soon_days', 30));
    }

    public function dueCount(): int
    {
        return $this->applyFilter($this->baseQuery(), 'has_to_pay')->count();
    }

    public function baseQuery(): Builder
    {
        return User::query()->whereIn('role', array_merge(User::OWNER_ROLES, ['disabled']));
    }

    private function windowFilter(Builder $query, int $days): Builder
    {
        $today = now()->toDateString();
        $end = now()->addDays($days)->toDateString();

        return $query->whereIn('role', User::OWNER_ROLES)
            ->where(function (Builder $window) use ($today, $end) {
                $window->where(function (Builder $full) use ($today, $end) {
                    $full->where('account_type', 'full')
                        ->whereNotNull('license_expires_at')
                        ->whereBetween('license_expires_at', [$today, $end]);
                })->orWhere(function (Builder $trial) use ($today, $end) {
                    $trial->where('account_type', 'temp')
                        ->whereNotNull('temp_expires_at')
                        ->whereBetween('temp_expires_at', [$today, $end]);
                });
            });
    }

    private function payload(
        string $key,
        string $tone,
        ?int $daysLeft,
        mixed $date,
        ?float $amount,
        string $currency,
        int $priority,
        bool $needsAttention,
        ?string $reason = null,
    ): array {
        return [
            'key' => $key,
            'tone' => $tone,
            'label' => __('admin.statuses.' . $key),
            'days_left' => $daysLeft,
            'next_payment_date' => $date ? Carbon::parse($date)->toDateString() : null,
            'amount' => $amount,
            'currency' => $currency,
            'needs_attention' => $needsAttention,
            'sort_priority' => $priority,
            'reason' => $reason,
        ];
    }
}
