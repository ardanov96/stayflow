<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class InventoryBlockNotFoundException extends DomainException
{
    public static function forId(int $blockId): self
    {
        return new self("Inventory block {$blockId} not found.");
    }
}
