<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Http\Requests\InventoryQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryQuoteController
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function quote(InventoryQuoteRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $quote = $this->inventoryService->quote(
            propertyId: (int) $validated['property_id'],
            roomTypeIds: isset($validated['room_type_ids']) ? array_map('intval', (array) $validated['room_type_ids']) : [],
            checkInDate: (string) $validated['check_in_date'],
            checkOutDate: (string) $validated['check_out_date'],
            adults: (int) $validated['adults'],
            children: (int) ($validated['children'] ?? 0),
            ratePlanId: isset($validated['rate_plan_id']) ? (int) $validated['rate_plan_id'] : null,
        );

        return response()->json($quote->toArray());
    }

    public function getAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'room_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $days = $this->inventoryService->getAvailability(
            propertyId: (int) $validated['property_id'],
            roomTypeId: (int) $validated['room_type_id'],
            startDate: (string) $validated['start_date'],
            endDate: (string) $validated['end_date'],
        );

        return response()->json(array_map(fn ($d) => $d->toArray(), $days));
    }

    public function materialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'room_type_ids' => ['nullable', 'array'],
            'room_type_ids.*' => ['integer'],
        ]);

        $count = $this->inventoryService->materializeDays(
            propertyId: (int) $validated['property_id'],
            startDate: isset($validated['start_date']) ? (string) $validated['start_date'] : null,
            endDate: isset($validated['end_date']) ? (string) $validated['end_date'] : null,
            roomTypeIds: isset($validated['room_type_ids']) ? array_map('intval', (array) $validated['room_type_ids']) : null,
        );

        return response()->json([
            'message' => 'Inventory days materialized successfully.',
            'rows_affected' => $count,
        ]);
    }
}
