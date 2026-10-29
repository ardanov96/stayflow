<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Contracts\DTOs;

/**
 * The result of a modification of a reservation.
 *
 * It carries the **diff** the staff sees: what changed, which nights were
 * added and which were given back, and what the stay costs now against
 * what it cost before.
 */
final readonly class ReservationModificationDTO
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  list<string>  $nightsAdded
     * @param  list<string>  $nightsRemoved
     */
    public function __construct(
        public ReservationDTO $reservation,
        public array $changes = [],
        public array $nightsAdded = [],
        public array $nightsRemoved = [],
        public int $previousTotalMinor = 0,
        public int $totalMinor = 0,
        public string $currency = 'IDR',
    ) {}

    /**
     * The difference of the price, in minor units.
     */
    public function priceDifferenceMinor(): int
    {
        return $this->totalMinor - $this->previousTotalMinor;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reservation' => $this->reservation->toArray(),
            'changes' => $this->changes,
            'nights_added' => $this->nightsAdded,
            'nights_removed' => $this->nightsRemoved,
            'previous_total_minor' => $this->previousTotalMinor,
            'total_minor' => $this->totalMinor,
            'price_difference_minor' => $this->priceDifferenceMinor(),
            'currency' => $this->currency,
        ];
    }
}
