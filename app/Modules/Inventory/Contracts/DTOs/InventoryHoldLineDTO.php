<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

/**
 * One stay date covered by a hold.
 */
final readonly class InventoryHoldLineDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $holdId,
        public int $propertyId,
        public string $date,
        public int $units,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'hold_id' => $this->holdId,
            'property_id' => $this->propertyId,
            'date' => $this->date,
            'units' => $this->units,
        ];
    }
}
