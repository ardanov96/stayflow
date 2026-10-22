<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts\DTOs;

final readonly class TaxRuleDTO
{
    public function __construct(
        public int $id,
        public int $propertyId,
        public string $name,
        public string $kind,
        public int $rateBps,
        public bool $isInclusive,
        public string $appliesTo,
        public int $order,
        public bool $isActive,
    ) {}
}
