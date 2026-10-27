<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Support;

use App\Modules\Inventory\Application\Ports\HoldRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldDTO;
use App\Modules\Inventory\Domain\Hold\HoldRequest;

/**
 * Resolves the stay dates a hold covers.
 *
 * The hold lines are the source of truth (they record exactly what was
 * held). The header is only used as a fallback for holds created before
 * the lines existed.
 */
trait ResolvesHoldDates
{
    /**
     * @return list<string>
     */
    private function holdDates(HoldRepository $holdRepository, int $organizationId, InventoryHoldDTO $hold): array
    {
        $lines = $holdRepository->linesFor($organizationId, $hold->id);

        if ($lines !== []) {
            return array_map(static fn ($line): string => $line->date, $lines);
        }

        $request = new HoldRequest(
            propertyId: $hold->propertyId,
            roomTypeId: $hold->roomTypeId,
            checkIn: $hold->checkIn,
            checkOut: $hold->checkOut,
            units: $hold->units,
        );

        return $request->stayDates();
    }
}
