<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Hold;

use App\Support\Date\StayDate;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A validated request to place a temporary hold on inventory.
 *
 * Pure value object: no persistence, no framework. The constructor
 * rejects malformed input so every action can rely on the invariants.
 *
 * The stay dates are `DATE` values: they are the nights of the hotel,
 * not instants (see `App\Support\Date\StayDate`).
 */
final readonly class HoldRequest
{
    /**
     * Default time-to-live in seconds (15 minutes) per
     * docs/ARCHITECTURE.md §5.3.
     */
    public const DEFAULT_TTL_SECONDS = 900;

    public function __construct(
        public int $propertyId,
        public int $roomTypeId,
        public string $checkIn,
        public string $checkOut,
        public int $units,
        public int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
        if ($propertyId < 1) {
            throw new InvalidArgumentException('propertyId must be a positive integer.');
        }

        if ($roomTypeId < 1) {
            throw new InvalidArgumentException('roomTypeId must be a positive integer.');
        }

        if ($units < 1) {
            throw new InvalidArgumentException("Units to hold must be greater than 0, got {$units}.");
        }

        if ($ttlSeconds < 1) {
            throw new InvalidArgumentException("Hold TTL must be greater than 0 seconds, got {$ttlSeconds}.");
        }

        if (! $this->checkOutDate()->isAfter($this->checkInDate())) {
            throw new InvalidArgumentException("Check-out date ({$this->checkOut}) must be after check-in date ({$this->checkIn}).");
        }
    }

    /**
     * The check-in, as a stay date.
     */
    public function checkInDate(): StayDate
    {
        return StayDate::fromString($this->checkIn);
    }

    /**
     * The check-out, as a stay date.
     */
    public function checkOutDate(): StayDate
    {
        return StayDate::fromString($this->checkOut);
    }

    /**
     * Number of nights covered by the stay.
     */
    public function nights(): int
    {
        return $this->checkInDate()->nightsUntil($this->checkOutDate());
    }

    /**
     * The stay dates, i.e. check-in up to (but excluding) check-out.
     *
     * @return list<string>
     */
    public function stayDates(): array
    {
        $dates = [];

        foreach ($this->checkInDate()->range($this->checkOutDate()) as $date) {
            $dates[] = $date->toString();
        }

        return $dates;
    }

    /**
     * The moment the hold expires, counted from the given instant.
     */
    public function expiresAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("+{$this->ttlSeconds} seconds");
    }

    /**
     * Whether this request describes the same stay as the given hold.
     * Used to detect an idempotency key reused with a different payload.
     */
    public function describesSameStayAs(Hold $hold): bool
    {
        return $hold->propertyId === $this->propertyId
            && $hold->roomTypeId === $this->roomTypeId
            && $hold->checkIn === $this->checkIn
            && $hold->checkOut === $this->checkOut
            && $hold->units === $this->units;
    }
}
