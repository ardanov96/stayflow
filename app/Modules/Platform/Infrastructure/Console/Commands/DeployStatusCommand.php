<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

use App\Modules\Platform\Infrastructure\Deployment\HealthProbe;

/**
 * Shows what the server is serving.
 */
final class DeployStatusCommand extends DeploymentCommand
{
    protected $signature = 'deploy:status
        {--root= : The server root (default: config("deploy.root"))}
        {--url= : The health endpoint (default: config("deploy.health_url"))}';

    protected $description = 'Show the release the server is serving and the releases it can roll back to';

    public function handle(): int
    {
        $pointer = $this->pointer();
        $current = $pointer->currentPath();

        $this->line('Server root: '.$this->root());
        $this->line('Current release: '.($current ?? 'none'));

        $releases = $pointer->releases();
        $previous = null;

        foreach ($releases as $release) {
            if ($release !== $current) {
                $previous = $release;
                break;
            }
        }

        $this->line('Previous release: '.($previous ?? 'none'));
        $this->line('Releases on disk: '.count($releases));

        $url = $this->option('url');
        $url = $url !== null && $url !== '' ? (string) $url : config('deploy.health_url');

        if ($url === null || $url === '') {
            return self::SUCCESS;
        }

        $healthy = (new HealthProbe(5))->isHealthy((string) $url);

        $this->line('Health endpoint '.$url.': '.($healthy ? 'healthy' : 'unhealthy'));

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
