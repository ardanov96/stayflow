<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Room\RoomUnitStatus;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;

/**
 * Action to update RoomUnit status (e.g. housekeeping cleaning or maintenance out-of-order).
 */
final readonly class UpdateRoomUnitStatusAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    public function execute(
        RoomUnit $roomUnit,
        RoomUnitStatus $newStatus,
        ?bool $isActive = null,
        ?string $notes = null,
        ?int $actorId = null,
    ): RoomUnit {
        $oldStatus = $roomUnit->status;
        $oldActive = $roomUnit->is_active;

        $updates = ['status' => $newStatus];
        if ($isActive !== null) {
            $updates['is_active'] = $isActive;
        }
        if ($notes !== null) {
            $updates['notes'] = $notes;
        }

        $roomUnit->update($updates);

        if ($isActive !== null && $isActive !== $oldActive) {
            $roomType = $roomUnit->roomType;
            if ($roomType !== null) {
                $activeCount = $roomType->roomUnits()->where('is_active', true)->count();
                $roomType->update(['total_units' => $activeCount]);
            }
        }

        $this->auditor->recordCustom(
            'catalog.room_unit.status_changed',
            new SubjectChange(
                subjectType: RoomUnit::class,
                subjectId: (int) $roomUnit->id,
                oldValues: [
                    'status' => $oldStatus->value,
                    'is_active' => $oldActive,
                ],
                newValues: [
                    'status' => $newStatus->value,
                    'is_active' => $roomUnit->is_active,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $roomUnit->property_id,
                ],
            ),
            new TenantHint((int) $roomUnit->organization_id, (int) $roomUnit->property_id),
        );

        return $roomUnit;
    }
}
