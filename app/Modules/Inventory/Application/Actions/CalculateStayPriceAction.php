<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Application\Ports\RatePriceRepository;
use App\Modules\Inventory\Contracts\DTOs\NightlyPriceDTO;
use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;
use App\Modules\Inventory\Domain\Exceptions\InvalidDateRangeException;
use App\Modules\Inventory\Domain\Exceptions\RatePriceNotFoundException;
use App\Modules\Inventory\Domain\Pricing\NightlyPriceBreakdown;
use App\Modules\Inventory\Domain\Pricing\StayPriceQuote;
use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\RateDerivation;
use App\Support\Date\StayDate;
use App\Support\Money\Money;
use InvalidArgumentException;

final readonly class CalculateStayPriceAction
{
    public function __construct(
        private RatePlanRepository $ratePlanRepository,
        private RatePriceRepository $ratePriceRepository,
        private CatalogService $catalogService,
    ) {}

    public function execute(
        int $propertyId,
        int $roomTypeId,
        int $ratePlanId,
        string $checkInDate,
        string $checkOutDate,
        int $adults,
        int $children = 0,
    ): StayPriceQuote {
        $checkIn = StayDate::fromString($checkInDate);
        $checkOut = StayDate::fromString($checkOutDate);

        if (! $checkOut->isAfter($checkIn)) {
            throw InvalidDateRangeException::checkOutBeforeCheckIn($checkInDate, $checkOutDate);
        }

        $roomType = $this->catalogService->getRoomType($roomTypeId);
        if ($roomType === null || $roomType->propertyId !== $propertyId) {
            throw new InvalidArgumentException("Room type {$roomTypeId} does not belong to property {$propertyId}.");
        }

        $ratePlan = $this->ratePlanRepository->findById($ratePlanId);
        if ($ratePlan === null || $ratePlan->propertyId !== $propertyId) {
            throw new InvalidArgumentException("Rate plan {$ratePlanId} does not belong to property {$propertyId}.");
        }

        // The list of night dates: [checkIn, checkOut)
        $nightDates = [];
        foreach ($checkIn->range($checkOut) as $date) {
            $nightDates[] = $date->toString();
        }

        // Fetch prices for these nights
        $effectivePrices = $this->resolveNightlyPrices(
            ratePlan: $ratePlan,
            roomTypeId: $roomTypeId,
            startDate: $checkInDate,
            endDate: $checkOutDate,
            nightDates: $nightDates,
        );

        $baseOccupancy = $roomType->baseOccupancy;
        $extraAdults = max(0, $adults - $baseOccupancy);
        $extraChildren = max(0, $children);

        $nightlyBreakdowns = [];
        foreach ($nightDates as $dateString) {
            if (! isset($effectivePrices[$dateString])) {
                throw RatePriceNotFoundException::forDate($ratePlanId, $roomTypeId, $dateString);
            }

            $priceInfo = $effectivePrices[$dateString];
            $currency = $priceInfo->currency;

            $baseMoney = Money::ofMinor($priceInfo->basePriceMinor, $currency);
            $extraAdultMoney = Money::ofMinor($priceInfo->extraAdultPriceMinor * $extraAdults, $currency);
            $extraChildMoney = Money::ofMinor($priceInfo->extraChildPriceMinor * $extraChildren, $currency);

            $nightlyBreakdowns[] = new NightlyPriceBreakdown(
                date: $dateString,
                basePrice: $baseMoney,
                extraAdults: $extraAdults,
                extraAdultPrice: $extraAdultMoney,
                extraChildren: $extraChildren,
                extraChildPrice: $extraChildMoney,
            );
        }

        // Fetch cancellation policy if configured
        $policyData = null;
        if ($ratePlan->cancellationPolicyId !== null) {
            $policyData = $this->ratePlanRepository->getCancellationPolicy($ratePlan->cancellationPolicyId);
        }

        return new StayPriceQuote(
            propertyId: $propertyId,
            roomTypeId: $roomTypeId,
            ratePlanId: $ratePlanId,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            nights: count($nightlyBreakdowns),
            adults: $adults,
            children: $children,
            nightlyBreakdowns: $nightlyBreakdowns,
            cancellationPolicy: $policyData,
        );
    }

    /**
     * @param  list<string>  $nightDates
     * @return array<string, NightlyPriceDTO>
     */
    private function resolveNightlyPrices(
        RatePlanDTO $ratePlan,
        int $roomTypeId,
        string $startDate,
        string $endDate,
        array $nightDates,
    ): array {
        // Direct prices on this rate plan
        $directPrices = $this->ratePriceRepository->getPricesForRange(
            ratePlanId: $ratePlan->id,
            roomTypeId: $roomTypeId,
            startDate: $startDate,
            endDate: $endDate,
        );

        // If not derived, return direct prices
        if ($ratePlan->parentRatePlanId === null || $ratePlan->derivationType === null || $ratePlan->derivationValue === null) {
            return $directPrices;
        }

        // Derived rate plan: fetch parent rate plan
        $parentPlan = $this->ratePlanRepository->findById($ratePlan->parentRatePlanId);
        if ($parentPlan === null) {
            return $directPrices;
        }

        $parentPrices = $this->resolveNightlyPrices(
            ratePlan: $parentPlan,
            roomTypeId: $roomTypeId,
            startDate: $startDate,
            endDate: $endDate,
            nightDates: $nightDates,
        );

        $derivation = new RateDerivation(
            type: DerivationType::from($ratePlan->derivationType),
            value: $ratePlan->derivationValue,
        );

        $resolved = [];
        foreach ($nightDates as $date) {
            // Direct price on derived plan overrides derivation
            if (isset($directPrices[$date])) {
                $resolved[$date] = $directPrices[$date];

                continue;
            }

            if (! isset($parentPrices[$date])) {
                continue;
            }

            $parentPrice = $parentPrices[$date];
            $parentMoney = Money::ofMinor($parentPrice->basePriceMinor, $parentPrice->currency);
            $derivedBaseMoney = $derivation->apply($parentMoney);

            $resolved[$date] = new NightlyPriceDTO(
                date: $date,
                basePriceMinor: $derivedBaseMoney->amountMinor,
                currency: $derivedBaseMoney->currencyCode,
                extraAdults: 0,
                extraAdultPriceMinor: $parentPrice->extraAdultPriceMinor,
                extraChildren: 0,
                extraChildPriceMinor: $parentPrice->extraChildPriceMinor,
                totalPriceMinor: $derivedBaseMoney->amountMinor,
            );
        }

        return $resolved;
    }
}
