<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Repositories;

use App\Modules\Inventory\Application\Ports\InventoryDayRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryDayDTO;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentInventoryDayRepository implements InventoryDayRepository
{
    public function findByRoomTypeAndDate(int $roomTypeId, string $date): ?InventoryDayDTO
    {
        /** @var InventoryDay|null $model */
        $model = InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->where('date', $date)
            ->first();

        return $model !== null ? $this->toDTO($model) : null;
    }

    public function getRange(int $roomTypeId, string $startDate, string $endDate): array
    {
        /** @var Collection<int, InventoryDay> $models */
        $models = InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('date')
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

    public function getRangeForRoomTypes(array $roomTypeIds, string $startDate, string $endDate): array
    {
        if (empty($roomTypeIds)) {
            return [];
        }

        /** @var Collection<int, InventoryDay> $models */
        $models = InventoryDay::query()
            ->whereIn('room_type_id', $roomTypeIds)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('room_type_id')
            ->orderBy('date')
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

    public function incrementBlockedUnits(int $roomTypeId, string $startDate, string $endDate, int $units): void
    {
        InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->increment('blocked_units', $units);
    }

    public function lockRangeForRoomTypes(array $roomTypeIds, string $startDate, string $endDate): array
    {
        if (empty($roomTypeIds)) {
            return [];
        }

        /** @var Collection<int, InventoryDay> $models */
        $models = InventoryDay::query()
            ->whereIn('room_type_id', $roomTypeIds)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('room_type_id')
            ->orderBy('date')
            ->lockForUpdate()
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

    public function incrementHeldUnits(int $roomTypeId, array $dates, int $units): void
    {
        if (empty($dates)) {
            return;
        }

        InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->increment('held_units', $units);
    }

    public function decrementHeldUnits(int $roomTypeId, array $dates, int $units): void
    {
        if (empty($dates)) {
            return;
        }

        InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->update([
                'held_units' => DB::raw("CASE WHEN held_units >= {$units} THEN held_units - {$units} ELSE 0 END"),
            ]);
    }

    public function decrementSoldUnits(int $roomTypeId, array $dates, int $units): void
    {
        if (empty($dates)) {
            return;
        }

        InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->update([
                'sold_units' => DB::raw("CASE WHEN sold_units >= {$units} THEN sold_units - {$units} ELSE 0 END"),
            ]);
    }

    public function convertHeldUnitsToSold(int $roomTypeId, array $dates, int $units): void
    {
        if (empty($dates)) {
            return;
        }

        InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->update([
                'held_units' => DB::raw("CASE WHEN held_units >= {$units} THEN held_units - {$units} ELSE 0 END"),
                'sold_units' => DB::raw("sold_units + {$units}"),
            ]);
    }

    public function decrementBlockedUnits(int $roomTypeId, string $startDate, string $endDate, int $units): void
    {
        InventoryDay::query()
            ->where('room_type_id', $roomTypeId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->update([
                'blocked_units' => DB::raw("CASE WHEN blocked_units >= {$units} THEN blocked_units - {$units} ELSE 0 END"),
            ]);
    }

    public function upsertDays(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        return (int) InventoryDay::query()->upsert(
            $rows,
            ['room_type_id', 'date'],
            ['total_units', 'updated_at']
        );
    }

    private function toDTO(InventoryDay $model): InventoryDayDTO
    {
        $rawDate = $model->getRawOriginal('date');
        $dateString = is_string($rawDate)
            ? substr($rawDate, 0, 10)
            : substr((string) $model->getAttribute('date'), 0, 10);

        return new InventoryDayDTO(
            id: $model->id,
            organizationId: $model->organization_id,
            propertyId: $model->property_id,
            roomTypeId: $model->room_type_id,
            date: $dateString,
            totalUnits: $model->total_units,
            heldUnits: $model->held_units,
            soldUnits: $model->sold_units,
            blockedUnits: $model->blocked_units,
            availableUnits: $model->available_units,
        );
    }
}
