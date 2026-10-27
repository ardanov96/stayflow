<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Repositories;

use App\Modules\Inventory\Application\Ports\RestrictionDayRepository;
use App\Modules\Inventory\Contracts\DTOs\RestrictionDayDTO;
use App\Modules\Inventory\Infrastructure\Models\RestrictionDay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentRestrictionDayRepository implements RestrictionDayRepository
{
    public function getRestrictions(int $ratePlanId, int $roomTypeId, string $startDate, string $endDate): array
    {
        /** @var Collection<int, RestrictionDay> $models */
        $models = RestrictionDay::query()
            ->where('rate_plan_id', $ratePlanId)
            ->where('room_type_id', $roomTypeId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();

        $result = [];
        foreach ($models as $model) {
            $rawDate = $model->getRawOriginal('date');
            $dateString = is_string($rawDate)
                ? substr($rawDate, 0, 10)
                : substr((string) $model->getAttribute('date'), 0, 10);

            $result[$dateString] = $this->toDTO($model);
        }

        return $result;
    }

    public function getRestrictionsForRoomTypes(int $ratePlanId, array $roomTypeIds, string $startDate, string $endDate): array
    {
        if (empty($roomTypeIds)) {
            return [];
        }

        /** @var Collection<int, RestrictionDay> $models */
        $models = RestrictionDay::query()
            ->where('rate_plan_id', $ratePlanId)
            ->whereIn('room_type_id', $roomTypeIds)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();

        $result = [];
        foreach ($roomTypeIds as $id) {
            $result[$id] = [];
        }

        foreach ($models as $model) {
            $rawDate = $model->getRawOriginal('date');
            $dateString = is_string($rawDate)
                ? substr($rawDate, 0, 10)
                : substr((string) $model->getAttribute('date'), 0, 10);

            $result[$model->room_type_id][$dateString] = $this->toDTO($model);
        }

        return $result;
    }

    public function setRestrictionsBatch(array $records): array
    {
        return DB::transaction(function () use ($records): array {
            $saved = [];
            foreach ($records as $record) {
                /** @var RestrictionDay $model */
                $model = RestrictionDay::query()->updateOrCreate(
                    [
                        'organization_id' => $record['organization_id'],
                        'property_id' => $record['property_id'],
                        'rate_plan_id' => $record['rate_plan_id'],
                        'room_type_id' => $record['room_type_id'],
                        'date' => $record['date'],
                    ],
                    [
                        'min_stay' => $record['min_stay'] ?? null,
                        'max_stay' => $record['max_stay'] ?? null,
                        'cta' => (bool) ($record['cta'] ?? false),
                        'ctd' => (bool) ($record['ctd'] ?? false),
                        'stop_sell' => (bool) ($record['stop_sell'] ?? false),
                    ]
                );

                $saved[] = $this->toDTO($model);
            }

            return $saved;
        });
    }

    private function toDTO(RestrictionDay $model): RestrictionDayDTO
    {
        $rawDate = $model->getRawOriginal('date');
        $dateString = is_string($rawDate)
            ? substr($rawDate, 0, 10)
            : substr((string) $model->getAttribute('date'), 0, 10);

        return new RestrictionDayDTO(
            id: $model->id,
            organizationId: $model->organization_id,
            propertyId: $model->property_id,
            ratePlanId: $model->rate_plan_id,
            roomTypeId: $model->room_type_id,
            date: $dateString,
            minStay: $model->min_stay,
            maxStay: $model->max_stay,
            cta: (bool) $model->cta,
            ctd: (bool) $model->ctd,
            stopSell: (bool) $model->stop_sell,
        );
    }
}
