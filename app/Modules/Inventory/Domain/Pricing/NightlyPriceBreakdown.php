<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Pricing;

use App\Support\Money\Money;

final readonly class NightlyPriceBreakdown
{
    public Money $totalPrice;

    public function __construct(
        public string $date,
        public Money $basePrice,
        public int $extraAdults,
        public Money $extraAdultPrice,
        public int $extraChildren,
        public Money $extraChildPrice,
    ) {
        $this->totalPrice = $this->basePrice
            ->plus($this->extraAdultPrice)
            ->plus($this->extraChildPrice);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'base_price_minor' => $this->basePrice->amountMinor,
            'currency' => $this->basePrice->currencyCode,
            'extra_adults' => $this->extraAdults,
            'extra_adult_price_minor' => $this->extraAdultPrice->amountMinor,
            'extra_children' => $this->extraChildren,
            'extra_child_price_minor' => $this->extraChildPrice->amountMinor,
            'total_price_minor' => $this->totalPrice->amountMinor,
        ];
    }
}
