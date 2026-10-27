<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\InventoryBlockRepository;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Domain\Exceptions\InventoryBlockNotFoundException;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use Illuminate\Support\Facades\DB;

final readonly class UnblockInventoryAction
{
    public function __construct(
        private InventoryDayRepository $inventoryDayRepository,
        private InventoryBlockRepository $inventoryBlockRepository,
        private Auditor $auditor,
    ) {}

    public function execute(int $blockId, ?int $actorId = null): void
    {
        $block = $this->inventoryBlockRepository->find($blockId);
        if ($block === null) {
            throw InventoryBlockNotFoundException::forId($blockId);
        }

        DB::transaction(function () use ($block, $actorId): void {
            $this->inventoryDayRepository->decrementBlockedUnits(
                roomTypeId: $block->roomTypeId,
                startDate: $block->startDate,
                endDate: $block->endDate,
                units: $block->units,
            );

            $this->inventoryBlockRepository->delete($block->id);

            if ($actorId !== null) {
                $this->auditor->recordCustom(
                    'inventory.unblocked',
                    new SubjectChange(
                        subjectId: $block->id,
                        subjectType: 'InventoryBlock',
                        oldValues: [
                            'room_type_id' => $block->roomTypeId,
                            'start_date' => $block->startDate,
                            'end_date' => $block->endDate,
                            'units' => $block->units,
                            'reason' => $block->reason,
                        ],
                        newValues: null,
                        metadata: [
                            'actor_id' => $actorId,
                        ],
                    ),
                    new TenantHint(
                        organizationId: $block->organizationId,
                        propertyId: $block->propertyId,
                    ),
                );
            }
        });
    }
}
