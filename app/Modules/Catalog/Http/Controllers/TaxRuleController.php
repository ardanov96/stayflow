<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\CreateTaxRuleAction;
use App\Modules\Catalog\Http\Requests\StoreTaxRuleRequest;
use App\Modules\Catalog\Infrastructure\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TaxRuleController extends Controller
{
    public function index(int $propertyId): JsonResponse
    {
        $property = Property::findOrFail($propertyId);
        $rules = $property->taxRules;

        return response()->json([
            'data' => $rules,
        ]);
    }

    public function store(
        StoreTaxRuleRequest $request,
        int $propertyId,
        CreateTaxRuleAction $action,
    ): JsonResponse {
        $property = Property::findOrFail($propertyId);

        $taxRule = $action->execute(
            property: $property,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return response()->json([
            'data' => $taxRule,
        ], 201);
    }
}
