<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Infrastructure\Models\InventoryDay;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryDay>
 */
class InventoryDayFactory extends Factory
{
    protected $model = InventoryDay::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory(),
            'room_type_id' => RoomType::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'total_units' => 10,
            'held_units' => 0,
            'sold_units' => 0,
            'blocked_units' => 0,
        ];
    }
}
