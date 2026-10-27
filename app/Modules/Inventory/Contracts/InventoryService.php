<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Contracts\DTOs\InventoryBlockDTO;
use App\Modules\Inventory\Contracts\DTOs\InventoryDayDTO;
use App\Modules\Inventory\Contracts\DTOs\InventoryQuoteDTO;
use App\Modules\Inventory\Contracts\DTOs\RestrictionDayDTO;

/**
 * Public service interface for the Inventory module.
 *
 * Per docs/ARCHITECTURE.md §5.2.
 */
interface InventoryService
{
    /**
     * Read-only price and availability; never reserves per docs/ARCHITECTURE.md §5.2.
     *
     * @param  array<int>  $roomTypeIds  If empty, quotes all active room types for property.
     */
    public function quote(
        int $propertyId,
        array $roomTypeIds,
        string $checkInDate,
        string $checkOutDate,
        int $adults,
        int $children = 0,
        ?int $ratePlanId = null,
    ): InventoryQuoteDTO;

    /**
     * Block units for a room type across a date range (e.g. maintenance, renovation).
     */
    public function block(
        int $propertyId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
        int $units,
        string $reason,
        ?int $userId = null,
    ): InventoryBlockDTO;

    /**
     * Release a block, returning blocked units back to availability.
     */
    public function unblock(int $blockId, ?int $userId = null): void;

    /**
     * Get availability records for a room type over a date range.
     *
     * @return array<int, InventoryDayDTO>
     */
    public function getAvailability(
        int $propertyId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
    ): array;

    /**
     * Set restrictions for a rate plan and room type over a date range.
     *
     * @return array<int, RestrictionDayDTO>
     */
    public function setRestrictions(
        int $propertyId,
        int $ratePlanId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
        ?int $minStay = null,
        ?int $maxStay = null,
        ?bool $cta = null,
        ?bool $ctd = null,
        ?bool $stopSell = null,
        ?int $userId = null,
    ): array;

    /**
     * Pre-materialize inventory day rows for a rolling window.
     *
     * @param  array<int>|null  $roomTypeIds
     * @return int Number of inventory day rows created or updated.
     */
    public function materializeDays(
        int $propertyId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $roomTypeIds = null,
    ): int;
}
