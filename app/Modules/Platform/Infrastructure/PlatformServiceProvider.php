<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure;

use App\Modules\Platform\Application\Identity\Ports\AuthMailer;
use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Application\OrganizationContextManager;
use App\Modules\Platform\Cache\TenantCache;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentLoginAttemptRecorder;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentUserIdentityGateway;
use App\Modules\Platform\Infrastructure\Identity\Adapters\Google2faTotpGenerator;
use App\Modules\Platform\Infrastructure\Identity\Adapters\LaravelAuthMailer;
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

        // P1-02 (Identity) ports -> Infrastructure adapters. The
        // action classes are auto-wired (constructor injection);
        // binding these ports makes sure requests/console jobs get
        // the same adapter for the lifetime of the container.
        $this->app->singleton(UserIdentityGateway::class, EloquentUserIdentityGateway::class);
        $this->app->singleton(LoginAttemptRecorder::class, EloquentLoginAttemptRecorder::class);
        $this->app->singleton(TotpGenerator::class, Google2faTotpGenerator::class);
        $this->app->singleton(AuthMailer::class, LaravelAuthMailer::class);
    }
}
