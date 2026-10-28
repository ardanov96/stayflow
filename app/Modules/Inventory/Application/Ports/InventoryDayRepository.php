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
     * Lock the inventory day rows for the given room types and dates
     * (SELECT ... FOR UPDATE).
     *
     * Rows are always returned in (room_type_id, date) order so that
     * concurrent writers acquire the locks in the same order and cannot
     * deadlock each other.
     *
     * @param  array<int>  $roomTypeIds
     * @return array<int, array<string, InventoryDayDTO>> Keyed by room_type_id => [date => InventoryDayDTO]
     */
    public function lockRangeForRoomTypes(array $roomTypeIds, string $startDate, string $endDate): array;

    /**
     * Increment blocked units for dates in range.
     */
    public function incrementBlockedUnits(int $roomTypeId, string $startDate, string $endDate, int $units): void;

    /**
     * Decrement blocked units for dates in range.
     */
    public function decrementBlockedUnits(int $roomTypeId, string $startDate, string $endDate, int $units): void;

    /**
     * Increment held units for the given dates.
     *
     * @param  list<string>  $dates
     */
    public function incrementHeldUnits(int $roomTypeId, array $dates, int $units): void;

    /**
     * Decrement held units for the given dates. The counter never goes
     * below zero.
     *
     * @param  list<string>  $dates
     */
    public function decrementHeldUnits(int $roomTypeId, array $dates, int $units): void;

    /**
     * Give back the units of nights that are already sold.
     *
     * A reservation that changed its nights gives the nights it no longer
     * covers back to availability. The counter never goes below zero.
     *
     * @param  list<string>  $dates
     */
    public function decrementSoldUnits(int $roomTypeId, array $dates, int $units): void;

    /**
     * Convert held units into sold units for the given dates: the held
     * counter goes down, the sold counter goes up, in one statement.
     *
     * @param  list<string>  $dates
     */
    public function convertHeldUnitsToSold(int $roomTypeId, array $dates, int $units): void;

    /**
     * Bulk upsert inventory day rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int Number of rows affected
     */
    public function upsertDays(array $rows): int;
}
