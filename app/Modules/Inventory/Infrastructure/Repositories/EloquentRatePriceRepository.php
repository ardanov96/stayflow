<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Repositories;

use App\Modules\Inventory\Application\Ports\RatePriceRepository;
use App\Modules\Inventory\Contracts\DTOs\NightlyPriceDTO;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentRatePriceRepository implements RatePriceRepository
{
    public function getPricesForRange(
        int $ratePlanId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
    ): array {
        /** @var Collection<int, RatePrice> $models */
        $models = RatePrice::query()
            ->where('rate_plan_id', $ratePlanId)
            ->where('room_type_id', $roomTypeId)
            ->where('date', '>=', $startDate)
            ->where('date', '<', $endDate)
            ->get();

        $result = [];
        foreach ($models as $model) {
            $rawDate = $model->getRawOriginal('date');
            $dateString = is_string($rawDate)
                ? substr($rawDate, 0, 10)
                : substr((string) $model->getAttribute('date'), 0, 10);

            $result[$dateString] = new NightlyPriceDTO(
                date: $dateString,
                basePriceMinor: $model->base_price_minor,
                currency: $model->currency,
                extraAdults: 0,
                extraAdultPriceMinor: $model->extra_adult_price_minor,
                extraChildren: 0,
                extraChildPriceMinor: $model->extra_child_price_minor,
                totalPriceMinor: $model->base_price_minor,
            );
        }

        return $result;
    }

    public function setPrices(
        int $propertyId,
        int $ratePlanId,
        int $roomTypeId,
        array $prices,
    ): void {
        DB::transaction(function () use ($propertyId, $ratePlanId, $roomTypeId, $prices): void {
            foreach ($prices as $p) {
                RatePrice::query()->updateOrCreate(
                    [
                        'rate_plan_id' => $ratePlanId,
                        'room_type_id' => $roomTypeId,
                        'date' => $p['date'],
                    ],
                    [
                        'property_id' => $propertyId,
                        'base_price_minor' => $p['base_price_minor'],
                        'currency' => $p['currency'],
                        'extra_adult_price_minor' => $p['extra_adult_price_minor'] ?? 0,
                        'extra_child_price_minor' => $p['extra_child_price_minor'] ?? 0,
                    ]
                );
            }
        });
    }

    public function deletePrices(
        int $ratePlanId,
        int $roomTypeId,
        string $startDate,
        string $endDate,
    ): void {
        RatePrice::query()
            ->where('rate_plan_id', $ratePlanId)
            ->where('room_type_id', $roomTypeId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->delete();
    }
}
