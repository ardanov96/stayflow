<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShellController;
use App\Modules\Platform\Http\Controllers\TenantTestController;
use App\Modules\Platform\Http\Middleware\EnsureTwoFactorIsConfirmed;
use Illuminate\Support\Facades\Route;

// P1-02 auth surface (login, 2FA, password reset, email
// verification). Guest / auth / signed groups are applied inside.
require __DIR__.'/auth.php';

// Public dashboard. Guests see the shell view; the ShellController
// is unauthenticated. For authenticated users the
// EnsureTwoFactorIsConfirmed middleware on the /app route below
// redirects to /two-factor/setup if the role requires 2FA and it
// isn't configured yet.
Route::get('/', [ShellController::class, 'dashboard'])->name('dashboard');
Route::get('/shell', [ShellController::class, 'dashboard'])->name('shell.dashboard');

// Protected dashboard. The 2FA gate fires here so unauthenticated
// guests are NOT bounced to /two-factor/setup (the auth middleware
// would have already redirected them to /login if they tried).
Route::middleware([EnsureTwoFactorIsConfirmed::class])
    ->get('/app', [ShellController::class, 'dashboard'])
    ->name('app.dashboard');

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update');

// Test-only routes used by the P1-01 isolation suite and the local
// dev shell. Skipped in production so the routes are never exposed
// to a public ingress. The SetTenantContext middleware reads
// X-Organization-Id / X-Property-Id from the request; without those
// headers the routes return zero rows (the BelongsToTenant scope's
// defense-in-depth default).
if (! app()->environment('production')) {
    Route::prefix('_testing/tenant')->group(function (): void {
        Route::get('ping', [TenantTestController::class, 'ping'])->name('testing.tenant.ping');
        Route::get('properties', [TenantTestController::class, 'properties'])->name('testing.tenant.properties');
        Route::get('memberships', [TenantTestController::class, 'memberships'])->name('testing.tenant.memberships');
    });

    Route::prefix('_testing/rbac')->group(function (): void {
        Route::get('reservations', fn () => response()->json(['status' => 'ok']))
            ->middleware('permission:reservations.create')
            ->name('testing.rbac.reservations');

        Route::get('audit', fn () => response()->json(['status' => 'ok']))
            ->middleware('permission:audit.read')
            ->name('testing.rbac.audit');

        Route::get('folio-refund', fn () => response()->json(['status' => 'ok']))
            ->middleware('permission:folio.refund')
            ->name('testing.rbac.folio-refund');

        Route::get('housekeeping', fn () => response()->json(['status' => 'ok']))
            ->middleware('permission:housekeeping.manage')
            ->name('testing.rbac.housekeeping');

        Route::get('rates', fn () => response()->json(['status' => 'ok']))
            ->middleware('permission:rate_plans.manage')
            ->name('testing.rbac.rates');

        Route::get('promos', fn () => response()->json(['status' => 'ok']))
            ->middleware('permission:promos.manage')
            ->name('testing.rbac.promos');
    });
}
