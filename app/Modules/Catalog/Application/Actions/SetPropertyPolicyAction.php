<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Policy\PolicyType;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\PropertyPolicy;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;

/**
 * Action to set or update a property policy (cancellation, payment, house rules).
 */
final readonly class SetPropertyPolicyAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param array{
     *     type: PolicyType|string,
     *     title: string,
     *     description?: array<string, string>|null,
     *     structured_terms?: array<string, mixed>|null,
     *     is_active?: bool
     * } $data
     */
    public function execute(Property $property, array $data, ?int $actorId = null): PropertyPolicy
    {
        $type = $data['type'];
        if (is_string($type)) {
            $type = PolicyType::from($type);
        }

        $policy = PropertyPolicy::where('property_id', $property->id)
            ->where('type', $type->value)
            ->first();

        $oldValues = $policy !== null ? [
            'title' => $policy->title,
            'is_active' => $policy->is_active,
        ] : null;

        if ($policy === null) {
            $policy = PropertyPolicy::create([
                'organization_id' => $property->organization_id,
                'property_id' => $property->id,
                'type' => $type,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'structured_terms' => $data['structured_terms'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        } else {
            $policy->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? $policy->description,
                'structured_terms' => $data['structured_terms'] ?? $policy->structured_terms,
                'is_active' => $data['is_active'] ?? $policy->is_active,
            ]);
        }

        $this->auditor->recordCustom(
            'catalog.policy.saved',
            new SubjectChange(
                subjectType: PropertyPolicy::class,
                subjectId: (int) $policy->id,
                oldValues: $oldValues,
                newValues: [
                    'type' => $policy->type->value,
                    'title' => $policy->title,
                    'is_active' => $policy->is_active,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $property->id,
                ],
            ),
            new TenantHint((int) $property->organization_id, (int) $property->id),
        );

        return $policy;
    }
}
