<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\Property\PropertyStatus;
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
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Resort & Villas',
            'slug' => Str::lower(Str::random(6)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => PropertyStatus::Active,
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'email' => fake()->safeEmail(),
            'phone' => '+6281234567890',
            'address' => [
                'street' => fake()->streetAddress(),
                'city' => 'Badung',
                'province' => 'Bali',
                'postal_code' => '80361',
                'country' => 'ID',
            ],
            'description' => [
                'en' => fake()->paragraph(),
                'id' => fake()->paragraph(),
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => PropertyStatus::Inactive]);
    }

    public function onboarding(): static
    {
        return $this->state(fn (): array => ['status' => PropertyStatus::Onboarding]);
    }
}
