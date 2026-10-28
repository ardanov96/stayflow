<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain;

/**
 * One room of a reservation.
 *
 * A room is a room **type** with a rate plan and the nights it covers
 * with the price of each night. The physical room (the unit) is assigned
 * later, and it is not always assigned: a villa that is sold as a whole
 * has no unit.
 */
final readonly class ReservationRoom
{
    /**
     * @param  list<array{date: string, price_minor: int}>  $nights
     */
    public function __construct(
        public int $roomTypeId,
        public ?int $ratePlanId = null,
        public ?int $roomUnitId = null,
        public array $nights = [],
    ) {}

    /**
     * Whether a physical room is assigned to it.
     */
    public function isAssigned(): bool
    {
        return $this->roomUnitId !== null;
    }

    /**
     * The number of nights it covers.
     */
    public function nightsCount(): int
    {
        return count($this->nights);
    }

    /**
     * The total of the nights, in minor units.
     */
    public function totalMinor(): int
    {
        $total = 0;

        foreach ($this->nights as $night) {
            $total += (int) $night['price_minor'];
        }

        return $total;
    }

    /**
     * The same room, with a physical room assigned to it.
     */
    public function withUnit(int $roomUnitId): self
    {
        return new self(
            roomTypeId: $this->roomTypeId,
            ratePlanId: $this->ratePlanId,
            roomUnitId: $roomUnitId,
            nights: $this->nights,
        );
    }
}
