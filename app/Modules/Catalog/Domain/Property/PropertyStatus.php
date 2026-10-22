<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Property;

/**
 * Operating status for a Property (hotel / villa).
 */
enum PropertyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Onboarding = 'onboarding';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Active,
            self::Inactive,
            self::Onboarding,
        ];
    }
}
