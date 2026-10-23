<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class InsufficientInventoryException extends DomainException
{
    public static function forDate(int $roomTypeId, string $date, int $requested, int $available): self
    {
        return new self("Insufficient inventory for room type {$roomTypeId} on {$date}: requested {$requested}, only {$available} available.");
    }
}
