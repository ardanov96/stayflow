<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

use App\Modules\Platform\Infrastructure\Deployment\DeployPublisher;
use App\Modules\Platform\Infrastructure\Deployment\HealthProbe;
use App\Modules\Platform\Infrastructure\Deployment\ReleaseManager;
use App\Modules\Platform\Infrastructure\Deployment\ReleasePointer;
use Illuminate\Console\Command;

/**
 * Shared wiring of the deployment commands.
 *
 * The server root comes from the `--root` option and falls back to
 * `config('deploy.root')`, so the same command works on a server and in
 * a test without any environment-specific code.
 */
abstract class DeploymentCommand extends Command
{
    protected function root(): string
    {
        $root = $this->option('root');

        return (string) ($root !== null && $root !== '' ? $root : config('deploy.root'));
    }

    protected function manager(): ReleaseManager
    {
        return new ReleaseManager($this->root(), (string) config('deploy.composer'));
    }

    protected function pointer(): ReleasePointer
    {
        return new ReleasePointer($this->root());
    }

    protected function probe(): HealthProbe
    {
        return new HealthProbe(
            timeoutSeconds: (int) config('deploy.health_timeout'),
            intervalMs: (int) config('deploy.health_interval'),
        );
    }

    protected function publisher(): DeployPublisher
    {
        return new DeployPublisher($this->manager(), $this->pointer(), $this->probe());
    }
}
