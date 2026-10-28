<?php

declare(strict_types=1);

use App\Modules\Reservations\Domain\Events\ReservationModified;
use App\Modules\Reservations\Domain\Events\ReservationStatusChanged;
use App\Modules\Reservations\Domain\Events\RoomAssigned;
use App\Modules\Reservations\Domain\Exceptions\InvalidReservationTransition;
use App\Modules\Reservations\Domain\Reservation;
use App\Modules\Reservations\Domain\ReservationRoom;
use App\Modules\Reservations\Domain\ReservationSource;
use App\Modules\Reservations\Domain\ReservationStatus;

/**
 * The state machine of a reservation (task P2-01).
 *
 * The transitions are checked **exhaustively**: every pair of states is
 * tried, and the ones that are not on the machine have to be refused.
 */
function reservationIn(ReservationStatus $status): Reservation
{
    $reservation = Reservation::pendingPayment(
        organizationId: 1,
        propertyId: 1,
        code: 'RSV-1',
        source: ReservationSource::direct(),
        channelRef: null,
        guestId: 1,
        arrival: '2026-11-01',
        departure: '2026-11-03',
        adults: 2,
        children: 0,
        totalMinor: 1000000,
        currency: 'IDR',
        rooms: [new ReservationRoom(roomTypeId: 1, ratePlanId: 1, nights: [
            ['date' => '2026-11-01', 'price_minor' => 500000],
            ['date' => '2026-11-02', 'price_minor' => 500000],
        ])],
    );

    $path = match ($status) {
        ReservationStatus::PendingPayment => [],
        ReservationStatus::Confirmed => [ReservationStatus::Confirmed],
        ReservationStatus::Expired => [ReservationStatus::Expired],
        ReservationStatus::Cancelled => [ReservationStatus::Cancelled],
        ReservationStatus::CheckedIn => [ReservationStatus::Confirmed, ReservationStatus::CheckedIn],
        ReservationStatus::CheckedOut => [
            ReservationStatus::Confirmed,
            ReservationStatus::CheckedIn,
            ReservationStatus::CheckedOut,
        ],
        ReservationStatus::NoShow => [ReservationStatus::Confirmed, ReservationStatus::NoShow],
    };

    foreach ($path as $step) {
        applyTransition($reservation, $step);
    }

    return $reservation;
}

function applyTransition(Reservation $reservation, ReservationStatus $to): void
{
    $reservation->transitionTo($to);
}

it('allows only the transitions of the state machine', function (
    ReservationStatus $from,
    ReservationStatus $to,
): void {
    $reservation = reservationIn($from);

    if ($from->canTransitionTo($to)) {
        applyTransition($reservation, $to);

        expect($reservation->status())->toBe($to);

        return;
    }

    expect(fn (): mixed => applyTransition($reservation, $to))
        ->toThrow(InvalidReservationTransition::class);

    // The refused move leaves the reservation where it was.
    expect($reservation->status())->toBe($from);
})->with(function (): array {
    $pairs = [];

    foreach (ReservationStatus::cases() as $from) {
        foreach (ReservationStatus::cases() as $to) {
            $pairs[$from->value.' -> '.$to->value] = [$from, $to];
        }
    }

    return $pairs;
});

it('has a final state for every reservation that left the hotel', function (): void {
    foreach (ReservationStatus::cases() as $status) {
        if ($status->allowedTransitions() === []) {
            expect($status->isTerminal())->toBeTrue();
        }
    }
});

it('records a versioned event on every change of state', function (): void {
    $reservation = reservationIn(ReservationStatus::PendingPayment);

    $version = $reservation->version();
    $events = count($reservation->events());

    $reservation->confirm();

    expect($reservation->version())->toBe($version + 1)
        ->and(count($reservation->events()))->toBe($events + 1)
        ->and($reservation->events()[count($reservation->events()) - 1])->toBeInstanceOf(
            ReservationStatusChanged::class,
        );
});

it('refuses to modify a reservation that is over', function (): void {
    $reservation = reservationIn(ReservationStatus::Cancelled);

    expect(fn (): mixed => $reservation->modify(['adults' => 3]))
        ->toThrow(InvalidReservationTransition::class);
});

it('bumps the version on a modification', function (): void {
    $reservation = reservationIn(ReservationStatus::Confirmed);
    $version = $reservation->version();
    $events = count($reservation->events());

    $reservation->modify(['adults' => 3, 'children' => 1]);

    expect($reservation->version())->toBe($version + 1)
        ->and(count($reservation->events()))->toBe($events + 1)
        ->and($reservation->events()[count($reservation->events()) - 1])->toBeInstanceOf(
            ReservationModified::class,
        );
});

it('assigns a physical room to a room of the reservation', function (): void {
    $reservation = reservationIn(ReservationStatus::Confirmed);

    $reservation->assignUnit(roomTypeId: 1, roomUnitId: 7);

    expect($reservation->rooms()[0]->roomUnitId)->toBe(7)
        ->and($reservation->rooms()[0]->isAssigned())->toBeTrue()
        ->and($reservation->events()[count($reservation->events()) - 1])->toBeInstanceOf(
            RoomAssigned::class,
        );
});

it('refuses to assign a room the reservation does not have', function (): void {
    $reservation = reservationIn(ReservationStatus::Confirmed);

    expect(fn (): mixed => $reservation->assignUnit(roomTypeId: 99, roomUnitId: 7))
        ->toThrow(InvalidArgumentException::class);
});
