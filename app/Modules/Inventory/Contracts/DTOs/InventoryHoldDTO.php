<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

use App\Modules\Inventory\Domain\Hold\HoldStatus;

/**
 * A temporary hold on inventory units.
 *
 * The status mirrors
 * {@see HoldStatus}: active,
 * committed, released, or expired.
 */
final readonly class InventoryHoldDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public int $roomTypeId,
        public string $checkIn,
        public string $checkOut,
        public int $units,
        public string $status,
        public string $idempotencyKey,
        public string $expiresAt,
        public ?int $reservationId,
        public ?string $committedAt,
        public ?string $releasedAt,
        public ?string $expiredAt,
        public ?string $releaseReason,
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
            'room_type_id' => $this->roomTypeId,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'units' => $this->units,
            'status' => $this->status,
            'idempotency_key' => $this->idempotencyKey,
            'expires_at' => $this->expiresAt,
            'reservation_id' => $this->reservationId,
            'committed_at' => $this->committedAt,
            'released_at' => $this->releasedAt,
            'expired_at' => $this->expiredAt,
            'release_reason' => $this->releaseReason,
        ];
    }
}
