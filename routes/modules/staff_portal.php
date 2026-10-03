<?php

use App\Http\Controllers\Staff\PortalController;
use App\Http\Controllers\Staff\WebAuthnController;
use App\Http\Middleware\StaffPortalAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('staff/{key}')
    ->where(['key' => '[A-Za-z0-9\-_]+'])
    ->middleware([StaffPortalAuth::class])
    ->group(function () {
        Route::get('/', [PortalController::class, 'portal'])->name('staff.portal');
        Route::post('/login', [PortalController::class, 'login'])->name('staff.login');
        Route::post('/logout', [PortalController::class, 'logout'])->name('staff.logout');
        Route::get('/state', [PortalController::class, 'state'])->name('staff.state');
        Route::post('/punch', [PortalController::class, 'punch'])->name('staff.punch');
        Route::get('/history', [PortalController::class, 'history'])->name('staff.history');
        Route::get('/manifest.webmanifest', [PortalController::class, 'manifest'])->name('staff.manifest');
        Route::get('/lang/{locale}', [PortalController::class, 'lang'])->where('locale', 'ar|en')->name('staff.lang');

        Route::post('/webauthn/register/options', [WebAuthnController::class, 'registerOptions'])->name('staff.webauthn.register.options');
        Route::post('/webauthn/register/verify', [WebAuthnController::class, 'verifyRegistration'])->name('staff.webauthn.register.verify');
        Route::post('/webauthn/auth/options', [WebAuthnController::class, 'authOptions'])->name('staff.webauthn.auth.options');
    });
