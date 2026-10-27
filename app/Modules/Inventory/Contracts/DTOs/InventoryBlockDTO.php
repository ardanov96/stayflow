<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

final readonly class InventoryBlockDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public int $roomTypeId,
        public string $startDate,
        public string $endDate,
        public int $units,
        public string $reason,
        public ?int $createdBy,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'property_id' => $this->propertyId,
            'room_type_id' => $this->roomTypeId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'units' => $this->units,
            'reason' => $this->reason,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }
}
