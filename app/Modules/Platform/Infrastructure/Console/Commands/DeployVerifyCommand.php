<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

use App\Modules\Platform\Infrastructure\Deployment\HealthProbe;

/**
 * Probes the health endpoint of the running application.
 */
final class DeployVerifyCommand extends DeploymentCommand
{
    protected $signature = 'deploy:verify
        {--root= : The server root (default: config("deploy.root"))}
        {--url= : The health endpoint (default: config("deploy.health_url"))}
        {--timeout= : How long the probe may take, in seconds}';

    protected $description = 'Probe the health endpoint of the running application';

    public function handle(): int
    {
        $url = $this->option('url');
        $url = $url !== null && $url !== '' ? (string) $url : config('deploy.health_url');

        if ($url === null || $url === '') {
            $this->error('No health endpoint is configured (--url or DEPLOY_HEALTH_URL).');

            return self::FAILURE;
        }

        $timeout = (int) ($this->option('timeout') ?: config('deploy.health_timeout'));
        $probe = new HealthProbe($timeout);

        if (! $probe->waitUntilHealthy((string) $url)) {
            $this->error('The health endpoint '.$url.' did not become healthy.');

            return self::FAILURE;
        }

        $this->info('The health endpoint '.$url.' is healthy.');

        return self::SUCCESS;
    }
}
