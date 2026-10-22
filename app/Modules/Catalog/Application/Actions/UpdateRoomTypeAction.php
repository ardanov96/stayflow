<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Room\RoomTypeStatus;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;

/**
 * Action to update an existing RoomType.
 */
final readonly class UpdateRoomTypeAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RoomType $roomType, array $data, ?int $actorId = null): RoomType
    {
        $oldValues = [
            'name' => $roomType->name,
            'status' => $roomType->status->value,
            'total_units' => $roomType->total_units,
        ];

        if (isset($data['status']) && is_string($data['status'])) {
            $data['status'] = RoomTypeStatus::from($data['status']);
        }

        $amenityIds = $data['amenity_ids'] ?? null;
        unset($data['amenity_ids']);

        $roomType->update($data);

        if (is_array($amenityIds)) {
            $roomType->amenities()->sync($amenityIds);
        }

        $this->auditor->recordCustom(
            'catalog.room_type.updated',
            new SubjectChange(
                subjectType: RoomType::class,
                subjectId: (int) $roomType->id,
                oldValues: $oldValues,
                newValues: [
                    'name' => $roomType->name,
                    'status' => $roomType->status->value,
                    'total_units' => $roomType->total_units,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $roomType->property_id,
                ],
            ),
            new TenantHint((int) $roomType->organization_id, (int) $roomType->property_id),
        );

        return $roomType;
    }
}
