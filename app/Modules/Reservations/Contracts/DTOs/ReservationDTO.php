<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Contracts\DTOs;

/**
 * A reservation, as the callers see it.
 */
final readonly class ReservationDTO
{
    /**
     * @param  list<array{id: int, room_type_id: int, rate_plan_id: int|null, room_unit_id: int|null, nights: list<array{date: string, price_minor: int}>, total_minor: int}>  $rooms
     * @param  list<int>  $guestIds
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public string $code,
        public string $status,
        public string $source,
        public ?string $channelRef,
        public int $guestId,
        public string $arrival,
        public string $departure,
        public int $adults,
        public int $children,
        public int $totalMinor,
        public string $currency,
        public int $version = 1,
        public ?int $holdId = null,
        public array $rooms = [],
        public array $guestIds = [],
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
            'code' => $this->code,
            'status' => $this->status,
            'source' => $this->source,
            'channel_ref' => $this->channelRef,
            'guest_id' => $this->guestId,
            'arrival' => $this->arrival,
            'departure' => $this->departure,
            'adults' => $this->adults,
            'children' => $this->children,
            'total_minor' => $this->totalMinor,
            'currency' => $this->currency,
            'version' => $this->version,
            'hold_id' => $this->holdId,
            'rooms' => $this->rooms,
            'guest_ids' => $this->guestIds,
        ];
    }
}
