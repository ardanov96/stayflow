<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\CreatePropertyAction;
use App\Modules\Catalog\Application\Actions\UpdatePropertyAction;
use App\Modules\Catalog\Http\Requests\StorePropertyRequest;
use App\Modules\Catalog\Http\Requests\UpdatePropertyRequest;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Contracts\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PropertyController extends Controller
{
    public function index(): JsonResponse
    {
        $properties = Property::with(['roomTypes', 'amenities', 'policies'])->get();

        return response()->json([
            'data' => $properties,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $property = Property::with(['roomTypes.amenities', 'amenities', 'taxRules', 'policies', 'media'])->findOrFail($id);

        return response()->json([
            'data' => $property,
        ]);
    }

    public function store(
        StorePropertyRequest $request,
        CreatePropertyAction $action,
        OrganizationContext $context,
    ): JsonResponse {
        $orgId = $context->current()?->organizationId;
        if ($orgId === null) {
            abort(403, 'No active organization context.');
        }

        $property = $action->execute(
            organizationId: $orgId,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $property,
        ], 201);
    }

    public function update(
        UpdatePropertyRequest $request,
        int $id,
        UpdatePropertyAction $action,
    ): JsonResponse {
        $property = Property::findOrFail($id);

        $updated = $action->execute(
            property: $property,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $updated,
        ]);
    }
}
