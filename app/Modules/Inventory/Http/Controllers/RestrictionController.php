<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Http\Requests\SetRestrictionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class RestrictionController
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function setRestrictions(SetRestrictionsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $saved = $this->inventoryService->setRestrictions(
            propertyId: (int) $validated['property_id'],
            ratePlanId: (int) $validated['rate_plan_id'],
            roomTypeId: (int) $validated['room_type_id'],
            startDate: (string) $validated['start_date'],
            endDate: (string) $validated['end_date'],
            minStay: isset($validated['min_stay']) ? (int) $validated['min_stay'] : null,
            maxStay: isset($validated['max_stay']) ? (int) $validated['max_stay'] : null,
            cta: isset($validated['cta']) ? (bool) $validated['cta'] : null,
            ctd: isset($validated['ctd']) ? (bool) $validated['ctd'] : null,
            stopSell: isset($validated['stop_sell']) ? (bool) $validated['stop_sell'] : null,
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json(array_map(fn ($r) => $r->toArray(), $saved));
    }
}
