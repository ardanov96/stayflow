<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Contracts\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Test-only endpoints used by the P1-01 isolation suite to assert
 * that the BelongsToTenant scope and the SetTenantContext middleware
 * behave correctly end-to-end through the HTTP layer. Registered
 * only when APP_ENV !== "production" (see routes/web.php).
 *
 * NOT a public API. The /_testing/tenant/* namespace is reserved
 * for tests and the local dev shell.
 *
 * Uses the DB facade (not Eloquent models) for cross-module reads
 * so this controller does not import Catalog models. The query is
 * manually scoped by organization_id; the table.column name comes
 * from the migration schema. This is a documented test-only raw SQL
 * exception, permitted by ENGINEERING_STANDARDS §2.8.
 */
class TenantTestController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function ping(): JsonResponse
    {
        $current = $this->context->current();

        return response()->json([
            'organization_id' => $current?->organizationId,
            'property_id' => $current?->propertyId,
        ]);
    }

    public function properties(): JsonResponse
    {
        $orgId = $this->context->current()?->organizationId;
        $rows = is_int($orgId)
            ? DB::table('properties')
                ->where('organization_id', $orgId)
                ->get(['id', 'organization_id', 'name', 'slug'])
                ->map(fn ($r): array => (array) $r)
                ->all()
            : [];

        return response()->json(['properties' => $rows]);
    }

    public function memberships(): JsonResponse
    {
        $orgId = $this->context->current()?->organizationId;
        $rows = is_int($orgId)
            ? DB::table('memberships')
                ->where('organization_id', $orgId)
                ->get(['id', 'organization_id', 'property_id', 'role'])
                ->map(fn ($r): array => (array) $r)
                ->all()
            : [];

        return response()->json(['memberships' => $rows]);
    }
}
