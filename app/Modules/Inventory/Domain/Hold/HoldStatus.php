<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Hold;

/**
 * Lifecycle of an inventory hold.
 *
 * A hold is created Active and leaves that state exactly once:
 * committed (sold), released (given back), or expired (given back by the
 * expiry job). The terminal states are final per docs/ARCHITECTURE.md §5.3.
 */
enum HoldStatus: string
{
    case Active = 'active';
    case Committed = 'committed';
    case Released = 'released';
    case Expired = 'expired';

    /**
     * Whether the hold has left the Active state and can no longer change.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Active;
    }

    /**
     * Whether the hold is still holding units.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
