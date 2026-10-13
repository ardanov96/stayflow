<?php

declare(strict_types=1);

namespace App\Modules\Platform\Jobs\Middleware;

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use Closure;

/**
 * Job middleware that propagates the active OrganizationContext
 * from the dispatch site to the worker.
 *
 * Without this middleware, any tenant-scoped query inside the job
 * would short-circuit to zero rows (the BelongsToTenant scope
 * returns 0 rows when no context is active). With it, the worker
 * sees the same context as the dispatcher.
 *
 * Usage on a job:
 *   public function middleware(): array
 *   {
 *       return [new PropagatesTenantContext()]; // capture current
 *       // or: [new PropagatesTenantContext($this->context)]; // explicit
 *   }
 *
 * If no context is active at dispatch time and the job does not pass
 * an explicit context, the worker runs with no context. This is the
 * right default for system jobs (retention cleanup, nightly billing,
 * ARI pushes that intentionally span tenants).
 */
final class PropagatesTenantContext
{
    public function __construct(public readonly ?TenantContext $context = null) {}

    public function handle(object $job, Closure $next): mixed
    {
        $orgContext = app(OrganizationContext::class);
        $context = $this->context ?? $orgContext->current();

        if ($context === null) {
            return $orgContext->without(fn (): mixed => $next($job));
        }

        return $orgContext->swap($context, fn (): mixed => $next($job));
    }
}
