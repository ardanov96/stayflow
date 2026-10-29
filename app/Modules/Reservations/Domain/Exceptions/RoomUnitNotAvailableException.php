<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Exceptions;

use RuntimeException;

/**
 * A physical room that cannot be assigned.
 */
final class RoomUnitNotAvailableException extends RuntimeException
{
    public static function booked(int $roomUnitId): self
    {
        return new self(sprintf(
            'The room unit %d is already booked for one of the nights of the reservation.',
            $roomUnitId,
        ));
    }

    public static function unknown(int $roomUnitId): self
    {
        return new self(sprintf('The room unit %d does not exist.', $roomUnitId));
    }

    public static function wrongRoomType(int $roomUnitId, int $roomTypeId): self
    {
        return new self(sprintf(
            'The room unit %d is not a room of the type %d.',
            $roomUnitId,
            $roomTypeId,
        ));
    }

    public static function inactive(int $roomUnitId): self
    {
        return new self(sprintf('The room unit %d is not in service.', $roomUnitId));
    }
}
