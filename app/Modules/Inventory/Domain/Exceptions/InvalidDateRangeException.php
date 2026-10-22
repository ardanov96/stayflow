<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class InvalidDateRangeException extends DomainException
{
    public static function checkOutBeforeCheckIn(string $checkIn, string $checkOut): self
    {
        return new self("Check-out date ({$checkOut}) must be strictly after check-in date ({$checkIn}).");
    }
}
