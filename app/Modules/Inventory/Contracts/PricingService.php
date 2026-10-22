<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;
use App\Modules\Inventory\Domain\Pricing\StayPriceQuote;

interface PricingService
{
    /**
     * Calculate an itemized price quote for a stay across multiple nights.
     *
     * Resolves base prices, handles parent/derived rate plan adjustments,
     * calculates extra adult/child occupancy supplements, and links the cancellation policy.
     */
    public function quoteStay(
        int $propertyId,
        int $roomTypeId,
        int $ratePlanId,
        string $checkInDate,
        string $checkOutDate,
        int $adults,
        int $children = 0,
    ): StayPriceQuote;

    /**
     * Get all active rate plans for a property.
     *
     * @return array<int, RatePlanDTO>
     */
    public function getRatePlansForProperty(int $propertyId): array;

    /**
     * Find a specific rate plan.
     */
    public function findRatePlan(int $ratePlanId): ?RatePlanDTO;
}
