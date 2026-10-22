<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class CircularDerivationException extends DomainException
{
    public static function forRatePlans(int $childId, int $parentId): self
    {
        return new self("Rate plan {$childId} cannot derive from rate plan {$parentId} because it creates a circular dependency.");
    }
}
