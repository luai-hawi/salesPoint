<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out accounts that were disabled while they were logged in.
 * Employees are blocked as soon as the shop they work for is disabled (or deleted).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $blockedMessage = null;

            if ($user->is_active === false) {
                $blockedMessage = __('auth.account_suspended');
            } elseif ($user->role === 'disabled') {
                $blockedMessage = __('auth.disabled');
            } elseif ($user->role === 'employee') {
                $owner = $user->shopOwner;
                if (! $owner || $owner->role === 'disabled') {
                    $blockedMessage = __('auth.shop_disabled');
                } elseif ($owner->is_active === false) {
                    $blockedMessage = __('auth.account_suspended');
                }
            }

            if ($blockedMessage !== null) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => $blockedMessage], 403);
                }

                return redirect()->route('login')->withErrors(['email' => $blockedMessage]);
            }
        }

        return $next($request);
    }
}
