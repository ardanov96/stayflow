<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RestrictionDay;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestrictionDay>
 */
class RestrictionDayFactory extends Factory
{
    protected $model = RestrictionDay::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory(),
            'rate_plan_id' => RatePlan::factory(),
            'room_type_id' => RoomType::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'min_stay' => 1,
            'max_stay' => 30,
            'cta' => false,
            'ctd' => false,
            'stop_sell' => false,
        ];
    }
}
