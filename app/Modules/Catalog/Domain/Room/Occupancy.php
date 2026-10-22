<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Room;

use InvalidArgumentException;

/**
 * Value Object representing the guest occupancy capacity of a RoomType.
 */
final readonly class Occupancy
{
    public function __construct(
        public int $baseOccupancy,
        public int $maxAdults,
        public int $maxChildren = 0,
    ) {
        if ($this->baseOccupancy < 1) {
            throw new InvalidArgumentException("Base occupancy must be at least 1, got {$this->baseOccupancy}.");
        }

        if ($this->maxAdults < $this->baseOccupancy) {
            throw new InvalidArgumentException(
                "Max adults ({$this->maxAdults}) cannot be less than base occupancy ({$this->baseOccupancy})."
            );
        }

        if ($this->maxChildren < 0) {
            throw new InvalidArgumentException("Max children cannot be negative, got {$this->maxChildren}.");
        }
    }

    public function maxTotal(): int
    {
        return $this->maxAdults + $this->maxChildren;
    }

    public function canAccommodate(int $adults, int $children): bool
    {
        return $adults >= 1 && $adults <= $this->maxAdults && $children >= 0 && $children <= $this->maxChildren;
    }
}
