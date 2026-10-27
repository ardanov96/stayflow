<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class HoldNotFoundException extends DomainException
{
    public static function forId(int $holdId): self
    {
        return new self("Inventory hold {$holdId} not found.");
    }
}
