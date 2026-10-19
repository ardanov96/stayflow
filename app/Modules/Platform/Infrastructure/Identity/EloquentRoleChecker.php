<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Identity;

use App\Models\User;
use App\Modules\Platform\Application\Identity\Ports\RoleChecker;
use App\Modules\Platform\Domain\Identity\Permission;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Identity\RolePermissionMatrix;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope;
use App\Modules\Platform\Infrastructure\Models\Membership;
use Illuminate\Support\Collection;

/**
 * Default adapter for {@see RoleChecker}. Reads the user's
 * {@see Membership} rows and consults the static
 * {@see RolePermissionMatrix}; the call is a pure function of
 * (user, organization, property, permission) once the rows are
 * loaded.
 *
 * The query is filtered to the requested organization only, so a
 * user belonging to multiple organizations has to be checked per-org.
 * Soft-deleted memberships are excluded (a deleted row means the
 * role was revoked and the audit log already records it).
 */
final class EloquentRoleChecker implements RoleChecker
{
    public function membershipsIn(User $user, int $organizationId): Collection
    {
        return Membership::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->get();
    }

    public function hasRole(User $user, int $organizationId, ?int $propertyId, Role $role): bool
    {
        foreach ($this->membershipsIn($user, $organizationId) as $membership) {
            if (! $this->propertyMatches($membership, $propertyId)) {
                continue;
            }
            if ($membership->roleEnum() === $role) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(User $user, int $organizationId, ?int $propertyId, Permission $permission): bool
    {
        $matrix = RolePermissionMatrix::instance();
        foreach ($this->membershipsIn($user, $organizationId) as $membership) {
            if (! $this->propertyMatches($membership, $propertyId)) {
                continue;
            }
            $role = $membership->roleEnum();
            if ($matrix->allows($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A membership with property_id NULL applies to every property
     * (org-wide authority). A membership with property_id === P
     * applies only to P. The check is symmetric: when the caller
     * asks about an org-scoped check ($propertyId === null) we
     * accept any property_id on the membership; when the caller
     * asks about a property-scoped check we require an exact match
     * OR an org-wide membership.
     */
    private function propertyMatches(Membership $membership, ?int $requestedPropertyId): bool
    {
        $membershipPropertyId = $membership->property_id !== null ? (int) $membership->property_id : null;

        if ($membershipPropertyId === null) {
            return true; // org-wide membership applies everywhere
        }
        if ($requestedPropertyId === null) {
            return true; // org-scoped check accepts any property binding
        }

        return $membershipPropertyId === $requestedPropertyId;
    }
}
