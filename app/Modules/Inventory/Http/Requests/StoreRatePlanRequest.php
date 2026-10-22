<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\MealPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'array'],
            'meal_plan' => ['nullable', new Enum(MealPlan::class)],
            'cancellation_policy_id' => ['nullable', 'integer', 'exists:property_policies,id'],
            'parent_rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'derivation_type' => ['nullable', 'required_with:parent_rate_plan_id', new Enum(DerivationType::class)],
            'derivation_value' => ['nullable', 'required_with:parent_rate_plan_id', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
