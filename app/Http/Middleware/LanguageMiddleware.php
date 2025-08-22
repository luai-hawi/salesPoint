<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Priority order: session -> cookie -> config default
        $locale = Session::get('locale') 
               ?? $request->cookie('locale') 
               ?? config('app.locale', 'en');
        
        // Validate locale
        if (in_array($locale, ['en', 'ar'])) {
            App::setLocale($locale);
            
            // Store in session if not already there
            if (!Session::has('locale')) {
                Session::put('locale', $locale);
            }
        } else {
            // Fallback to default
            $defaultLocale = config('app.locale', 'en');
            App::setLocale($defaultLocale);
            Session::put('locale', $defaultLocale);
        }
        
        return $next($request);
    }
}