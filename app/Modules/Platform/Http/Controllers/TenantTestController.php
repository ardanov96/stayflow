<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Test-only endpoints used by the P1-01 isolation suite to assert
 * that the BelongsToTenant scope and the SetTenantContext middleware
 * behave correctly end-to-end through the HTTP layer. Registered
 * only when APP_ENV !== "production" (see routes/web.php).
 *
 * NOT a public API. The /_testing/tenant/* namespace is reserved
 * for tests and the local dev shell.
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
        return response()->json([
            'properties' => Property::query()
                ->get(['id', 'organization_id', 'name', 'slug'])
                ->toArray(),
        ]);
    }

    public function memberships(): JsonResponse
    {
        return response()->json([
            'memberships' => Membership::query()
                ->get(['id', 'organization_id', 'property_id', 'role'])
                ->toArray(),
        ]);
    }
}
