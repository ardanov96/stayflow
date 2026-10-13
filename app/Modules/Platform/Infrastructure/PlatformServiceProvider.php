<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure;

use App\Modules\Platform\Application\OrganizationContextManager;
use App\Modules\Platform\Contracts\OrganizationContext;
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
    }
}
