<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure;

use App\Models\User;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\Identity\Ports\AuthMailer;
use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Application\Identity\Ports\RoleChecker;
use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Application\OrganizationContextManager;
use App\Modules\Platform\Cache\TenantCache;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Permission;
use App\Modules\Platform\Infrastructure\AuditLog\EloquentAuditor;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployPruneCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployPublishCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployRollbackCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployStatusCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployVerifyCommand;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentLoginAttemptRecorder;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentUserIdentityGateway;
use App\Modules\Platform\Infrastructure\Identity\Adapters\Google2faTotpGenerator;
use App\Modules\Platform\Infrastructure\Identity\Adapters\LaravelAuthMailer;
use App\Modules\Platform\Infrastructure\Identity\EloquentRoleChecker;
use Illuminate\Support\Facades\Gate;
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

        // P1-04 (Audit log): the Auditor port is the seam every other
        // module writes through. The adapter pulls the active
        // OrganizationContext (already a singleton) and the current
        // Auth user, then persists the row in the same transaction as
        // the source change.
        $this->app->singleton(Auditor::class, EloquentAuditor::class);

        // P1-03 (RBAC): the RoleChecker port is the seam every gate,
        // policy, and middleware asks "can this user do X?". The
        // adapter is Eloquent-backed and stateless; binding it as a
        // singleton just means the same instance is reused within a
        // request (it does not cache anything).
        $this->app->singleton(RoleChecker::class, EloquentRoleChecker::class);
    }

    public function boot(): void
    {
        // The Platform HTTP routes (the health endpoint used by the
        // deployment pipeline). Loaded here so the module owns its own
        // routes, like the other modules do.
        $routesPath = __DIR__.'/../Http/routes.php';

        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        // Wire Laravel Gate to RoleChecker for all known Permission cases
        Gate::before(function ($user, string $ability, array $arguments = []): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            $permission = Permission::tryFrom($ability);
            if ($permission === null) {
                return null;
            }

            $context = $this->app->make(OrganizationContext::class)->current();
            if ($context === null) {
                return false;
            }

            $propertyId = $context->propertyId;
            if (isset($arguments[0]) && is_int($arguments[0])) {
                $propertyId = $arguments[0];
            }

            return $this->app->make(RoleChecker::class)
                ->hasPermission($user, $context->organizationId, $propertyId, $permission);
        });
    }
}
