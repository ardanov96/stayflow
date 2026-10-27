<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Application\Ports\RestrictionDayRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryQuoteDTO;
use App\Modules\Inventory\Contracts\DTOs\NightlyAvailabilityDTO;
use App\Modules\Inventory\Contracts\DTOs\RoomTypeQuoteDTO;
use App\Modules\Inventory\Contracts\PricingService;
use App\Modules\Inventory\Domain\Availability\InventoryWindow;
use App\Modules\Inventory\Domain\Exceptions\InvalidDateRangeException;
use App\Modules\Inventory\Domain\Exceptions\OutsideMaterializedWindowException;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Throwable;

final readonly class QuoteInventoryAction
{
    public function __construct(
        private CatalogService $catalogService,
        private InventoryDayRepository $inventoryDayRepository,
        private RestrictionDayRepository $restrictionDayRepository,
        private PricingService $pricingService,
    ) {}

    /**
     * @param  array<int>  $roomTypeIds
     */
    public function execute(
        int $propertyId,
        array $roomTypeIds,
        string $checkInDate,
        string $checkOutDate,
        int $adults,
        int $children = 0,
        ?int $ratePlanId = null,
    ): InventoryQuoteDTO {
        $checkIn = new DateTimeImmutable($checkInDate);
        $checkOut = new DateTimeImmutable($checkOutDate);

        if ($checkIn >= $checkOut) {
            throw new InvalidDateRangeException("Check-out date ({$checkOutDate}) must be after check-in date ({$checkInDate}).");
        }

        if (! InventoryWindow::isWithinWindow($checkInDate) || ! InventoryWindow::isWithinWindow($checkOutDate)) {
            throw OutsideMaterializedWindowException::forDate($checkOutDate);
        }

        $nights = (int) $checkIn->diff($checkOut)->days;

        // Fetch room types
        $allRoomTypes = $this->catalogService->getRoomTypesForProperty($propertyId);
        $targetRoomTypes = [];
        foreach ($allRoomTypes as $rt) {
            if (empty($roomTypeIds) || in_array($rt->id, $roomTypeIds, true)) {
                $targetRoomTypes[] = $rt;
            }
        }

        $targetIds = array_map(fn ($rt) => $rt->id, $targetRoomTypes);

        // Stay dates are [checkIn, checkOut - 1 day]
        $lastNight = $checkOut->modify('-1 day');
        $stayPeriod = new DatePeriod($checkIn, new DateInterval('P1D'), $checkOut);

        // Fetch inventory days for all target room types
        $daysByRoomType = $this->inventoryDayRepository->getRangeForRoomTypes(
            $targetIds,
            $checkInDate,
            $lastNight->format('Y-m-d')
        );

        // Fetch restrictions if rate plan is specified (including check-out date for CTD)
        $restrictionsByRoomType = [];
        if ($ratePlanId !== null) {
            $restrictionsByRoomType = $this->restrictionDayRepository->getRestrictionsForRoomTypes(
                $ratePlanId,
                $targetIds,
                $checkInDate,
                $checkOutDate
            );
        }

        $roomTypeQuotes = [];

        foreach ($targetRoomTypes as $roomType) {
            $violations = [];
            $nightlyDTOs = [];
            $minAvailable = PHP_INT_MAX;

            $roomDays = $daysByRoomType[$roomType->id] ?? [];
            $roomRestrictions = $restrictionsByRoomType[$roomType->id] ?? [];

            // Check CTA on arrival date
            if (isset($roomRestrictions[$checkInDate]) && $roomRestrictions[$checkInDate]->cta) {
                $violations[] = "Closed to Arrival (CTA) on {$checkInDate}.";
            }

            // Check CTD on departure date
            if (isset($roomRestrictions[$checkOutDate]) && $roomRestrictions[$checkOutDate]->ctd) {
                $violations[] = "Closed to Departure (CTD) on {$checkOutDate}.";
            }

            foreach ($stayPeriod as $night) {
                $dateKey = $night->format('Y-m-d');
                $inventoryDay = $roomDays[$dateKey] ?? null;
                $restriction = $roomRestrictions[$dateKey] ?? null;

                if ($inventoryDay === null) {
                    $violations[] = "Inventory not materialized for date {$dateKey}.";
                    $minAvailable = 0;
                    $nightlyDTOs[] = new NightlyAvailabilityDTO(
                        date: $dateKey,
                        availableUnits: 0,
                        totalUnits: 0,
                        heldUnits: 0,
                        soldUnits: 0,
                        blockedUnits: 0,
                    );

                    continue;
                }

                $available = $inventoryDay->availableUnits;
                if ($available < $minAvailable) {
                    $minAvailable = $available;
                }

                $cta = $restriction !== null && $restriction->cta;
                $ctd = $restriction !== null && $restriction->ctd;
                $stopSell = $restriction !== null && $restriction->stopSell;
                $minStay = $restriction?->minStay;
                $maxStay = $restriction?->maxStay;

                if ($stopSell) {
                    $violations[] = "Stop sell active on {$dateKey}.";
                }

                if ($minStay !== null && $nights < $minStay) {
                    $violations[] = "Minimum stay of {$minStay} nights required on {$dateKey} (requested {$nights}).";
                }

                if ($maxStay !== null && $nights > $maxStay) {
                    $violations[] = "Maximum stay of {$maxStay} nights exceeded on {$dateKey} (requested {$nights}).";
                }

                $nightlyDTOs[] = new NightlyAvailabilityDTO(
                    date: $dateKey,
                    availableUnits: $available,
                    totalUnits: $inventoryDay->totalUnits,
                    heldUnits: $inventoryDay->heldUnits,
                    soldUnits: $inventoryDay->soldUnits,
                    blockedUnits: $inventoryDay->blockedUnits,
                    cta: $cta,
                    ctd: $ctd,
                    stopSell: $stopSell,
                    minStay: $minStay,
                    maxStay: $maxStay,
                );
            }

            if ($minAvailable === PHP_INT_MAX) {
                $minAvailable = 0;
            }

            $priceQuote = null;
            if ($ratePlanId !== null) {
                try {
                    $priceQuote = $this->pricingService->quoteStay(
                        propertyId: $propertyId,
                        roomTypeId: $roomType->id,
                        ratePlanId: $ratePlanId,
                        checkInDate: $checkInDate,
                        checkOutDate: $checkOutDate,
                        adults: $adults,
                        children: $children,
                    );
                } catch (Throwable $e) {
                    $violations[] = "Pricing unavailable: {$e->getMessage()}";
                }
            }

            $violations = array_values(array_unique($violations));
            $isAvailable = empty($violations) && $minAvailable > 0;

            $roomTypeQuotes[] = new RoomTypeQuoteDTO(
                roomTypeId: $roomType->id,
                roomTypeCode: $roomType->code,
                roomTypeName: $roomType->name,
                availableUnits: max(0, $minAvailable),
                isAvailable: $isAvailable,
                restrictionViolations: $violations,
                priceQuote: $priceQuote,
                nightlyAvailability: $nightlyDTOs,
            );
        }

        return new InventoryQuoteDTO(
            propertyId: $propertyId,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            nights: $nights,
            adults: $adults,
            children: $children,
            roomTypes: $roomTypeQuotes,
        );
    }
}
