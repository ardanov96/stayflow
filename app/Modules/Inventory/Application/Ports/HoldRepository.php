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
     * Lock the hold row that owns the idempotency key (FOR UPDATE).
     *
     * The idempotency row is always locked BEFORE the inventory day
     rows, so concurrent writers take the locks in the same order.
     */
    public function findByIdempotencyKeyForUpdate(int $organizationId, string $idempotencyKey): ?InventoryHoldDTO;

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
     * Move the hold from one status to another, but only when it is
     * still in the expected source status.
     *
     * @param  array<string, mixed>  $attributes
     * @return bool True when the hold was transitioned.
     */
    public function transition(
        int $organizationId,
        int $holdId,
        HoldStatus $from,
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
