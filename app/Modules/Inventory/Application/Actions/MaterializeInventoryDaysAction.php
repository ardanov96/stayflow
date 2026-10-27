<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Domain\Availability\InventoryWindow;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MaterializeInventoryDaysAction
{
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
        $today = new DateTimeImmutable('today');
        $start = $startDate !== null ? new DateTimeImmutable($startDate) : $today;
        $end = $endDate !== null ? new DateTimeImmutable($endDate) : new DateTimeImmutable(InventoryWindow::defaultMaterializationEndDate());

        if ($start > $end) {
            throw new InvalidArgumentException("Start date {$start->format('Y-m-d')} cannot be after end date {$end->format('Y-m-d')}.");
        }

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

        $period = new DatePeriod(
            $start,
            new DateInterval('P1D'),
            $end->modify('+1 day')
        );

        $totalUpserted = 0;
        $now = date('Y-m-d H:i:s');
        $batch = [];

        foreach ($targetRoomTypes as $roomType) {
            $totalUnits = $roomType->totalUnits;

            foreach ($period as $date) {
                $batch[] = [
                    'organization_id' => $organizationId,
                    'property_id' => $propertyId,
                    'room_type_id' => $roomType->id,
                    'date' => $date->format('Y-m-d'),
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
                        'start_date' => $start->format('Y-m-d'),
                        'end_date' => $end->format('Y-m-d'),
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
