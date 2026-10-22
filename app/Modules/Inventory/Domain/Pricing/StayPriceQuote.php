<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Pricing;

use App\Support\Money\Money;

final readonly class StayPriceQuote
{
    public Money $subtotal;

    /**
     * @param  array<int, NightlyPriceBreakdown>  $nightlyBreakdowns
     * @param  array<string, mixed>|null  $cancellationPolicy
     */
    public function __construct(
        public int $propertyId,
        public int $roomTypeId,
        public int $ratePlanId,
        public string $checkInDate,
        public string $checkOutDate,
        public int $nights,
        public int $adults,
        public int $children,
        public array $nightlyBreakdowns,
        public ?array $cancellationPolicy = null,
    ) {
        $currency = ! empty($this->nightlyBreakdowns)
            ? $this->nightlyBreakdowns[0]->basePrice->currencyCode
            : 'IDR';

        $total = Money::ofMinor(0, $currency);
        foreach ($this->nightlyBreakdowns as $breakdown) {
            $total = $total->plus($breakdown->totalPrice);
        }

        $this->subtotal = $total;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'room_type_id' => $this->roomTypeId,
            'rate_plan_id' => $this->ratePlanId,
            'check_in_date' => $this->checkInDate,
            'check_out_date' => $this->checkOutDate,
            'nights' => $this->nights,
            'adults' => $this->adults,
            'children' => $this->children,
            'nightly_breakdowns' => array_map(fn (NightlyPriceBreakdown $b) => $b->toArray(), $this->nightlyBreakdowns),
            'subtotal_minor' => $this->subtotal->amountMinor,
            'currency' => $this->subtotal->currencyCode,
            'cancellation_policy' => $this->cancellationPolicy,
        ];
    }
}
