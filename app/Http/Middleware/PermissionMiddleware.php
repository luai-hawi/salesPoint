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

        // For employees, check permissions. A parameter like "a|b" passes when the employee has any of them.
        if ($user->role === 'employee') {
            foreach ($permissions as $permission) {
                $alternatives = array_filter(explode('|', $permission));
                $granted = false;
                foreach ($alternatives as $alternative) {
                    if ($user->hasPermission($alternative)) {
                        $granted = true;
                        break;
                    }
                }
                if (!$granted) {
                    abort(403, 'Unauthorized - Missing permission: ' . $permission);
                }
            }
        }

        return $next($request);
    }
}
