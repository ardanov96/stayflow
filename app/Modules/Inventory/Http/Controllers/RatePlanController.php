<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Application\Actions\CreateRatePlanAction;
use App\Modules\Inventory\Application\Actions\UpdateRatePlanAction;
use App\Modules\Inventory\Contracts\PricingService;
use App\Modules\Inventory\Http\Requests\StoreRatePlanRequest;
use App\Modules\Inventory\Http\Requests\UpdateRatePlanRequest;
use App\Modules\Platform\Contracts\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class RatePlanController extends Controller
{
    public function index(Request $request, PricingService $pricingService): JsonResponse
    {
        $propertyId = (int) $request->query('property_id');
        if ($propertyId <= 0) {
            return response()->json(['message' => 'property_id query parameter is required.'], 400);
        }

        $plans = $pricingService->getRatePlansForProperty($propertyId);

        return response()->json(['data' => $plans]);
    }

    public function show(int $id, PricingService $pricingService): JsonResponse
    {
        $plan = $pricingService->findRatePlan($id);
        if ($plan === null) {
            return response()->json(['message' => 'Rate plan not found.'], 404);
        }

        return response()->json(['data' => $plan]);
    }

    public function store(
        StoreRatePlanRequest $request,
        CreateRatePlanAction $action,
        OrganizationContext $context,
    ): JsonResponse {
        $currentContext = $context->current();
        $orgId = $currentContext !== null ? $currentContext->organizationId : 0;
        $user = $request->user();
        $actorId = $user !== null ? (int) $user->id : 0;

        try {
            $dto = $action->execute(
                organizationId: $orgId,
                propertyId: (int) $request->validated('property_id'),
                data: $request->validated(),
                actorId: $actorId,
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $dto], 201);
    }

    public function update(
        int $id,
        UpdateRatePlanRequest $request,
        UpdateRatePlanAction $action,
    ): JsonResponse {
        $user = $request->user();
        $actorId = $user !== null ? (int) $user->id : 0;

        try {
            $dto = $action->execute(
                ratePlanId: $id,
                data: $request->validated(),
                actorId: $actorId,
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $dto]);
    }
}
