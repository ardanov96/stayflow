<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class RatePriceNotFoundException extends DomainException
{
    public static function forDate(int $ratePlanId, int $roomTypeId, string $date): self
    {
        return new self("No rate price found for rate plan {$ratePlanId}, room type {$roomTypeId} on date {$date}.");
    }
}
