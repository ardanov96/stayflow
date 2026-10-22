<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

use App\Modules\Inventory\Contracts\DTOs\NightlyPriceDTO;

interface RatePriceRepository
{
    /**
     * Retrieve prices keyed by 'YYYY-MM-DD' for a date range (inclusive start, exclusive end for stay nights).
     *
     * @return array<string, NightlyPriceDTO>
     */
    public function getPricesForRange(
        int $ratePlanId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
    ): array;

    /**
     * Set prices for specific dates or date ranges.
     *
     * @param  array<int, array{date: string, base_price_minor: int, currency: string, extra_adult_price_minor?: int, extra_child_price_minor?: int}>  $prices
     */
    public function setPrices(
        int $propertyId,
        int $ratePlanId,
        int $roomTypeId,
        array $prices,
    ): void;

    /**
     * Delete prices for a date range.
     */
    public function deletePrices(
        int $ratePlanId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
    ): void;
}
