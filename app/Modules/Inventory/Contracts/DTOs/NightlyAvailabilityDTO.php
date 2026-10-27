<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

final readonly class NightlyAvailabilityDTO
{
    public function __construct(
        public string $date,
        public int $availableUnits,
        public int $totalUnits,
        public int $heldUnits,
        public int $soldUnits,
        public int $blockedUnits,
        public bool $cta = false,
        public bool $ctd = false,
        public bool $stopSell = false,
        public ?int $minStay = null,
        public ?int $maxStay = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'available_units' => $this->availableUnits,
            'total_units' => $this->totalUnits,
            'held_units' => $this->heldUnits,
            'sold_units' => $this->soldUnits,
            'blocked_units' => $this->blockedUnits,
            'cta' => $this->cta,
            'ctd' => $this->ctd,
            'stop_sell' => $this->stopSell,
            'min_stay' => $this->minStay,
            'max_stay' => $this->maxStay,
        ];
    }
}
