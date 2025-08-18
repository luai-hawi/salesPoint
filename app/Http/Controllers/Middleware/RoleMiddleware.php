<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Handle "shop_owner,employee" passed as one string
        if (count($roles) === 1 && str_contains($roles[0], ',')) {
            $roles = explode(',', $roles[0]);
        }

        if (!in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
