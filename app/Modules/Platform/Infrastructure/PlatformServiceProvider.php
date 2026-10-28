<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure;

use App\Models\User;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\Billing\ProcessBillingEvent;
use App\Modules\Platform\Application\Identity\Ports\AuthMailer;
use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Application\Identity\Ports\RoleChecker;
use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Application\Notifications\NotificationDispatcher;
use App\Modules\Platform\Application\OrganizationContextManager;
use App\Modules\Platform\Cache\TenantCache;
use App\Modules\Platform\Contracts\MediaStorage;
use App\Modules\Platform\Contracts\NotificationDeliveryLog;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Contracts\SubscriptionRepository;
use App\Modules\Platform\Domain\Identity\Permission;
use App\Modules\Platform\Infrastructure\AuditLog\EloquentAuditor;
use App\Modules\Platform\Infrastructure\Billing\BillingGatewaySelector;
use App\Modules\Platform\Infrastructure\Billing\MidtransBillingGateway;
use App\Modules\Platform\Infrastructure\Billing\XenditBillingGateway;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployPruneCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployPublishCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployRollbackCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployStatusCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\DeployVerifyCommand;
use App\Modules\Platform\Infrastructure\Console\Commands\GenerateErdCommand;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentLoginAttemptRecorder;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentUserIdentityGateway;
use App\Modules\Platform\Infrastructure\Identity\Adapters\Google2faTotpGenerator;
use App\Modules\Platform\Infrastructure\Identity\Adapters\LaravelAuthMailer;
use App\Modules\Platform\Infrastructure\Identity\EloquentRoleChecker;
use App\Modules\Platform\Infrastructure\Media\FilesystemMediaStorage;
use App\Modules\Platform\Infrastructure\Notifications\EloquentNotificationDeliveryLog;
use App\Modules\Platform\Infrastructure\Notifications\MailNotificationChannel;
use App\Modules\Platform\Infrastructure\Subscriptions\EloquentSubscriptionRepository;
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

        // P1-15 (Notifications): the delivery log, and the dispatcher
        // with the channels that are implemented. WhatsApp is not one of
        // them yet; it will be another NotificationChannel, and nothing
        // else has to change.
        $this->app->singleton(NotificationDeliveryLog::class, EloquentNotificationDeliveryLog::class);

        // P1-14 (Media storage): the private files and the public files
        // live on two different disks, and only the public ones have a
        // public URL.
        $this->app->singleton(MediaStorage::class, fn (): FilesystemMediaStorage => new FilesystemMediaStorage(
            privateDisk: (string) config('media.private_disk'),
            publicDisk: (string) config('media.public_disk'),
            publicUrl: (string) config('media.public_url'),
            privateDiskTemporaryUrls: (bool) config('media.private_disk_temporary_urls'),
        ));

        // P1-11 (SaaS subscription and plan gating): the local
        // subscription is the single source of truth (ADR-0013), and the
        // organization chooses its gateway.
        $this->app->singleton(SubscriptionRepository::class, EloquentSubscriptionRepository::class);

        $this->app->singleton(BillingGatewaySelector::class, fn (): BillingGatewaySelector => new BillingGatewaySelector([
            'xendit' => new XenditBillingGateway,
            'midtrans' => new MidtransBillingGateway,
        ]));

        $this->app->singleton(ProcessBillingEvent::class, fn ($app): ProcessBillingEvent => new ProcessBillingEvent(
            subscriptions: $app->make(SubscriptionRepository::class),
            gateways: $app->make(BillingGatewaySelector::class),
        ));

        $this->app->singleton(NotificationDispatcher::class, function ($app): NotificationDispatcher {
            return new NotificationDispatcher(
                channels: [
                    $app->make(MailNotificationChannel::class),
                ],
                log: $app->make(NotificationDeliveryLog::class),
            );
        });
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

        // The deployment pipeline (P0-06): publish a release, probe the
        // health endpoint, roll back automatically when the release does
        // not survive the probe.
        $this->commands([
            DeployPublishCommand::class,
            DeployRollbackCommand::class,
            DeployVerifyCommand::class,
            DeployStatusCommand::class,
            DeployPruneCommand::class,
            GenerateErdCommand::class,
        ]);

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
