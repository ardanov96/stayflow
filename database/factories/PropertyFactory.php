<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // organization_id is required by the tenant scope; factory
            // creates a fresh org by default. Tests that need a
            // specific org override this field.
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Hotel',
            'slug' => Str::lower(Str::random(6)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'active',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => 'suspended']);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived']);
    }
}
