<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts\DTOs;

final readonly class PropertyDTO
{
    public function __construct(
        public int $id,
        public int $organizationId,
        public string $name,
        public string $slug,
        public string $status,
        public string $timezone,
        public string $currency,
        public string $checkInTime,
        public string $checkOutTime,
    ) {}
}
