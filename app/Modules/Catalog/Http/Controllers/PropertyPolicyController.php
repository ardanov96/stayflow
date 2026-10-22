<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\SetPropertyPolicyAction;
use App\Modules\Catalog\Http\Requests\SetPropertyPolicyRequest;
use App\Modules\Catalog\Infrastructure\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PropertyPolicyController extends Controller
{
    public function index(int $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);
        $policies = $property->policies;

        return response()->json([
            'data' => $policies,
        ]);
    }

    public function store(
        SetPropertyPolicyRequest $request,
        int $propertyId,
        SetPropertyPolicyAction $action,
    ): JsonResponse {
        $property = Property::findOrFail($propertyId);

        $policy = $action->execute(
            property: $property,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $policy,
        ], 200);
    }
}
