<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Room\RoomTypeStatus;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;

/**
 * Action to create a new RoomType within a Property.
 */
final readonly class CreateRoomTypeAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     description?: array<string, string>|null,
     *     base_occupancy?: int,
     *     max_adults?: int,
     *     max_children?: int,
     *     total_units?: int,
     *     status?: RoomTypeStatus|string,
     *     amenity_ids?: list<int>
     * } $data
     */
    public function execute(Property $property, array $data, ?int $actorId = null): RoomType
    {
        $status = $data['status'] ?? RoomTypeStatus::Active;
        if (is_string($status)) {
            $status = RoomTypeStatus::from($status);
        }

        $roomType = RoomType::create([
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_occupancy' => $data['base_occupancy'] ?? 2,
            'max_adults' => $data['max_adults'] ?? 2,
            'max_children' => $data['max_children'] ?? 0,
            'total_units' => $data['total_units'] ?? 0,
            'status' => $status,
        ]);

        if (! empty($data['amenity_ids'])) {
            $roomType->amenities()->sync($data['amenity_ids']);
        }

        $this->auditor->recordCustom(
            'catalog.room_type.created',
            new SubjectChange(
                subjectType: RoomType::class,
                subjectId: (int) $roomType->id,
                oldValues: null,
                newValues: [
                    'code' => $roomType->code,
                    'name' => $roomType->name,
                    'total_units' => $roomType->total_units,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $property->id,
                ],
            ),
            new TenantHint((int) $property->organization_id, (int) $property->id),
        );

        return $roomType;
    }
}
