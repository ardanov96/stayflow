<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Models\User;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth-aware tenant context resolver (P1-02 Identity).
 *
 * Resolution order:
 *   1. If the request is authenticated, look up the user's
 *      Membership rows and pick one. A user with a single
 *      Membership is unambiguous; a user with multiple picks the
 *      one whose id is stashed in the session as
 *      `tenant.active_organization_id` (the user switches via the
 *      topbar), or the lowest-id Membership as a default. A user
 *      with zero Memberships gets no context (the BelongsToTenant
 *      scope short-circuits to `1=0`, which is the safe default).
 *   2. If the request is NOT authenticated AND the env is not
 *      `production`, fall back to the X-Organization-Id /
 *      X-Property-Id headers so the P1-01 isolation suite and
 *      the local dev shell still work. This path is skipped in
 *      production so a public caller cannot spoof an org.
 *   3. Otherwise: no context.
 *
 * The dev-only header path remains because the TenantIsolationTest
 * needs to drive requests that simulate cross-tenant reads without
 * setting up a real user. Closing the path entirely would force the
 * test suite to seed an authenticatable user for every assertion.
 * The production-environment check is the gate.
 */
final class SetTenantContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $tenantContext = $this->resolveFromAuth($user, $request)
            ?? $this->resolveFromHeaders($request);

        if ($tenantContext === null) {
            return $next($request);
        }

        return $this->context->swap($tenantContext, static fn (): Response => $next($request));
    }

    private function resolveFromAuth(?Authenticatable $user, Request $request): ?TenantContext
    {
        if (! $user instanceof User) {
            return null;
        }

        $memberships = $user->memberships()->get();
        if ($memberships->isEmpty()) {
            return null;
        }

        // If the session already pins an active org, prefer it.
        $sessionOrg = $request->session()->get('tenant.active_organization_id');
        $membership = null;
        if (is_int($sessionOrg) && $sessionOrg >= 1) {
            $membership = $memberships->firstWhere('organization_id', $sessionOrg);
        }
        $membership ??= $memberships->sortBy('id')->first();

        if ($membership === null) {
            return null;
        }

        /** @var Membership $membership */
        $propertyId = $membership->property_id;
        $organizationId = (int) $membership->organization_id;

        return $propertyId === null
            ? TenantContext::forOrganization($organizationId)
            : TenantContext::forOrganizationAndProperty(
                $organizationId,
                (int) $propertyId,
            );
    }

    private function resolveFromHeaders(Request $request): ?TenantContext
    {
        if (app()->environment('production')) {
            return null;
        }

        $orgId = $this->parsePositiveInt($request->headers->get('X-Organization-Id'));
        if ($orgId === null) {
            return null;
        }

        $propId = $this->parsePositiveInt($request->headers->get('X-Property-Id'));

        return $propId === null
            ? TenantContext::forOrganization($orgId)
            : TenantContext::forOrganizationAndProperty($orgId, $propId);
    }

    private function parsePositiveInt(?string $raw): ?int
    {
        if ($raw === null || ! ctype_digit($raw)) {
            return null;
        }
        $value = (int) $raw;

        return $value >= 1 ? $value : null;
    }
}
