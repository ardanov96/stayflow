<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts\DTOs;

/**
 * A physical room unit.
 */
final readonly class RoomUnitDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public int $roomTypeId,
        public string $number,
        public ?string $floor = null,
        public string $status = 'clean',
        public bool $isActive = true,
    ) {}
}
