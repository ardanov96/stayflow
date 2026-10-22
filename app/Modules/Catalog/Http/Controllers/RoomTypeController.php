<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\CreateRoomTypeAction;
use App\Modules\Catalog\Application\Actions\UpdateRoomTypeAction;
use App\Modules\Catalog\Http\Requests\StoreRoomTypeRequest;
use App\Modules\Catalog\Http\Requests\UpdateRoomTypeRequest;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RoomTypeController extends Controller
{
    public function index(int $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);
        $roomTypes = $property->roomTypes()->with(['amenities', 'roomUnits', 'media'])->get();

        return response()->json([
            'data' => $roomTypes,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $roomType = RoomType::with(['amenities', 'roomUnits', 'media'])->findOrFail($id);

        return response()->json([
            'data' => $roomType,
        ]);
    }

    public function store(
        StoreRoomTypeRequest $request,
        int $propertyId,
        CreateRoomTypeAction $action,
    ): JsonResponse {
        $property = Property::findOrFail($propertyId);

        $roomType = $action->execute(
            property: $property,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $roomType,
        ], 201);
    }

    public function update(
        UpdateRoomTypeRequest $request,
        int $id,
        UpdateRoomTypeAction $action,
    ): JsonResponse {
        $roomType = RoomType::findOrFail($id);

        $updated = $action->execute(
            roomType: $roomType,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $updated,
        ]);
    }
}
