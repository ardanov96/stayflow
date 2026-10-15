<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure;

use App\Modules\Platform\Application\OrganizationContextManager;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Cache\TenantCache;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Infrastructure\AuditLog\EloquentAuditor;
use Illuminate\Support\ServiceProvider;

/**
 * Wires Platform's contracts to their concrete Application-layer
 * implementations. Kept thin: no business logic, only bindings.
 *
 * Registered in bootstrap/providers.php so it boots on every request,
 * console command, and queued job.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton so the same stack survives across the lifetime of
        // a request, a console run, or a queued job.
        $this->app->singleton(OrganizationContext::class, OrganizationContextManager::class);

        // TenantCache is auto-wired (CacheRepository + OrganizationContext
        // are both bound). Bound explicitly as a singleton so callers
        // that resolve via app(TenantCache::class) get the same instance
        // for the lifetime of the request.
        $this->app->singleton(TenantCache::class);

        // P1-04 (Audit log): the Auditor port is the seam every other
        // module writes through. The adapter pulls the active
        // OrganizationContext (already a singleton) and the current
        // Auth user, then persists the row in the same transaction as
        // the source change.
        $this->app->singleton(Auditor::class, EloquentAuditor::class);
    }
}
