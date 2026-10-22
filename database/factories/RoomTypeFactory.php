<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\Room\RoomTypeStatus;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => function (array $attributes): int {
                if (isset($attributes['property_id'])) {
                    $property = Property::find($attributes['property_id']);
                    if ($property !== null) {
                        return (int) $property->organization_id;
                    }
                }

                return (int) Organization::factory()->create()->id;
            },
            'property_id' => Property::factory(),
            'code' => Str::upper(Str::random(3)).'-'.fake()->unique()->numberBetween(100, 999),
            'name' => fake()->randomElement(['Deluxe King Suite', 'Standard Twin Villa', 'Ocean Executive Room', 'Garden Suite']),
            'description' => [
                'en' => 'Spacious room with pool view, high-speed WiFi and luxurious bathroom.',
                'id' => 'Kamar luas dengan pemandangan kolam renang, WiFi cepat, dan kamar mandi mewah.',
            ],
            'base_occupancy' => 2,
            'max_adults' => 2,
            'max_children' => 1,
            'total_units' => 5,
            'status' => RoomTypeStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RoomTypeStatus::Inactive]);
    }
}
