<?php

declare(strict_types=1);

use App\Modules\Reservations\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/reservations')->middleware(['web', 'auth'])->group(function (): void {
    Route::post('/', [ReservationController::class, 'store'])
        ->middleware('permission:reservations.create')
        ->name('reservations.store');

    Route::patch('/{id}', [ReservationController::class, 'update'])
        ->middleware('permission:reservations.update')
        ->name('reservations.update');

    // The physical room of a reservation.
    Route::post('/{id}/room-units', [ReservationController::class, 'assignRoomUnit'])
        ->middleware('permission:reservations.update')
        ->name('reservations.room-units.assign');

    Route::post('/{id}/room-units/move', [ReservationController::class, 'moveRoomUnit'])
        ->middleware('permission:reservations.update')
        ->name('reservations.room-units.move');

    Route::delete('/{id}/room-units/{roomTypeId}', [ReservationController::class, 'unassignRoomUnit'])
        ->middleware('permission:reservations.update')
        ->name('reservations.room-units.unassign');

    Route::get('/{id}/room-units/suggestions', [ReservationController::class, 'suggestRoomUnits'])
        ->middleware('permission:reservations.read')
        ->name('reservations.room-units.suggestions');
});
