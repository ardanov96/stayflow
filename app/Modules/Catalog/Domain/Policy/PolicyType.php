<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policy;

/**
 * Categorization of property policies.
 */
enum PolicyType: string
{
    case Cancellation = 'cancellation';
    case Payment = 'payment';
    case HouseRules = 'house_rules';
    case CheckInCheckOut = 'checkin_checkout';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Cancellation,
            self::Payment,
            self::HouseRules,
            self::CheckInCheckOut,
        ];
    }
}
