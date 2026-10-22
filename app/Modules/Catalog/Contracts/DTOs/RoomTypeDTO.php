<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts\DTOs;

final readonly class RoomTypeDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public string $code,
        public string $name,
        public int $baseOccupancy,
        public int $maxAdults,
        public int $maxChildren,
        public int $totalUnits,
        public string $status,
    ) {}
}
