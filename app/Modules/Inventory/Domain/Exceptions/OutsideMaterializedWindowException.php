<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class OutsideMaterializedWindowException extends DomainException
{
    public static function forDate(string $date): self
    {
        return new self("Requested date {$date} is outside the allowable inventory window.");
    }

    public static function unmaterialized(int $roomTypeId, string $date): self
    {
        return new self("Inventory has not been materialized for room type {$roomTypeId} on date {$date}.");
    }
}
