<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Application\Actions\SetRatePricesAction;
use App\Modules\Inventory\Http\Requests\SetRatePricesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class RatePriceController extends Controller
{
    public function setPrices(
        SetRatePricesRequest $request,
        SetRatePricesAction $action,
    ): JsonResponse {
        $user = $request->user();
        $actorId = $user !== null ? (int) $user->id : 0;
        $propertyId = (int) $request->validated('property_id');

        /** @var array{property_id: int, rate_plan_id: int, room_type_id: int, start_date: string, end_date: string, base_price_minor: int, currency?: string, extra_adult_price_minor?: int, extra_child_price_minor?: int} $validated */
        $validated = $request->validated();

        try {
            $action->execute(
                propertyId: $propertyId,
                payload: $validated,
                actorId: $actorId,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Rate prices updated successfully.']);
    }
}
