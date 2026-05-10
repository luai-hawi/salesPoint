<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Automatically filters by the authenticated owner's user_id.
     * Works for both shop owners and their employees.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        $ownerId = $user->role === 'employee'
            ? $user->shop_owner_id
            : $user->id;

        $builder->where($model->getTable() . '.user_id', $ownerId);
    }
}
