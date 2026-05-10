<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
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

            $model->user_id ??= $user->role === 'employee'
                ? $user->shop_owner_id
                : $user->id;
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
}
