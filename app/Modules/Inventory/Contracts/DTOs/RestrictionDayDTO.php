<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

final readonly class RestrictionDayDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public int $ratePlanId,
        public int $roomTypeId,
        public string $date,
        public ?int $minStay,
        public ?int $maxStay,
        public bool $cta,
        public bool $ctd,
        public bool $stopSell,
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
            'rate_plan_id' => $this->ratePlanId,
            'room_type_id' => $this->roomTypeId,
            'date' => $this->date,
            'min_stay' => $this->minStay,
            'max_stay' => $this->maxStay,
            'cta' => $this->cta,
            'ctd' => $this->ctd,
            'stop_sell' => $this->stopSell,
        ];
    }
}
