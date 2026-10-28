<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Application\Ports\RatePriceRepository;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Support\Date\StayDate;
use InvalidArgumentException;

final readonly class SetRatePricesAction
{
    public function __construct(
        private RatePlanRepository $ratePlanRepository,
        private RatePriceRepository $ratePriceRepository,
        private Auditor $auditor,
    ) {}

    /**
     * Set prices for a date range or discrete list of dates.
     *
     * @param  array{
     *     rate_plan_id: int,
     *     room_type_id: int,
     *     start_date: string,
     *     end_date: string,
     *     base_price_minor: int,
     *     currency?: string,
     *     extra_adult_price_minor?: int,
     *     extra_child_price_minor?: int
     * }  $payload
     */
    public function execute(
        int $propertyId,
        array $payload,
        int $actorId,
    ): void {
        $ratePlan = $this->ratePlanRepository->findById($payload['rate_plan_id']);
        if ($ratePlan === null || $ratePlan->propertyId !== $propertyId) {
            throw new InvalidArgumentException("Rate plan {$payload['rate_plan_id']} does not belong to property {$propertyId}.");
        }

        $startDate = StayDate::fromString($payload['start_date']);
        $endDate = StayDate::fromString($payload['end_date']);

        if ($endDate->isBefore($startDate)) {
            throw new InvalidArgumentException("End date ({$payload['end_date']}) cannot precede start date ({$payload['start_date']}).");
        }

        $prices = [];
        $currency = $payload['currency'] ?? 'IDR';
        $extraAdult = $payload['extra_adult_price_minor'] ?? 0;
        $extraChild = $payload['extra_child_price_minor'] ?? 0;

        // The range is inclusive on both ends: a price covers the night
        // of the start date and the night of the end date.
        foreach ($startDate->range($endDate->plusDays(1)) as $date) {
            $prices[] = [
                'date' => $date->toString(),
                'base_price_minor' => $payload['base_price_minor'],
                'currency' => $currency,
                'extra_adult_price_minor' => $extraAdult,
                'extra_child_price_minor' => $extraChild,
            ];
        }

        $this->ratePriceRepository->setPrices(
            propertyId: $propertyId,
            ratePlanId: $payload['rate_plan_id'],
            roomTypeId: $payload['room_type_id'],
            prices: $prices,
        );

        $this->auditor->recordCustom(
            'rate.changed',
            new SubjectChange(
                subjectType: 'RatePrice',
                subjectId: $payload['rate_plan_id'],
                oldValues: null,
                newValues: [
                    'room_type_id' => $payload['room_type_id'],
                    'range' => "{$payload['start_date']}..{$payload['end_date']}",
                    'base_price_minor' => $payload['base_price_minor'],
                    'count' => count($prices),
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'organization_id' => $ratePlan->organizationId,
                ],
            ),
            new TenantHint($ratePlan->organizationId, $propertyId),
        );
    }
}
