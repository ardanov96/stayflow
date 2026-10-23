<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

final readonly class InventoryQuoteDTO
{
    /**
     * @param  array<int, RoomTypeQuoteDTO>  $roomTypes
     */
    public function __construct(
        public int $propertyId,
        public string $checkInDate,
        public string $checkOutDate,
        public int $nights,
        public int $adults,
        public int $children,
        public array $roomTypes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'check_in_date' => $this->checkInDate,
            'check_out_date' => $this->checkOutDate,
            'nights' => $this->nights,
            'adults' => $this->adults,
            'children' => $this->children,
            'room_types' => array_map(fn (RoomTypeQuoteDTO $r) => $r->toArray(), $this->roomTypes),
        ];
    }
}
