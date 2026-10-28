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
    public function appendEvent(
        int $organizationId,
        int $reservationId,
        string $type,
        array $payload = [],
        ?int $actorId = null,
    ): void;
}
