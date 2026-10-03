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
            $isImpersonationSession = Session::has('impersonator_id');

            if ($isImpersonationSession) {
                return $next($request);
            }

            $user->refresh();
            $claimed = $request->session()->get('program_session_claimed') === $user->id;
            if ($user->session_id !== $currentSessionId) {
                $cookie = $request->cookie(Auth::guard('web')->getRecallerName());
                $recaller = is_string($cookie) ? new \Illuminate\Auth\Recaller($cookie) : null;
                if (Auth::viaRemember() && $recaller?->valid()) {
                    $updated = $this->restoreRememberedSession($request, $user->id, $recaller);
                } elseif (! Auth::viaRemember() && ! $user->session_id && ! $claimed) {
                    $updated = $user->newQuery()->whereKey($user->id)
                        ->where('session_id', $user->session_id)
                        ->where('remember_token', $user->remember_token)
                        ->update(['session_id' => $currentSessionId]);
                } else {
                    $updated = 0;
                }

                if (! $updated) {
                    Auth::guard('web')->logoutCurrentDevice();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'session' => __('messages.session_expired_new_login'),
                    ]);
                }
            }

            $request->session()->put('program_session_claimed', $user->id);
        }

        return $next($request);
    }

    private function restoreRememberedSession(Request $request, int $userId, \Illuminate\Auth\Recaller $recaller): bool
    {
        return DB::transaction(function () use ($request, $userId, $recaller) {
            $user = \App\Models\User::query()->whereKey($userId)->lockForUpdate()->first();
            if (! $user || ! hash_equals((string) $user->getRememberToken(), $recaller->token())
                || ! hash_equals($user->getAuthPassword(), $recaller->hash())) {
                return false;
            }

            // Parallel remembered requests must restore one canonical session, not displace each other.
            $session = $request->session();
            if ($user->session_id) {
                $session->setId($user->session_id);
                $session->start();
            } else {
                $user->update(['session_id' => $session->getId()]);
            }
            $session->put(Auth::guard('web')->getName(), $user->id);
            $session->put('program_session_claimed', $user->id);
            Auth::guard('web')->setUser($user);
            $session->save();

            return true;
        });
    }
}
