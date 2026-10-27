<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

use App\Modules\Inventory\Contracts\DTOs\InventoryHoldDTO;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldLineDTO;
use App\Modules\Inventory\Domain\Hold\HoldStatus;

interface HoldRepository
{
    public function find(int $organizationId, int $holdId): ?InventoryHoldDTO;

    /**
     * Lock the hold row (FOR UPDATE) so its status cannot change while
     * the caller works on it.
     */
    public function findForUpdate(int $organizationId, int $holdId): ?InventoryHoldDTO;

    public function findByIdempotencyKey(int $organizationId, string $idempotencyKey): ?InventoryHoldDTO;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InventoryHoldDTO;

    /**
     * @param  list<array{date: string, units: int}>  $lines
     */
    public function createLines(int $organizationId, int $holdId, int $propertyId, array $lines): void;

    /**
     * @return list<InventoryHoldLineDTO>
     */
    public function linesFor(int $organizationId, int $holdId): array;

    /**
     * Move the hold out of the `active` status, but only when it is
     * still active.
     *
     * The UPDATE is guarded by the current status, so exactly one of the
     * concurrent callers performs the transition. The caller uses the
     * return value to decide whether it owns the follow-up counter
     * mutation: the winner mutates the counters, the losers do not.
     *
     * There is deliberately no variant that takes the source status as
     * an argument: the only legal source of a terminal transition is
     * `active`, and hard-coding it removes the chance of a caller
     * guarding on the wrong status.
     *
     * @param  array<string, mixed>  $attributes
     * @return bool True when this caller performed the transition.
     */
    public function transitionFromActive(
        int $organizationId,
        int $holdId,
        HoldStatus $to,
        array $attributes = [],
    ): bool;

    /**
     * Active holds whose TTL has passed, oldest first.
     *
     * @return list<InventoryHoldDTO>
     */
    public function findExpired(int $organizationId, int $limit): array;
}
