<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'ar'])) {
        session(['locale' => $locale]);
        App::setLocale($locale);
        
        // Optional: Also set a cookie for persistence
        cookie()->queue('locale', $locale, 60 * 24 * 365); // 1 year
    }
    
    return redirect()->back();
})->name('lang.switch');