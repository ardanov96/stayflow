<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Models\User;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use RuntimeException;
use Throwable;

/**
 * Revokes a {@see Membership} (i.e. removes the role assignment
 * entirely, not just changes the role). Records a `member.role.revoked`
 * audit event so the role-management UI never has to write its own
 * audit row.
 *
 * No-op (returns false, writes no audit row) if the membership
 * does not exist; "revoke a non-existent role" is a normal
 * idempotent outcome, not an error.
 */
final readonly class RevokeRole
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    public function execute(
        User $actor,
        User $subject,
        Organization $organization,
        ?int $propertyId,
    ): bool {
        $tenant = new TenantHint($organization->id, $propertyId);

        try {
            $membership = Membership::withoutGlobalScopes()
                ->where('user_id', $subject->id)
                ->where('organization_id', $organization->id)
                ->where(function ($q) use ($propertyId): void {
                    if ($propertyId === null) {
                        $q->whereNull('property_id');
                    } else {
                        $q->where('property_id', $propertyId);
                    }
                })
                ->first();

            if ($membership === null) {
                return false;
            }

            $previousRole = $membership->role;
            $membershipId = (int) $membership->id;
            $membership->delete();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Failed to revoke role: '.$e->getMessage(),
                previous: $e,
            );
        }

        $this->auditor->recordCustom(
            'member.role.revoked',
            new SubjectChange(
                subjectType: Membership::class,
                subjectId: $membershipId,
                oldValues: ['role' => $previousRole, 'property_id' => $propertyId],
                newValues: null,
                metadata: [
                    'actor_id' => $actor->id,
                    'subject_user_id' => $subject->id,
                    'organization_id' => $organization->id,
                ],
            ),
            $tenant,
        );

        return true;
    }
}
