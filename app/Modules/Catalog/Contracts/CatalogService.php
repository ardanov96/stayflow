<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Contracts\DTOs\PropertyDTO;
use App\Modules\Catalog\Contracts\DTOs\RoomTypeDTO;
use App\Modules\Catalog\Contracts\DTOs\RoomUnitDTO;
use App\Modules\Catalog\Contracts\DTOs\TaxRuleDTO;

/**
 * Public contract interface for the Catalog module.
 *
 * Sibling modules (Inventory, Reservations, Folio, Distribution) interact
 * with Catalog strictly through this contract.
 */
interface CatalogService
{
    public function getProperty(int $propertyId): ?PropertyDTO;

    public function getRoomType(int $roomTypeId): ?RoomTypeDTO;

    /**
     * @return list<RoomTypeDTO>
     */
    public function getRoomTypesForProperty(int $propertyId): array;

    /**
     * @return list<TaxRuleDTO>
     */
    public function getTaxRulesForProperty(int $propertyId): array;

    public function getActiveRoomUnitsCount(int $roomTypeId): int;

    /**
     * The active room units of a room type.
     *
     * The PMS needs the physical rooms to assign them to a reservation
     * (task P2-05).
     *
     * @return list<RoomUnitDTO>
     */
    public function getRoomUnitsForType(int $roomTypeId): array;
}
