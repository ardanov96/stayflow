<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Contracts;

use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;

/**
 * The reservations of the PMS, as the other modules see them.
 */
interface ReservationService
{
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
}
