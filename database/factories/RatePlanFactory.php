<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\MealPlan;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    protected $model = RatePlan::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory(),
            'code' => 'BAR-'.Str::upper(Str::random(4)),
            'name' => 'Best Available Rate',
            'description' => [
                'en' => fake()->sentence(),
                'id' => fake()->sentence(),
            ],
            'meal_plan' => MealPlan::RoomOnly,
            'cancellation_policy_id' => null,
            'parent_rate_plan_id' => null,
            'derivation_type' => null,
            'derivation_value' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function derivedFrom(RatePlan $parent, DerivationType $type, int $value): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $parent->organization_id,
            'property_id' => $parent->property_id,
            'parent_rate_plan_id' => $parent->id,
            'derivation_type' => $type,
            'derivation_value' => $value,
        ]);
    }
}
