<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Exceptions;

use RuntimeException;

/**
 * A reservation that cannot be created because the room type is not
 * available.
 */
final class ReservationNotAvailableException extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public static function forRoomType(int $roomTypeId, array $violations): self
    {
        return new self(sprintf(
            'The room type %d cannot be reserved: %s.',
            $roomTypeId,
            $violations === []
                ? 'there is no availability on the requested nights'
                : implode(' ', $violations),
        ));
    }
}
