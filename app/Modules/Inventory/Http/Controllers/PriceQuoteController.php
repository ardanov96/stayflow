<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Application\Actions\CalculateStayPriceAction;
use App\Modules\Inventory\Http\Requests\CalculatePriceQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PriceQuoteController extends Controller
{
    public function quote(
        CalculatePriceQuoteRequest $request,
        CalculateStayPriceAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        $quote = $action->execute(
            propertyId: (int) $validated['property_id'],
            roomTypeId: (int) $validated['room_type_id'],
            ratePlanId: (int) $validated['rate_plan_id'],
            checkInDate: (string) $validated['check_in_date'],
            checkOutDate: (string) $validated['check_out_date'],
            adults: (int) $validated['adults'],
            children: (int) ($validated['children'] ?? 0),
        );

        return response()->json(['data' => $quote->toArray()]);
    }
}
