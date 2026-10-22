<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\TaxRule;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRule>
 */
class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

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
            'name' => 'PB1 Pajak Hotel',
            'kind' => TaxKind::Tax,
            'rate_bps' => 1000, // 10%
            'is_inclusive' => false,
            'applies_to' => TaxAppliesTo::Room,
            'order' => 1,
            'is_active' => true,
        ];
    }

    public function serviceCharge(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Service Charge',
            'kind' => TaxKind::ServiceCharge,
            'rate_bps' => 1000,
            'is_inclusive' => false,
            'order' => 1,
        ]);
    }

    public function inclusive(): static
    {
        return $this->state(fn (): array => ['is_inclusive' => true]);
    }
}
