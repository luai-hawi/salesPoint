<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PreventConcurrentSessions
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $currentSessionId = Session::getId();

            // Only check if user has a stored session ID AND it's different from current
            if ($user->session_id && $user->session_id !== $currentSessionId) {
                // Check if the stored session still exists in database
                $existingSession = DB::table('sessions')
                    ->where('id', $user->session_id)
                    ->where('user_id', $user->id)
                    ->exists();

                if ($existingSession) {
                    // The current session is NOT the latest one, so this is an old session
                    // Log out this old session
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'session' => __('messages.session_expired_new_login')
                    ]);
                }
            }

            // This is either a new session or the current valid session
            // Update user's current session ID to this one
            $user->update(['session_id' => $currentSessionId]);
        }

        return $next($request);
    }
}