<?php

namespace App\Traits;

use App\Exceptions\EntryLimitReached;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Scopes\TenantScope;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

trait BelongsToTenant
{
    /** @var array<int, Lock> */
    protected static array $entryCreationLocks = [];

    /**
     * Boot the trait: register the global scope and auto-set user_id on create.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();
            $ownerId = $user->ownerId();

            if ($ownerId && self::shouldGuardEntryLimit($model)) {
                $owner = $user->role === 'employee' ? $user->shopOwner : $user;
                $mode = (string) ($owner?->entry_limit_mode ?: 'off');
                $limit = $owner?->entry_limit ? (int) $owner->entry_limit : null;

                if ($mode === 'block' && $limit) {
                    $lock = Cache::lock('entry-limit-owner-' . $ownerId, 5);
                    $lock->block(5, function () use ($owner, $ownerId, $limit, $model) {
                        $used = $owner->fresh()->getEntryUsage();

                        if ($used >= $limit) {
                            throw new EntryLimitReached(self::resourceKey($model), $ownerId, $limit, $used);
                        }
                    });
                    self::$entryCreationLocks[spl_object_id($model)] = $lock;
                }
            }

            $model->user_id ??= $ownerId;
        });

        static::created(function ($model) {
            self::releaseEntryCreationLock($model);
        });
    }

    /**
     * Bypass the tenant scope — use only when intentionally querying across owners
     * (e.g. admin panels, cross-owner reports).
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    private static function shouldGuardEntryLimit(object $model): bool
    {
        return $model instanceof Bill
            || $model instanceof Product
            || $model instanceof Customer
            || $model instanceof PurchaseBill;
    }

    private static function resourceKey(object $model): string
    {
        return match (true) {
            $model instanceof Bill => 'bills',
            $model instanceof Product => 'products',
            $model instanceof Customer => 'customers',
            $model instanceof PurchaseBill => 'purchase_bills',
            default => 'entries',
        };
    }

    private static function releaseEntryCreationLock(object $model): void
    {
        $key = spl_object_id($model);
        if (! isset(self::$entryCreationLocks[$key])) {
            return;
        }

        try {
            self::$entryCreationLocks[$key]->release();
        } finally {
            unset(self::$entryCreationLocks[$key]);
        }
    }
}
