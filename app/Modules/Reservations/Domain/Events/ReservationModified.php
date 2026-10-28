<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A reservation was modified.
 *
 * A modification (the dates, the guests, the number of people) is not a
 * state: it is a **versioned event** (docs/ARCHITECTURE.md §7). The
 * version is the number of the change, and it is what the callers use to
 * make sure they do not write over a change they did not read.
 */
final class ReservationModified implements ShouldDispatchAfterCommit
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly array $changes,
        public readonly int $version = 1,
    ) {}
}
