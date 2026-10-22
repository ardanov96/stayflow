<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Inventory\Application\Ports\RatePlanRepository;
use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;
use App\Modules\Inventory\Domain\Exceptions\CircularDerivationException;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use InvalidArgumentException;

final readonly class UpdateRatePlanAction
{
    public function __construct(
        private RatePlanRepository $ratePlanRepository,
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        int $ratePlanId,
        array $data,
        int $actorId,
    ): RatePlanDTO {
        $existing = $this->ratePlanRepository->findById($ratePlanId);
        if ($existing === null) {
            throw new InvalidArgumentException("Rate plan {$ratePlanId} not found.");
        }

        if (array_key_exists('parent_rate_plan_id', $data) && $data['parent_rate_plan_id'] !== null) {
            $parentId = (int) $data['parent_rate_plan_id'];
            if ($parentId === $ratePlanId) {
                throw CircularDerivationException::forRatePlans($ratePlanId, $parentId);
            }

            // Guard against cycle: traverse ancestor chain
            $currentParentId = $parentId;
            while ($currentParentId !== null) {
                $ancestor = $this->ratePlanRepository->findById($currentParentId);
                if ($ancestor === null) {
                    break;
                }
                if ($ancestor->parentRatePlanId === $ratePlanId) {
                    throw CircularDerivationException::forRatePlans($ratePlanId, $parentId);
                }
                $currentParentId = $ancestor->parentRatePlanId;
            }
        }

        $updated = $this->ratePlanRepository->update($ratePlanId, $data);

        $this->auditor->recordCustom(
            'rate.changed',
            new SubjectChange(
                subjectType: 'RatePlan',
                subjectId: $updated->id,
                oldValues: null,
                newValues: $data,
                metadata: [
                    'actor_id' => $actorId,
                    'organization_id' => $existing->organizationId,
                ],
            ),
            new TenantHint($existing->organizationId, $existing->propertyId),
        );

        return $updated;
    }
}
