<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Admins and shop owners have all permissions
        if (in_array($user->role, ['admin', 'shop_owner', 'restaurant', 'merchant'])) {
            return $next($request);
        }

        // For employees, check permissions
        if ($user->role === 'employee') {
            foreach ($permissions as $permission) {
                if (!$user->hasPermission($permission)) {
                    abort(403, 'Unauthorized - Missing permission: ' . $permission);
                }
            }
        }

        return $next($request);
    }
}
