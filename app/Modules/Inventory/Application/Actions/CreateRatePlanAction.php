<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use InvalidArgumentException;

final readonly class CreateRatePlanAction
{
    public function __construct(
        private RatePlanRepository $ratePlanRepository,
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        int $organizationId,
        int $propertyId,
        array $data,
        int $actorId,
    ): RatePlanDTO {
        $parentRatePlanId = ! empty($data['parent_rate_plan_id'])
            ? (int) $data['parent_rate_plan_id']
            : null;

        if ($parentRatePlanId !== null) {
            $parent = $this->ratePlanRepository->findById($parentRatePlanId);
            if ($parent === null || $parent->propertyId !== $propertyId) {
                throw new InvalidArgumentException("Parent rate plan {$parentRatePlanId} does not exist in property {$propertyId}.");
            }
        }

        $ratePlan = $this->ratePlanRepository->save([
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'meal_plan' => $data['meal_plan'] ?? 'room_only',
            'cancellation_policy_id' => $data['cancellation_policy_id'] ?? null,
            'parent_rate_plan_id' => $parentRatePlanId,
            'derivation_type' => $data['derivation_type'] ?? null,
            'derivation_value' => isset($data['derivation_value']) ? (int) $data['derivation_value'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->auditor->recordCustom(
            'rate.changed',
            new SubjectChange(
                subjectType: 'RatePlan',
                subjectId: $ratePlan->id,
                oldValues: null,
                newValues: [
                    'code' => $ratePlan->code,
                    'name' => $ratePlan->name,
                    'meal_plan' => $ratePlan->mealPlan,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'organization_id' => $organizationId,
                ],
            ),
            new TenantHint($organizationId, $propertyId),
        );

        return $ratePlan;
    }
}
