<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Infrastructure\Models\InventoryHold;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryHold>
 */
class InventoryHoldFactory extends Factory
{
    protected $model = InventoryHold::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'organization_id' => Organization::factory(),
            'property_id' => Property::factory(),
            'room_type_id' => RoomType::factory(),
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkIn->modify('+1 day')->format('Y-m-d'),
            'units' => 1,
            'status' => 'active',
            'idempotency_key' => fake()->unique()->uuid(),
            'expires_at' => now()->addMinutes(15),
            'reservation_id' => null,
            'committed_at' => null,
            'released_at' => null,
            'expired_at' => null,
            'release_reason' => null,
        ];
    }
}
