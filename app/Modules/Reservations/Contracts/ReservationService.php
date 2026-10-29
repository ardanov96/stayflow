<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Contracts;

use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\DTOs\ReservationModificationDTO;

/**
 * The reservations of the PMS, as the other modules see them.
 */
interface ReservationService
{
    /**
     * Read a reservation of the current organization.
     */
    public function find(int $reservationId): ?ReservationDTO;

    /**
     * Create a reservation for the staff of the hotel.
     *
     * The inventory is held and committed in the same transaction: either
     * the guest has a room and the inventory says so, or nothing happened.
     *
     * The request is idempotent: the same idempotency key creates **one**
     * reservation, never two.
     *
     * @param  list<int>  $companionGuestIds
     */
    public function create(
        int $propertyId,
        int $roomTypeId,
        int $ratePlanId,
        string $arrival,
        string $departure,
        int $adults,
        int $children,
        int $guestId,
        array $companionGuestIds = [],
        string $idempotencyKey = '',
        ?int $userId = null,
    ): ReservationDTO;

    /**
     * Modify a reservation.
     *
     * The inventory follows atomically: the nights the reservation no
     * longer covers go back to availability, the new nights are taken, and
     * the price is recalculated. A modification that cannot be satisfied
     * leaves the reservation exactly as it was.
     *
     * The result carries the **diff** the staff sees.
     *
     * @param  list<int>  $companionGuestIds
     */
    public function modify(
        int $reservationId,
        ?string $arrival = null,
        ?string $departure = null,
        ?int $roomTypeId = null,
        ?int $ratePlanId = null,
        ?int $adults = null,
        ?int $children = null,
        ?int $guestId = null,
        array $companionGuestIds = [],
        ?int $userId = null,
    ): ReservationModificationDTO;
}
