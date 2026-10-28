<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Events;

use App\Modules\Reservations\Domain\ReservationStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * The status of a reservation changed.
 *
 * The move is always one the state machine allows
 * (docs/ARCHITECTURE.md §7).
 */
final class ReservationStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly ReservationStatus $from,
        public readonly ReservationStatus $to,
        public readonly ?string $reason = null,
        public readonly int $version = 1,
    ) {}
}
