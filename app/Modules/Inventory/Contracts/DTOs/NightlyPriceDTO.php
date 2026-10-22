<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

final readonly class NightlyPriceDTO
{
    public function __construct(
        public string $date,
        public int $basePriceMinor,
        public string $currency,
        public int $extraAdults,
        public int $extraAdultPriceMinor,
        public int $extraChildren,
        public int $extraChildPriceMinor,
        public int $totalPriceMinor,
    ) {}
}
