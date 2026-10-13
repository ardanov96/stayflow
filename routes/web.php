<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShellController;
use App\Modules\Platform\Http\Controllers\TenantTestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShellController::class, 'dashboard'])->name('dashboard');
Route::get('/shell', [ShellController::class, 'dashboard'])->name('shell.dashboard');

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
}
