<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Services;

use App\Modules\Inventory\Application\Actions\CalculateStayPriceAction;
use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;
use App\Modules\Inventory\Contracts\PricingService;
use App\Modules\Inventory\Domain\Pricing\StayPriceQuote;

final readonly class EloquentPricingService implements PricingService
{
    public function __construct(
        private CalculateStayPriceAction $calculateStayPriceAction,
        private RatePlanRepository $ratePlanRepository,
    ) {}

    public function quoteStay(
        int $propertyId,
        int $roomTypeId,
        int $ratePlanId,
        string $checkInDate,
        string $checkOutDate,
        int $adults,
        int $children = 0,
    ): StayPriceQuote {
        return $this->calculateStayPriceAction->execute(
            propertyId: $propertyId,
            roomTypeId: $roomTypeId,
            ratePlanId: $ratePlanId,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            adults: $adults,
            children: $children,
        );
    }

    public function getRatePlansForProperty(int $propertyId): array
    {
        return $this->ratePlanRepository->listByProperty($propertyId);
    }

    public function findRatePlan(int $ratePlanId): ?RatePlanDTO
    {
        return $this->ratePlanRepository->findById($ratePlanId);
    }
}
