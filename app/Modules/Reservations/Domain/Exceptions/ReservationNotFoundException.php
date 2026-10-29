<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Exceptions;

use RuntimeException;

/**
 * A reservation that does not exist.
 */
final class ReservationNotFoundException extends RuntimeException
{
    public static function forId(int $reservationId): self
    {
        return new self("The reservation {$reservationId} does not exist.");
    }
}
