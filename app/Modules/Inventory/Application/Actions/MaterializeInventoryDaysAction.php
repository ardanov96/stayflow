<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Application\Support\ResolvesPropertyTimezone;
use App\Modules\Inventory\Domain\Availability\InventoryWindow;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Support\Date\PropertyClock;
use App\Support\Date\StayDate;
use App\Support\Date\UtcTimestamp;
use InvalidArgumentException;

final readonly class MaterializeInventoryDaysAction
{
    use ResolvesPropertyTimezone;

    public function __construct(
        private CatalogService $catalogService,
        private InventoryDayRepository $inventoryDayRepository,
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<int>|null  $roomTypeIds
     */
    public function execute(
        int $organizationId,
        int $propertyId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $roomTypeIds = null,
        ?int $actorId = null,
    ): int {
        $allRoomTypes = $this->catalogService->getRoomTypesForProperty($propertyId);
        if (empty($allRoomTypes)) {
            return 0;
        }

        $targetRoomTypes = [];
        foreach ($allRoomTypes as $rt) {
            if ($roomTypeIds === null || in_array($rt->id, $roomTypeIds, true)) {
                $targetRoomTypes[] = $rt;
            }
        }

        if (empty($targetRoomTypes)) {
            return 0;
        }

        // The window is counted in the days of the property: the hotel in
        // Bali is already on the 28th when it is still the 27th in UTC.
        $timezone = $this->propertyTimezone($this->catalogService, $propertyId);

        $start = $startDate !== null
            ? StayDate::fromString($startDate)
            : (new PropertyClock)->today($timezone);

        $end = $endDate !== null
            ? StayDate::fromString($endDate)
            : StayDate::fromString(InventoryWindow::defaultMaterializationEndDate($timezone));

        if ($start->isAfter($end)) {
            throw new InvalidArgumentException("Start date {$start->toString()} cannot be after end date {$end->toString()}.");
        }

        $totalUpserted = 0;
        $now = UtcTimestamp::now();
        $batch = [];

        foreach ($targetRoomTypes as $roomType) {
            $totalUnits = $roomType->totalUnits;

            foreach ($start->range($end->plusDays(1)) as $date) {
                $batch[] = [
                    'organization_id' => $organizationId,
                    'property_id' => $propertyId,
                    'room_type_id' => $roomType->id,
                    'date' => $date->toString(),
                    'total_units' => $totalUnits,
                    'held_units' => 0,
                    'sold_units' => 0,
                    'blocked_units' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 500) {
                    $totalUpserted += $this->inventoryDayRepository->upsertDays($batch);
                    $batch = [];
                }
            }
        }

        if (! empty($batch)) {
            $totalUpserted += $this->inventoryDayRepository->upsertDays($batch);
        }

        if ($actorId !== null) {
            $this->auditor->recordCustom(
                'inventory.materialized',
                new SubjectChange(
                    subjectId: $propertyId,
                    subjectType: 'Property',
                    oldValues: null,
                    newValues: [
                        'start_date' => $start->toString(),
                        'end_date' => $end->toString(),
                        'room_types_count' => count($targetRoomTypes),
                        'total_upserted' => $totalUpserted,
                    ],
                    metadata: [
                        'actor_id' => $actorId,
                    ],
                ),
                new TenantHint(
                    organizationId: $organizationId,
                    propertyId: $propertyId,
                ),
            );
        }

        return $totalUpserted;
    }
}
