<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\MealPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRatePlanRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'array'],
            'meal_plan' => ['sometimes', new Enum(MealPlan::class)],
            'cancellation_policy_id' => ['nullable', 'integer', 'exists:property_policies,id'],
            'parent_rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'derivation_type' => ['nullable', new Enum(DerivationType::class)],
            'derivation_value' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
