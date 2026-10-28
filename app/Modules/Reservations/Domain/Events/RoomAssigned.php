<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A room was assigned to a reservation.
 */
final class RoomAssigned implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<array{date: string, price_minor: int}>  $nights
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly int $roomTypeId,
        public readonly ?int $roomUnitId,
        public readonly array $nights,
    ) {}
}
