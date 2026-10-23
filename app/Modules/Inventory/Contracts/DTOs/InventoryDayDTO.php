<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

final readonly class InventoryDayDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public int $roomTypeId,
        public string $date,
        public int $totalUnits,
        public int $heldUnits,
        public int $soldUnits,
        public int $blockedUnits,
        public int $availableUnits,
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
            'date' => $this->date,
            'total_units' => $this->totalUnits,
            'held_units' => $this->heldUnits,
            'sold_units' => $this->soldUnits,
            'blocked_units' => $this->blockedUnits,
            'available_units' => $this->availableUnits,
        ];
    }
}
