<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\DTOs;

use JsonSerializable;

final readonly class RatePlanDTO implements JsonSerializable
{
    /**
     * @param  array<string, string>|null  $description
     */
    public function __construct(
        public int $id,
        public int $organizationId,
        public int $propertyId,
        public string $code,
        public string $name,
        public ?array $description,
        public string $mealPlan,
        public ?int $cancellationPolicyId,
        public ?int $parentRatePlanId,
        public ?string $derivationType,
        public ?int $derivationValue,
        public bool $isActive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'property_id' => $this->propertyId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'meal_plan' => $this->mealPlan,
            'cancellation_policy_id' => $this->cancellationPolicyId,
            'parent_rate_plan_id' => $this->parentRatePlanId,
            'derivation_type' => $this->derivationType,
            'derivation_value' => $this->derivationValue,
            'is_active' => $this->isActive,
        ];
    }
}
