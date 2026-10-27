<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Infrastructure\Models\InventoryBlock;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBlock>
 */
class InventoryBlockFactory extends Factory
{
    protected $model = InventoryBlock::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory(),
            'room_type_id' => RoomType::factory(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'units' => 2,
            'reason' => 'Routine Maintenance',
            'created_by' => User::factory(),
        ];
    }
}
