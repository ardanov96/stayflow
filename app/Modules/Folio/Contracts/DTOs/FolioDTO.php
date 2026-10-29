<?php

declare(strict_types=1);

namespace App\Modules\Folio\Contracts\DTOs;

/**
 * The folio of a reservation, as the callers see it.
 */
final readonly class FolioDTO
{
    /**
     * @param  list<array{id: int, type: string, amount_minor: int, currency: string, business_date: string, reversal_of_id: int|null, source: string|null}>  $entries
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public int $reservationId,
        public string $status,
        public string $currency,
        public int $balanceMinor,
        public array $entries = [],
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
            'reservation_id' => $this->reservationId,
            'status' => $this->status,
            'currency' => $this->currency,
            'balance_minor' => $this->balanceMinor,
            'entries' => $this->entries,
        ];
    }
}
