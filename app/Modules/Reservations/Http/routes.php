<?php

declare(strict_types=1);

use App\Modules\Reservations\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/reservations')->middleware(['web', 'auth'])->group(function (): void {
    Route::post('/', [ReservationController::class, 'store'])
        ->middleware('permission:reservations.create')
        ->name('reservations.store');
});
