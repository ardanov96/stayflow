<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            // Unique per call: 6 random alphanumerics + 4-digit suffix.
            // Faker's unique() tracker resets between test processes, so
            // collisions across the test suite are not a concern.
            'slug' => Str::lower(Str::random(6)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'active',
            'default_locale' => 'en',
            'settings' => [],
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
