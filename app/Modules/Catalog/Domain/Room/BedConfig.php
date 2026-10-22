<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Room;

/**
 * Physical bed configuration of a room unit.
 *
 * Defined per DOMAIN_MODEL §2 and OQ-01.
 */
enum BedConfig: string
{
    case King = 'king';
    case Twin = 'twin';
    case KingTwin = 'king_twin';
    case Double = 'double';
    case Single = 'single';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::King,
            self::Twin,
            self::KingTwin,
            self::Double,
            self::Single,
        ];
    }
}
