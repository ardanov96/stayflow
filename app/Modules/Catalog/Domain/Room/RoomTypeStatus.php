<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Room;

/**
 * Operating status of a sellable RoomType.
 */
enum RoomTypeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Active,
            self::Inactive,
        ];
    }
}
