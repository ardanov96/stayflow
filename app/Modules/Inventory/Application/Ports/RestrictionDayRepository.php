<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

use App\Modules\Inventory\Contracts\DTOs\RestrictionDayDTO;

interface RestrictionDayRepository
{
    /**
     * @return array<string, RestrictionDayDTO> Keyed by date (Y-m-d)
     */
    public function getRestrictions(int $ratePlanId, int $roomTypeId, string $startDate, string $endDate): array;

    /**
     * @param  array<int>  $roomTypeIds
     * @return array<int, array<string, RestrictionDayDTO>> Keyed by room_type_id => [date => RestrictionDayDTO]
     */
    public function getRestrictionsForRoomTypes(int $ratePlanId, array $roomTypeIds, string $startDate, string $endDate): array;

    /**
     * Bulk save or update restriction records.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, RestrictionDayDTO>
     */
    public function setRestrictionsBatch(array $records): array;
}
