<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Room\BedConfig;
use App\Modules\Catalog\Domain\Room\RoomUnitStatus;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;

/**
 * Action to create a physical RoomUnit within a RoomType.
 */
final readonly class CreateRoomUnitAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param array{
     *     number: string,
     *     floor?: string|null,
     *     bed_config?: BedConfig|string,
     *     status?: RoomUnitStatus|string,
     *     is_active?: bool,
     *     notes?: string|null
     * } $data
     */
    public function execute(RoomType $roomType, array $data, ?int $actorId = null): RoomUnit
    {
        $bedConfig = $data['bed_config'] ?? BedConfig::King;
        if (is_string($bedConfig)) {
            $bedConfig = BedConfig::from($bedConfig);
        }

        $status = $data['status'] ?? RoomUnitStatus::Clean;
        if (is_string($status)) {
            $status = RoomUnitStatus::from($status);
        }

        $roomUnit = RoomUnit::create([
            'organization_id' => $roomType->organization_id,
            'property_id' => $roomType->property_id,
            'room_type_id' => $roomType->id,
            'number' => $data['number'],
            'floor' => $data['floor'] ?? null,
            'bed_config' => $bedConfig,
            'status' => $status,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);

        // Keep RoomType total_units in sync with active units
        $activeCount = $roomType->roomUnits()->where('is_active', true)->count();
        $roomType->update(['total_units' => $activeCount]);

        $this->auditor->recordCustom(
            'catalog.room_unit.created',
            new SubjectChange(
                subjectType: RoomUnit::class,
                subjectId: (int) $roomUnit->id,
                oldValues: null,
                newValues: [
                    'number' => $roomUnit->number,
                    'bed_config' => $roomUnit->bed_config->value,
                    'status' => $roomUnit->status->value,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $roomType->property_id,
                    'room_type_id' => (int) $roomType->id,
                ],
            ),
            new TenantHint((int) $roomType->organization_id, (int) $roomType->property_id),
        );

        return $roomUnit;
    }
}
