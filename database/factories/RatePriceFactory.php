<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePrice>
 */
class RatePriceFactory extends Factory
{
    protected $model = RatePrice::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory(),
            'rate_plan_id' => RatePlan::factory(),
            'room_type_id' => RoomType::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'base_price_minor' => 1_000_000_00, // 1,000,000 IDR
            'currency' => 'IDR',
            'extra_adult_price_minor' => 200_000_00, // 200,000 IDR
            'extra_child_price_minor' => 100_000_00, // 100,000 IDR
        ];
    }
}
