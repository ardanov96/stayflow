<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Http\Requests\CreateInventoryBlockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class InventoryBlockController
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function store(CreateInventoryBlockRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $block = $this->inventoryService->block(
            propertyId: (int) $validated['property_id'],
            roomTypeId: (int) $validated['room_type_id'],
            startDate: (string) $validated['start_date'],
            endDate: (string) $validated['end_date'],
            units: (int) $validated['units'],
            reason: (string) $validated['reason'],
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json($block->toArray(), 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->inventoryService->unblock(
            blockId: $id,
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json(['message' => 'Block removed successfully.']);
    }
}
