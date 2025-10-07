<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$params)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        $roles = [];
        $permissions = [];

        foreach ($params as $param) {
            // Handle comma-separated values
            if (str_contains($param, ',')) {
                $parts = explode(',', $param);
                foreach ($parts as $part) {
                    if (in_array($part, ['admin', 'shop_owner', 'employee', 'restaurant', 'merchant'])) {
                        $roles[] = $part;
                    } else {
                        $permissions[] = $part;
                    }
                }
            } else {
                if (in_array($param, ['admin', 'shop_owner', 'employee', 'restaurant', 'merchant'])) {
                    $roles[] = $param;
                } else {
                    $permissions[] = $param;
                }
            }
        }

        // Check roles
        if (!empty($roles) && !in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized');
        }

        // Check permissions for employees
        if ($user->role === 'employee' && !empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$user->hasPermission($permission)) {
                    abort(403, 'Unauthorized - Missing permission: ' . $permission);
                }
            }
        }

        return $next($request);
    }
}
