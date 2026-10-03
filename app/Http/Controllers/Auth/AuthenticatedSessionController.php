<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Restaurant\RestaurantSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = DB::transaction(function () use ($request) {
            $user = \App\Models\User::query()->whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            $currentSessionId = Session::getId();
            $user->update(['session_id' => $currentSessionId]);
            $this->logoutOtherSessions($user, $currentSessionId);

            return $user;
        });
        $request->session()->put('program_session_claimed', $user->id);

        if ($user && RestaurantSupport::isKitchenOnly($user) && \Illuminate\Support\Facades\Route::has('kitchen.display')) {
            return redirect()->intended(route('kitchen.display', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user && ! $request->session()->has('impersonator_id')) {
            $user->newQuery()->whereKey($user->id)
                ->where('session_id', $request->session()->getId())
                ->update(['session_id' => null, 'remember_token' => \Illuminate\Support\Str::random(60)]);
        }

        Auth::guard('web')->logoutCurrentDevice();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Logout all other sessions for the user
     */
    protected function logoutOtherSessions($user, $currentSessionId)
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
