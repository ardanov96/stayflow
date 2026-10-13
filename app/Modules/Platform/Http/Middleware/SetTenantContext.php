<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev-only tenant context resolver. Reads X-Organization-Id and
 * X-Property-Id headers (positive integers) and pushes a
 * {@see TenantContext} into the {@see OrganizationContext} stack
 * for the lifetime of the request.
 *
 * - Missing or invalid header: no-op (the request runs with no
 *   tenant context; queries on tenant tables return zero rows).
 * - X-Organization-Id present, X-Property-Id absent: organization-
 *   wide context. All tenant-scoped queries for this request are
 *   filtered by the given org id.
 * - Both headers present: property-scoped context. The BelongsTo
 *   Tenant scope adds property_id = X-Property-Id to queries on
 *   tables that have a property_id column.
 *
 * Replaced in P1-02 (Identity) by an auth-aware resolver that
 * derives the org from the authenticated user's Memberships. The
 * header-based resolver exists for now because P1-01 has no
 * authentication yet. It is intentionally permissive: any caller
 * that knows a valid org id can set the context. P1-02 closes
 * that gap.
 */
final class SetTenantContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $this->parsePositiveInt($request->headers->get('X-Organization-Id'));
        if ($orgId === null) {
            return $next($request);
        }

        $propId = $this->parsePositiveInt($request->headers->get('X-Property-Id'));
        $tenantContext = $propId === null
            ? TenantContext::forOrganization($orgId)
            : TenantContext::forOrganizationAndProperty($orgId, $propId);

        return $this->context->swap($tenantContext, static fn (): Response => $next($request));
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
