<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Services;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Catalog\Contracts\DTOs\PropertyDTO;
use App\Modules\Catalog\Contracts\DTOs\RoomTypeDTO;
use App\Modules\Catalog\Contracts\DTOs\RoomUnitDTO;
use App\Modules\Catalog\Contracts\DTOs\TaxRuleDTO;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use App\Modules\Catalog\Infrastructure\Models\TaxRule;
use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxKind;

/**
 * Eloquent implementation of CatalogService contract.
 */
class EloquentCatalogService implements CatalogService
{
    public function getProperty(int $propertyId): ?PropertyDTO
    {
        $property = Property::find($propertyId);
        if ($property === null) {
            return null;
        }

        $statusStr = $property->status->value;

        return new PropertyDTO(
            id: (int) $property->id,
            organizationId: (int) $property->organization_id,
            name: (string) $property->name,
            slug: (string) $property->slug,
            status: $statusStr,
            timezone: (string) $property->timezone,
            currency: (string) $property->currency,
            checkInTime: (string) $property->check_in_time,
            checkOutTime: (string) $property->check_out_time,
        );
    }

    public function getRoomType(int $roomTypeId): ?RoomTypeDTO
    {
        $roomType = RoomType::find($roomTypeId);
        if ($roomType === null) {
            return null;
        }

        $statusStr = $roomType->status->value;

        return new RoomTypeDTO(
            id: (int) $roomType->id,
            organizationId: (int) $roomType->organization_id,
            propertyId: (int) $roomType->property_id,
            code: (string) $roomType->code,
            name: (string) $roomType->name,
            baseOccupancy: (int) $roomType->base_occupancy,
            maxAdults: (int) $roomType->max_adults,
            maxChildren: (int) $roomType->max_children,
            totalUnits: (int) $roomType->total_units,
            status: $statusStr,
        );
    }

    /**
     * @return list<RoomTypeDTO>
     */
    public function getRoomTypesForProperty(int $propertyId): array
    {
        return RoomType::where('property_id', $propertyId)
            ->get()
            ->map(function (RoomType $rt): RoomTypeDTO {
                $statusStr = $rt->status->value;

                return new RoomTypeDTO(
                    id: (int) $rt->id,
                    organizationId: (int) $rt->organization_id,
                    propertyId: (int) $rt->property_id,
                    code: (string) $rt->code,
                    name: (string) $rt->name,
                    baseOccupancy: (int) $rt->base_occupancy,
                    maxAdults: (int) $rt->max_adults,
                    maxChildren: (int) $rt->max_children,
                    totalUnits: (int) $rt->total_units,
                    status: $statusStr,
                );
            })
            ->all();
    }

    /**
     * @return list<TaxRuleDTO>
     */
    public function getTaxRulesForProperty(int $propertyId): array
    {
        return TaxRule::where('property_id', $propertyId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function (TaxRule $tr): TaxRuleDTO {
                $kindStr = $tr->kind instanceof TaxKind ? $tr->kind->value : (string) $tr->kind;
                $appliesToStr = $tr->applies_to instanceof TaxAppliesTo ? $tr->applies_to->value : (string) $tr->applies_to;

                return new TaxRuleDTO(
                    id: (int) $tr->id,
                    propertyId: (int) $tr->property_id,
                    name: (string) $tr->name,
                    kind: $kindStr,
                    rateBps: (int) $tr->rate_bps,
                    isInclusive: (bool) $tr->is_inclusive,
                    appliesTo: $appliesToStr,
                    order: (int) $tr->order,
                    isActive: (bool) $tr->is_active,
                );
            })
            ->all();
    }

    public function getActiveRoomUnitsCount(int $roomTypeId): int
    {
        $roomType = RoomType::find($roomTypeId);
        if ($roomType === null) {
            return 0;
        }

        return $roomType->roomUnits()->where('is_active', true)->count();
    }

    /**
     * @return list<RoomUnitDTO>
     */
    public function getRoomUnitsForType(int $roomTypeId): array
    {
        return RoomUnit::query()
            ->where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->orderBy('number')
            ->get()
            ->map(static fn (RoomUnit $unit): RoomUnitDTO => new RoomUnitDTO(
                id: (int) $unit->id,
                organizationId: (int) $unit->organization_id,
                propertyId: (int) $unit->property_id,
                roomTypeId: (int) $unit->room_type_id,
                number: (string) $unit->number,
                floor: $unit->floor !== null ? (string) $unit->floor : null,
                status: $unit->status->value,
                isActive: (bool) $unit->is_active,
            ))
            ->all();
    }
}
