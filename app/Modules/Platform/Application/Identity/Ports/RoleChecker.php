<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity\Ports;

use App\Models\User;
use App\Modules\Platform\Domain\Identity\Permission;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Infrastructure\Models\Membership;
use Illuminate\Support\Collection;

/**
 * The seam the rest of the app uses to ask "can this user do X
 * inside this tenant?". Implementation lives in
 * {@see \App\Modules\Platform\Infrastructure\Identity\
 * EloquentRoleChecker} and is bound in {@see \App\Modules\Platform\
 * Infrastructure\PlatformServiceProvider::register()}.
 *
 * Semantics (the same across every check on this port):
 *  - A user may have any number of {@see Membership} rows in the
 *    same organization (one per property, plus a possible org-wide
 *    one with property_id NULL).
 *  - For a property-scoped check ({@see self::hasPermission()} with
 *    $propertyId set): the user passes if ANY of their memberships
 *    in the org has either property_id NULL (org-wide authority) or
 *    property_id === $propertyId, AND the role allows the
 *    permission.
 *  - For an org-scoped check ($propertyId === null): the user passes
 *    if ANY of their memberships in the org has a role that allows
 *    the permission. There is no "property context" to match.
 *  - A user with NO memberships in the org is denied everything.
 *
 * The port is Eloquent-aware at the type level ({@see User},
 * {@see Membership}) because the Application layer is allowed to
 * talk to Eloquent models; only cross-module reads are forbidden
 * (see tests/Arch/EloquentBoundariesTest.php).
 */
interface RoleChecker
{
    /**
     * @return Collection<int, Membership> the user's active memberships
     *                                     in the given organization
     */
    public function membershipsIn(User $user, int $organizationId): Collection;

    public function hasRole(User $user, int $organizationId, ?int $propertyId, Role $role): bool;

    public function hasPermission(User $user, int $organizationId, ?int $propertyId, Permission $permission): bool;
}
