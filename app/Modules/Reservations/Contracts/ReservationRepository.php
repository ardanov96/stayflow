<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Contracts;

use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Domain\Reservation;

/**
 * The reservations of the PMS.
 */
interface ReservationRepository
{
    public function find(int $organizationId, int $reservationId): ?ReservationDTO;

    public function findByCode(int $organizationId, int $propertyId, string $code): ?ReservationDTO;

    /**
     * The reservation that came from an inventory hold.
     */
    public function findByHold(int $organizationId, int $holdId): ?ReservationDTO;

    /**
     * The code of the next reservation of a property.
     *
     * The prefix carries the day of the property (the staff reads the
     * code out loud), so it is built by the caller.
     */
    public function nextCode(int $organizationId, int $propertyId, string $prefix): string;

    /**
     * Write the reservation.
     *
     * The update is guarded by the version: a caller that read the
     * reservation before a change does not write over it.
     */
    public function save(Reservation $reservation): void;

    /**
     * Write the rooms of the reservation.
     */
    public function saveRooms(Reservation $reservation): void;

    /**
     * Write the guests of the reservation.
     *
     * @param  list<int>  $companionGuestIds
     */
    public function saveGuests(Reservation $reservation, int $primaryGuestId, array $companionGuestIds): void;

    /**
     * Append an entry to the trail of the reservation.
     *
     * @param  array<string, mixed>  $payload
     */
    /**
     * Book the nights of a physical room for a reservation.
     *
     * The database refuses a night that is already booked: the unique
     * index on (room unit, night) is what enforces the invariant 7 of
     * docs/DOMAIN_MODEL.md section 3.
     *
     * @param  list<string>  $dates
     */
    public function bookNights(
        int $organizationId,
        int $reservationId,
        int $reservationRoomId,
        int $roomUnitId,
        array $dates,
    ): void;

    /**
     * Release the nights a physical room was booked for.
     *
     * Returns the number of nights that were released.
     */
    public function releaseNights(int $organizationId, int $reservationRoomId): int;

    /**
     * The nights a physical room is booked for.
     *
     * @return list<string>
     */
    public function bookedNightsOf(int $organizationId, int $roomUnitId): array;

    public function appendEvent(
        int $organizationId,
        int $reservationId,
        string $type,
        array $payload = [],
        ?int $actorId = null,
    ): void;
}
