<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Models\User;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Grants a user a {@see Role} in a given organization (and optionally
 * a specific property). If a membership already exists for the
 * (user, organization, property) triple, its role is updated; a new
 * row is created otherwise. The change is recorded in the audit log
 * under the event name `member.role.changed` so the role-management
 * UI never has to write its own audit row.
 *
 * The action does NOT enforce "only Owner/Manager can assign roles"
 * — that is the caller's job (the Filament resource uses a Policy
 * with `roles.assign`). This is the same separation Laravel
 * recommends for actions: the action does the work, the gate
 * decides who is allowed to call it.
 */
final readonly class AssignRole
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    public function execute(
        User $actor,
        User $subject,
        Organization $organization,
        ?int $propertyId,
        Role $role,
    ): Membership {
        if ($propertyId !== null && $propertyId < 1) {
            throw new InvalidArgumentException('propertyId must be a positive integer when set.');
        }

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

            $previousRole = $membership?->role;

            if ($membership === null) {
                $membership = new Membership;
                $membership->user_id = $subject->id;
                $membership->organization_id = $organization->id;
                $membership->property_id = $propertyId;
                $membership->role = $role->value;
                $membership->save();
            } else {
                $membership->role = $role->value;
                $membership->save();
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Failed to assign role: '.$e->getMessage(),
                previous: $e,
            );
        }

        $eventName = $previousRole === null ? 'member.role.assigned' : 'member.role.changed';
        $this->auditor->recordCustom(
            $eventName,
            new SubjectChange(
                subjectType: Membership::class,
                subjectId: (int) $membership->id,
                oldValues: $previousRole === null ? null : ['role' => $previousRole],
                newValues: ['role' => $role->value, 'property_id' => $propertyId],
                metadata: [
                    'actor_id' => $actor->id,
                    'subject_user_id' => $subject->id,
                    'organization_id' => $organization->id,
                ],
            ),
            $tenant,
        );

        return $membership;
    }
}
