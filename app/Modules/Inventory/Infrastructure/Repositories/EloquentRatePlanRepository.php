<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Repositories;

use App\Modules\Catalog\Infrastructure\Models\PropertyPolicy;
use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;

final class EloquentRatePlanRepository implements RatePlanRepository
{
    public function findById(int $id): ?RatePlanDTO
    {
        $model = RatePlan::query()->find($id);
        if ($model === null) {
            return null;
        }

        return $this->toDTO($model);
    }

    public function findByCode(int $propertyId, string $code): ?RatePlanDTO
    {
        $model = RatePlan::query()
            ->where('property_id', $propertyId)
            ->where('code', $code)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDTO($model);
    }

    public function listByProperty(int $propertyId): array
    {
        return RatePlan::query()
            ->where('property_id', $propertyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RatePlan $plan) => $this->toDTO($plan))
            ->all();
    }

    public function save(array $data): RatePlanDTO
    {
        $model = RatePlan::query()->create($data);

        return $this->toDTO($model);
    }

    public function update(int $id, array $data): RatePlanDTO
    {
        /** @var RatePlan $model */
        $model = RatePlan::query()->findOrFail($id);
        $model->fill($data);
        $model->save();

        return $this->toDTO($model);
    }

    public function delete(int $id): bool
    {
        return (bool) RatePlan::query()->where('id', $id)->delete();
    }

    public function getCancellationPolicy(int $policyId): ?array
    {
        $policy = PropertyPolicy::query()->find($policyId);
        if ($policy === null) {
            return null;
        }

        return [
            'id' => $policy->id,
            'title' => $policy->title,
            'description' => $policy->description,
            'structured_terms' => $policy->structured_terms,
        ];
    }

    private function toDTO(RatePlan $plan): RatePlanDTO
    {
        return new RatePlanDTO(
            id: $plan->id,
            organizationId: $plan->organization_id,
            propertyId: $plan->property_id,
            code: $plan->code,
            name: $plan->name,
            description: $plan->description,
            mealPlan: $plan->meal_plan->value,
            cancellationPolicyId: $plan->cancellation_policy_id,
            parentRatePlanId: $plan->parent_rate_plan_id,
            derivationType: $plan->derivation_type?->value,
            derivationValue: $plan->derivation_value,
            isActive: $plan->is_active,
        );
    }
}
