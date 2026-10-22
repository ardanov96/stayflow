<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\CreateRoomUnitAction;
use App\Modules\Catalog\Application\Actions\UpdateRoomUnitStatusAction;
use App\Modules\Catalog\Domain\Room\RoomUnitStatus;
use App\Modules\Catalog\Http\Requests\StoreRoomUnitRequest;
use App\Modules\Catalog\Http\Requests\UpdateRoomUnitStatusRequest;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RoomUnitController extends Controller
{
    public function index(int $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);
        $units = $property->roomUnits()->with('roomType')->get();

        return response()->json([
            'data' => $units,
        ]);
    }

    public function store(
        StoreRoomUnitRequest $request,
        int $roomTypeId,
        CreateRoomUnitAction $action,
    ): JsonResponse {
        $roomType = RoomType::findOrFail($roomTypeId);

        $unit = $action->execute(
            roomType: $roomType,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $unit,
        ], 201);
    }

    public function updateStatus(
        UpdateRoomUnitStatusRequest $request,
        int $id,
        UpdateRoomUnitStatusAction $action,
    ): JsonResponse {
        $unit = RoomUnit::findOrFail($id);

        $newStatus = RoomUnitStatus::from((string) $request->input('status'));
        $isActive = $request->has('is_active') ? (bool) $request->input('is_active') : null;
        $notes = $request->input('notes');

        $updated = $action->execute(
            roomUnit: $unit,
            newStatus: $newStatus,
            isActive: $isActive,
            notes: $notes,
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $updated,
        ]);
    }
}
