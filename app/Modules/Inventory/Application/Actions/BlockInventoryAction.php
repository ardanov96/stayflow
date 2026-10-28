<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\InventoryBlockRepository;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryBlockDTO;
use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Inventory\Domain\Exceptions\OutsideMaterializedWindowException;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Support\Date\StayDate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class BlockInventoryAction
{
    public function __construct(
        private InventoryDayRepository $inventoryDayRepository,
        private InventoryBlockRepository $inventoryBlockRepository,
        private Auditor $auditor,
    ) {}

    public function execute(
        int $organizationId,
        int $propertyId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
        int $units,
        string $reason,
        ?int $actorId = null,
    ): InventoryBlockDTO {
        if ($units <= 0) {
            throw new InvalidArgumentException("Units to block must be greater than 0, got {$units}.");
        }

        $start = StayDate::fromString($startDate);
        $end = StayDate::fromString($endDate);

        if ($start->isAfter($end)) {
            throw new InvalidArgumentException("Start date {$startDate} cannot be after end date {$endDate}.");
        }

        return DB::transaction(function () use (
            $organizationId,
            $propertyId,
            $roomTypeId,
            $startDate,
            $endDate,
            $units,
            $reason,
            $actorId,
            $start,
            $end,
        ): InventoryBlockDTO {
            $days = $this->inventoryDayRepository->getRange($roomTypeId, $startDate, $endDate);

            foreach ($start->range($end->plusDays(1)) as $date) {
                $dateKey = $date->toString();
                if (! isset($days[$dateKey])) {
                    throw OutsideMaterializedWindowException::unmaterialized($roomTypeId, $dateKey);
                }

                $day = $days[$dateKey];
                if ($day->availableUnits < $units) {
                    throw InsufficientInventoryException::forDate(
                        roomTypeId: $roomTypeId,
                        date: $dateKey,
                        requested: $units,
                        available: $day->availableUnits,
                    );
                }
            }

            $this->inventoryDayRepository->incrementBlockedUnits($roomTypeId, $startDate, $endDate, $units);

            $block = $this->inventoryBlockRepository->create([
                'organization_id' => $organizationId,
                'property_id' => $propertyId,
                'room_type_id' => $roomTypeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'units' => $units,
                'reason' => $reason,
                'created_by' => $actorId,
            ]);

            if ($actorId !== null) {
                $this->auditor->recordCustom(
                    'inventory.blocked',
                    new SubjectChange(
                        subjectId: $block->id,
                        subjectType: 'InventoryBlock',
                        oldValues: null,
                        newValues: [
                            'room_type_id' => $roomTypeId,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'units' => $units,
                            'reason' => $reason,
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

            return $block;
        });
    }
}
