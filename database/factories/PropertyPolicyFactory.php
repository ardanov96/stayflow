<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\Policy\PolicyType;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\PropertyPolicy;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyPolicy>
 */
class PropertyPolicyFactory extends Factory
{
    protected $model = PropertyPolicy::class;

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
            'type' => PolicyType::Cancellation,
            'title' => 'Standard Flexible Cancellation Policy',
            'description' => [
                'en' => 'Cancel up to 48 hours before check-in for a full refund.',
                'id' => 'Pembatalan gratis hingga 48 jam sebelum waktu check-in.',
            ],
            'structured_terms' => [
                'free_cancellation_hours' => 48,
                'penalty_percent' => 100,
            ],
            'is_active' => true,
        ];
    }
}
