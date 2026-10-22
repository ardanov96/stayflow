<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Amenity;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => null,
            'code' => Str::lower(fake()->unique()->word()),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(['property', 'room', 'bathroom', 'media']),
            'icon' => 'sparkles',
        ];
    }
}
