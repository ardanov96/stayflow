<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Room;

/**
 * Operational housekeeping / availability status of a physical RoomUnit.
 */
enum RoomUnitStatus: string
{
    case Clean = 'clean';
    case Dirty = 'dirty';
    case Inspected = 'inspected';
    case OutOfOrder = 'out_of_order';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Clean,
            self::Dirty,
            self::Inspected,
            self::OutOfOrder,
        ];
    }
}
