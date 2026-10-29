<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Support;

use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Domain\Reservation;
use App\Modules\Reservations\Domain\ReservationRoom;
use App\Modules\Reservations\Domain\ReservationSource;
use App\Modules\Reservations\Domain\ReservationStatus;

/**
 * Rebuilds the aggregate of a stored reservation.
 *
 * The application works with the aggregate; the repository works with the
 * row. This is where one becomes the other.
 */
trait ResolvesReservationAggregate
{
    private function aggregate(ReservationDTO $stored): Reservation
    {
        $rooms = [];

        foreach ($stored->rooms as $room) {
            $rooms[] = new ReservationRoom(
                roomTypeId: (int) $room['room_type_id'],
                ratePlanId: $room['rate_plan_id'] !== null ? (int) $room['rate_plan_id'] : null,
                roomUnitId: $room['room_unit_id'] !== null ? (int) $room['room_unit_id'] : null,
                nights: (array) $room['nights'],
                id: (int) $room['id'],
            );
        }

        return Reservation::restore(
            id: $stored->id,
            organizationId: $stored->organizationId,
            propertyId: $stored->propertyId,
            code: $stored->code,
            source: ReservationSource::fromString($stored->source),
            channelRef: $stored->channelRef,
            guestId: $stored->guestId,
            arrival: $stored->arrival,
            departure: $stored->departure,
            adults: $stored->adults,
            children: $stored->children,
            totalMinor: $stored->totalMinor,
            currency: $stored->currency,
            status: ReservationStatus::from($stored->status),
            rooms: $rooms,
            version: $stored->version,
            holdId: $stored->holdId,
        );
    }
}
