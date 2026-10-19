<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Models\User;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Application\Identity\Ports\RoleChecker;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that guards routes by checking whether the authenticated
 * user holds a role that grants the requested {@see Permission}.
 *
 * If unauthorized:
 *  1. Structured warning is written to the application log.
 *  2. An `auth.forbidden` event is recorded in the append-only audit log.
 *  3. Aborts with HTTP 403 Forbidden.
 */
final readonly class AuthorizePermission
{
    public function __construct(
        private RoleChecker $roleChecker,
        private OrganizationContext $organizationContext,
        private Auditor $auditor,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $permEnum = Permission::fromString($permission);
        $context = $this->organizationContext->current();

        $allowed = false;
        $orgId = $context?->organizationId;
        $propId = $context?->propertyId;

        if ($orgId !== null) {
            $allowed = $this->roleChecker->hasPermission($user, $orgId, $propId, $permEnum);
        }

        if (! $allowed) {
            Log::warning('Forbidden: user lacks required permission', [
                'user_id' => $user->id,
                'organization_id' => $orgId,
                'property_id' => $propId,
                'permission' => $permission,
                'path' => $request->path(),
            ]);

            if ($orgId !== null) {
                $this->auditor->recordCustom(
                    'auth.forbidden',
                    new SubjectChange(
                        subjectType: User::class,
                        subjectId: (int) $user->id,
                        metadata: [
                            'permission' => $permission,
                            'path' => $request->path(),
                            'method' => $request->method(),
                            'ip' => (string) $request->ip(),
                        ],
                    ),
                    new TenantHint($orgId, $propId),
                );
            }

            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
