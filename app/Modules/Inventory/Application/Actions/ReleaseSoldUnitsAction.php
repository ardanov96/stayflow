<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Domain\Events\InventoryChanged;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use InvalidArgumentException;

/**
 * Gives the units of nights that are already sold back to availability.
 *
 * A reservation that changed its nights no longer covers the nights it
 * dropped: they are sold, and they have to go back to the pool. The
 * counters never go below zero.
 */
final readonly class ReleaseSoldUnitsAction
{
    public function __construct(
        private InventoryDayRepository $inventoryDayRepository,
        private Auditor $auditor,
    ) {}

    /**
     * @param  list<string>  $dates
     */
    public function execute(
        int $organizationId,
        int $propertyId,
        int $roomTypeId,
        array $dates,
        int $units,
        ?int $actorId = null,
    ): void {
        if ($units < 1) {
            throw new InvalidArgumentException("The number of units to release must be positive, got {$units}.");
        }

        if ($dates === []) {
            return;
        }

        $this->inventoryDayRepository->decrementSoldUnits($roomTypeId, $dates, $units);

        $this->auditor->recordCustom(
            'inventory.sold.released',
            new SubjectChange(
                subjectId: $roomTypeId,
                subjectType: 'RoomType',
                oldValues: null,
                newValues: [
                    'dates' => $dates,
                    'units' => $units,
                ],
                metadata: $actorId !== null ? ['actor_id' => $actorId] : [],
            ),
            new TenantHint(
                organizationId: $organizationId,
                propertyId: $propertyId,
            ),
        );

        event(new InventoryChanged(
            organizationId: $organizationId,
            propertyId: $propertyId,
            roomTypeIds: [$roomTypeId],
            startDate: $dates[0],
            endDate: $dates[count($dates) - 1],
            reason: 'sold.released',
        ));
    }
}
