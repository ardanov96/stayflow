<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use RuntimeException;

/**
 * Raised when the inventory locks could not be acquired after the
 * configured number of retries (deadlock / lock-wait timeout).
 */
final class InventoryContentionException extends RuntimeException
{
    public static function afterRetries(int $attempts): self
    {
        return new self("Could not acquire the inventory locks after {$attempts} attempts. Please try again.");
    }
}
