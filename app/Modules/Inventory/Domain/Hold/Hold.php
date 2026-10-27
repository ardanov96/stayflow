<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Hold;

use App\Modules\Inventory\Domain\Exceptions\HoldStateException;
use DateTimeImmutable;

/**
 * Inventory hold aggregate.
 *
 * Owns the state machine: an Active hold leaves that state exactly once,
 * by being committed, released, or expired. Pure domain: no persistence,
 * no framework, fully unit-testable.
 */
final class Hold
{
    public function __construct(
        public readonly int $id,
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly int $roomTypeId,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly int $units,
        public readonly string $idempotencyKey,
        public readonly DateTimeImmutable $expiresAt,
        private HoldStatus $status = HoldStatus::Active,
        private ?int $reservationId = null,
        private ?DateTimeImmutable $committedAt = null,
        private ?DateTimeImmutable $releasedAt = null,
        private ?DateTimeImmutable $expiredAt = null,
        private ?string $releaseReason = null,
    ) {}

    public function status(): HoldStatus
    {
        return $this->status;
    }

    public function reservationId(): ?int
    {
        return $this->reservationId;
    }

    public function committedAt(): ?DateTimeImmutable
    {
        return $this->committedAt;
    }

    public function releasedAt(): ?DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function expiredAt(): ?DateTimeImmutable
    {
        return $this->expiredAt;
    }

    public function releaseReason(): ?string
    {
        return $this->releaseReason;
    }

    public function isActive(): bool
    {
        return $this->status === HoldStatus::Active;
    }

    /**
     * Whether the hold is past its time-to-live and should be released.
     */
    public function isPastExpiry(DateTimeImmutable $now): bool
    {
        return $this->isActive() && $this->expiresAt <= $now;
    }

    /**
     * Convert the hold to sold. The held units become sold units.
     */
    public function commit(?int $reservationId = null, ?DateTimeImmutable $at = null): void
    {
        $this->assertActive('commit');

        $this->status = HoldStatus::Committed;
        $this->reservationId = $reservationId;
        $this->committedAt = $at ?? new DateTimeImmutable;
    }

    /**
     * Give the held units back to availability.
     */
    public function release(?string $reason = null, ?DateTimeImmutable $at = null): void
    {
        $this->assertActive('release');

        $this->status = HoldStatus::Released;
        $this->releaseReason = $reason;
        $this->releasedAt = $at ?? new DateTimeImmutable;
    }

    /**
     * Expire the hold: the units go back to availability, the hold is
     * marked as expired rather than released.
     */
    public function expire(?DateTimeImmutable $at = null): void
    {
        $this->assertActive('expire');

        $this->status = HoldStatus::Expired;
        $this->expiredAt = $at ?? new DateTimeImmutable;
    }

    private function assertActive(string $action): void
    {
        if (! $this->isActive()) {
            throw HoldStateException::invalidTransition($this->status, $action);
        }
    }
}
