<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\Auth\EmailVerificationController;
use App\Modules\Platform\Http\Controllers\Auth\LoginController;
use App\Modules\Platform\Http\Controllers\Auth\LogoutController;
use App\Modules\Platform\Http\Controllers\Auth\NewPasswordController;
use App\Modules\Platform\Http\Controllers\Auth\PasswordResetLinkController;
use App\Modules\Platform\Http\Controllers\Auth\RecoveryCodesController;
use App\Modules\Platform\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Modules\Platform\Http\Controllers\Auth\TwoFactorDisableController;
use App\Modules\Platform\Http\Controllers\Auth\TwoFactorSetupController;
use App\Modules\Platform\Http\Middleware\EnsureTwoFactorIsConfirmed;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| P1-02 auth surface
|--------------------------------------------------------------------------
|
| Included from routes/web.php under the `web` middleware group so
| session, CSRF, locale, and tenant context all apply. Every route
| name is prefixed `auth.*` so the namespacing cannot collide with
| other feature areas.
*/

// ---- Guest routes (redirect to dashboard if already signed in) ----

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('auth.login');
    Route::post('login', [LoginController::class, 'store'])->name('auth.login.attempt');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('auth.password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('auth.password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('auth.password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('auth.password.store');

    // The 2FA challenge is a "guest" route because the user has
    // not yet completed login. The session holds a marker set by
    // LoginController@store; the controller checks it before
    // rendering the form.
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('auth.two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->name('auth.two-factor.challenge.attempt');
});

// ---- Signed verification link (no auth required, but the
// ---- signature itself is the bearer credential) ----

Route::middleware('signed')->group(function (): void {
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('auth.email.verify');
});

// ---- Authenticated routes ----

Route::middleware('auth')->group(function (): void {
    // The verification notice / resend lives in the auth group;
    // the user must be signed in to request a new link.
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('auth.email.resend');

    // Logout is a POST so a stray link prefetcher cannot sign a
    // user out (no GET-based CSRF gap).
    Route::post('logout', [LogoutController::class, 'destroy'])->name('auth.logout');

    // 2FA setup + recovery codes are gated behind the auth
    // middleware; the controller-side check rejects an
    // already-confirmed user from re-enrolling.
    Route::get('two-factor/setup', [TwoFactorSetupController::class, 'create'])
        ->name('auth.two-factor.setup');
    Route::post('two-factor/setup', [TwoFactorSetupController::class, 'store'])
        ->name('auth.two-factor.setup.begin');
    Route::post('two-factor/setup/confirm', [TwoFactorSetupController::class, 'confirm'])
        ->name('auth.two-factor.setup.confirm');

    Route::get('two-factor/recovery-codes', [RecoveryCodesController::class, 'show'])
        ->name('auth.two-factor.recovery-codes');
    Route::post('two-factor/recovery-codes/regenerate', [RecoveryCodesController::class, 'regenerate'])
        ->name('auth.two-factor.recovery-codes.regenerate');

    Route::post('two-factor/disable', [TwoFactorDisableController::class, 'destroy'])
        ->name('auth.two-factor.disable');
});

// ---- Routes that require 2FA to be confirmed ----
// The dashboard is moved into this group by the SetTenantContext
// refactor commit. Future protected modules hang their routes off
// this group too.

Route::middleware(['auth', EnsureTwoFactorIsConfirmed::class])->group(function (): void {
    // Reserved for P1-02 consumers. The dashboard route is
    // already registered in routes/web.php; the refactor in the
    // SetTenantContext commit moves it here.
});
