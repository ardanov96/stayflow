<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A reservation was created.
 *
 * Published after the transaction committed: a listener never observes a
 * reservation that was rolled back.
 */
final class ReservationCreated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly string $code,
        public readonly string $status,
        public readonly string $source,
    ) {}
}
