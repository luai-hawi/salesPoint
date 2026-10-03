<?php

namespace App\Services\Restaurant;

use App\Models\User;
use App\Support\PermissionCatalog;

class RestaurantSupport
{
    public static function ownerId(User $user): int
    {
        return (int) ($user->ownerId() ?? $user->id);
    }

    public static function ensureRestaurantAccount(User $user): void
    {
        if (! $user->isRestaurantAccount()) {
            abort(403);
        }
    }

    public static function ensurePermission(User $user, string $permission): void
    {
        self::ensureRestaurantAccount($user);

        if ($user->role === 'employee' && ! $user->hasPermission($permission)) {
            abort(403);
        }
    }

    public static function canViewInsights(User $user): bool
    {
        if (! $user->isRestaurantAccount()) {
            return false;
        }

        return $user->role !== 'employee'
            || $user->hasPermission('view_reports')
            || $user->hasPermission('view_financial');
    }

    public static function isKitchenOnly(User $user): bool
    {
        if ($user->role !== 'employee' || ! $user->isRestaurantAccount()) {
            return false;
        }

        $permissions = PermissionCatalog::normalize($user->getPermissions());

        return $permissions === ['view_kitchen'];
    }
}
