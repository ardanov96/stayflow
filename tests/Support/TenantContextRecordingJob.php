<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Test job that records the OrganizationContext it observed in
 * handle() into a static property. The test reads the static
 * property after dispatch to assert the context was propagated.
 *
 * A static property is the only practical way to ferry a value
 * across the queue boundary in a test: a per-instance stdClass
 * sentinel is serialized with the job, so by the time handle()
 * runs the test reference and the job reference are distinct
 * objects. The static class property is shared across both.
 */
class TenantContextRecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public static ?array $lastObserved = null;

    public function __construct(public TenantContext $context) {}

    public function handle(): void
    {
        $current = app(OrganizationContext::class)->current();
        self::$lastObserved = [
            "org_id" => $current?->organizationId,
            "property_id" => $current?->propertyId,
        ];
    }

    public function middleware(): array
    {
        return [new PropagatesTenantContext($this->context)];
    }
}