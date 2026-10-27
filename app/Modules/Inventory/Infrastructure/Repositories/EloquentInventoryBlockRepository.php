<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Repositories;

use App\Modules\Inventory\Application\Ports\InventoryBlockRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryBlockDTO;
use App\Modules\Inventory\Infrastructure\Models\InventoryBlock;

final class EloquentInventoryBlockRepository implements InventoryBlockRepository
{
    public function create(array $data): InventoryBlockDTO
    {
        /** @var InventoryBlock $model */
        $model = InventoryBlock::query()->create($data);

        return $this->toDTO($model);
    }

    public function find(int $blockId): ?InventoryBlockDTO
    {
        /** @var InventoryBlock|null $model */
        $model = InventoryBlock::query()->find($blockId);

        return $model !== null ? $this->toDTO($model) : null;
    }

    public function delete(int $blockId): bool
    {
        return (bool) InventoryBlock::query()->where('id', $blockId)->delete();
    }

    private function toDTO(InventoryBlock $model): InventoryBlockDTO
    {
        $rawStartDate = $model->getRawOriginal('start_date');
        $startDate = is_string($rawStartDate)
            ? substr($rawStartDate, 0, 10)
            : substr((string) $model->getAttribute('start_date'), 0, 10);

        $rawEndDate = $model->getRawOriginal('end_date');
        $endDate = is_string($rawEndDate)
            ? substr($rawEndDate, 0, 10)
            : substr((string) $model->getAttribute('end_date'), 0, 10);

        return new InventoryBlockDTO(
            id: $model->id,
            organizationId: $model->organization_id,
            propertyId: $model->property_id,
            roomTypeId: $model->room_type_id,
            startDate: $startDate,
            endDate: $endDate,
            units: $model->units,
            reason: $model->reason,
            createdBy: $model->created_by,
            createdAt: $model->created_at?->toISOString() ?? now()->toISOString(),
        );
    }
}
