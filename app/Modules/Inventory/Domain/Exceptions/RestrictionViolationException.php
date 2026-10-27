<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class RestrictionViolationException extends DomainException
{
    public static function minStayViolated(int $minStay, int $actualNights): self
    {
        return new self("Minimum stay restriction violated: required {$minStay} nights, requested {$actualNights} nights.");
    }

    public static function maxStayViolated(int $maxStay, int $actualNights): self
    {
        return new self("Maximum stay restriction violated: limit is {$maxStay} nights, requested {$actualNights} nights.");
    }

    public static function ctaViolated(string $checkInDate): self
    {
        return new self("Closed to Arrival (CTA) on {$checkInDate}.");
    }

    public static function ctdViolated(string $checkOutDate): self
    {
        return new self("Closed to Departure (CTD) on {$checkOutDate}.");
    }

    public static function stopSellViolated(int $roomTypeId, string $date): self
    {
        return new self("Stop sell restriction is active for room type {$roomTypeId} on {$date}.");
    }
}
