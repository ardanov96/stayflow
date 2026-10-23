<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

use App\Modules\Inventory\Contracts\DTOs\InventoryDayDTO;

interface InventoryDayRepository
{
    public function findByRoomTypeAndDate(int $roomTypeId, string $date): ?InventoryDayDTO;

    /**
     * @return array<string, InventoryDayDTO> Keyed by date (Y-m-d)
     */
    public function getRange(int $roomTypeId, string $startDate, string $endDate): array;

    /**
     * @param  array<int>  $roomTypeIds
     * @return array<int, array<string, InventoryDayDTO>> Keyed by room_type_id => [date => InventoryDayDTO]
     */
    public function getRangeForRoomTypes(array $roomTypeIds, string $startDate, string $endDate): array;

    /**
     * Increment blocked units for dates in range.
     */
    public function incrementBlockedUnits(int $roomTypeId, string $startDate, string $endDate, int $units): void;

    /**
     * Decrement blocked units for dates in range.
     */
    public function decrementBlockedUnits(int $roomTypeId, string $startDate, string $endDate, int $units): void;

    /**
     * Bulk upsert inventory day rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int Number of rows affected
     */
    public function upsertDays(array $rows): int;
}
