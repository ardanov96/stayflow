<?php

declare(strict_types=1);

use App\Modules\Reservations\Domain\Events\ReservationCreated;
use App\Modules\Reservations\Domain\Events\ReservationModified;
use App\Modules\Reservations\Domain\Events\ReservationStatusChanged;
use App\Modules\Reservations\Domain\Events\RoomAssigned;
use App\Modules\Reservations\Domain\ReservationStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The events of a reservation (task P2-01).
 *
 * A listener must never observe a change that was rolled back, so every
 * event of the module is published after the transaction committed.
 */
it('publishes the events of the module after the commit', function (string $event): void {
    expect(is_a($event, ShouldDispatchAfterCommit::class, true))->toBeTrue();
})->with([
    ReservationCreated::class,
    ReservationStatusChanged::class,
    ReservationModified::class,
    RoomAssigned::class,
]);

it('never publishes an event of a transaction that was rolled back', function (): void {
    $seen = 0;

    Event::listen(ReservationStatusChanged::class, function () use (&$seen): void {
        $seen++;
    });

    try {
        DB::transaction(function (): void {
            event(new ReservationStatusChanged(
                organizationId: 1,
                propertyId: 1,
                reservationId: 1,
                from: ReservationStatus::PendingPayment,
                to: ReservationStatus::Confirmed,
            ));

            throw new RuntimeException('the transaction is rolled back');
        });
    } catch (RuntimeException) {
        // The transaction was rolled back on purpose.
    }

    expect($seen)->toBe(0);
});

it('publishes the event once the transaction committed', function (): void {
    $seen = 0;

    Event::listen(ReservationStatusChanged::class, function () use (&$seen): void {
        $seen++;
    });

    DB::transaction(function (): void {
        event(new ReservationStatusChanged(
            organizationId: 1,
            propertyId: 1,
            reservationId: 1,
            from: ReservationStatus::PendingPayment,
            to: ReservationStatus::Confirmed,
        ));
    });

    expect($seen)->toBe(1);
});
