<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Hold;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A validated request to place a temporary hold on inventory.
 *
 * Pure value object: no persistence, no framework. The constructor
 * rejects malformed input so every action can rely on the invariants.
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

        $checkInDate = new DateTimeImmutable($checkIn);
        $checkOutDate = new DateTimeImmutable($checkOut);

        if ($checkInDate >= $checkOutDate) {
            throw new InvalidArgumentException("Check-out date ({$checkOut}) must be after check-in date ({$checkIn}).");
        }
    }

    /**
     * Number of nights covered by the stay.
     */
    public function nights(): int
    {
        $checkIn = new DateTimeImmutable($this->checkIn);
        $checkOut = new DateTimeImmutable($this->checkOut);

        return (int) $checkIn->diff($checkOut)->days;
    }

    /**
     * The stay dates, i.e. check-in up to (but excluding) check-out.
     *
     * @return list<string>
     */
    public function stayDates(): array
    {
        $checkIn = new DateTimeImmutable($this->checkIn);
        $checkOut = new DateTimeImmutable($this->checkOut);

        $dates = [];
        $period = new DatePeriod($checkIn, new DateInterval('P1D'), $checkOut);
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
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
