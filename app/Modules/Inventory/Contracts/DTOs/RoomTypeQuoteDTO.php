<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

use App\Modules\Inventory\Domain\Pricing\StayPriceQuote;

final readonly class RoomTypeQuoteDTO
{
    /**
     * @param  array<int, string>  $restrictionViolations
     * @param  array<int, NightlyAvailabilityDTO>  $nightlyAvailability
     */
    public function __construct(
        public int $roomTypeId,
        public string $roomTypeCode,
        public string $roomTypeName,
        public int $availableUnits,
        public bool $isAvailable,
        public array $restrictionViolations = [],
        public ?StayPriceQuote $priceQuote = null,
        public array $nightlyAvailability = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'room_type_id' => $this->roomTypeId,
            'room_type_code' => $this->roomTypeCode,
            'room_type_name' => $this->roomTypeName,
            'available_units' => $this->availableUnits,
            'is_available' => $this->isAvailable,
            'restriction_violations' => $this->restrictionViolations,
            'price_quote' => $this->priceQuote?->toArray(),
            'nightly_availability' => array_map(fn (NightlyAvailabilityDTO $n) => $n->toArray(), $this->nightlyAvailability),
        ];
    }
}
