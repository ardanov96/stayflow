<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Published after the inventory counters changed and the transaction
 * committed (docs/ARCHITECTURE.md §5.3).
 *
 * `ShouldDispatchAfterCommit` guarantees the listeners never observe a
 * change that was rolled back.
 *
 * NOTE: this event is published with the `event()` helper, NOT with the
 * `Dispatchable` trait: in Laravel 11+ that trait dispatches QUEUED
 * jobs, not events.
 */
final class InventoryChanged implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $roomTypeIds
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly array $roomTypeIds,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $reason,
    ) {}
}
