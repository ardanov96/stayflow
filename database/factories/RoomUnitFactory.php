<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\Room\BedConfig;
use App\Modules\Catalog\Domain\Room\RoomUnitStatus;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Catalog\Infrastructure\Models\RoomUnit;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomUnit>
 */
class RoomUnitFactory extends Factory
{
    protected $model = RoomUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => function (array $attributes): int {
                if (isset($attributes['room_type_id'])) {
                    $roomType = RoomType::find($attributes['room_type_id']);
                    if ($roomType !== null) {
                        return (int) $roomType->organization_id;
                    }
                }

                return (int) Organization::factory()->create()->id;
            },
            'property_id' => function (array $attributes): int {
                if (isset($attributes['room_type_id'])) {
                    $roomType = RoomType::find($attributes['room_type_id']);
                    if ($roomType !== null) {
                        return (int) $roomType->property_id;
                    }
                }

                return (int) Property::factory()->create()->id;
            },
            'room_type_id' => RoomType::factory(),
            'number' => (string) fake()->unique()->numberBetween(101, 999),
            'floor' => (string) fake()->numberBetween(1, 4),
            'bed_config' => BedConfig::King,
            'status' => RoomUnitStatus::Clean,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function dirty(): static
    {
        return $this->state(fn (): array => ['status' => RoomUnitStatus::Dirty]);
    }

    public function outOfOrder(): static
    {
        return $this->state(fn (): array => ['status' => RoomUnitStatus::OutOfOrder]);
    }
}
