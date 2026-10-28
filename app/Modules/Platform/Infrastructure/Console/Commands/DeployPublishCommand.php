<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

use Throwable;

/**
 * Publishes a release and rolls it back when it does not survive the
 * health probe.
 *
 * This is the command the deployment pipeline runs after it uploaded the
 * build to the server.
 */
final class DeployPublishCommand extends DeploymentCommand
{
    protected $signature = 'deploy:publish
        {--root= : The server root (default: config("deploy.root"))}
        {--source= : The directory holding the build to publish}
        {--release= : The identifier of the release (default: a timestamp)}
        {--health-url= : The health endpoint to probe (default: config("deploy.health_url"))}
        {--health-timeout= : How long the probe may take, in seconds}
        {--skip-dependencies : Do not run composer install}
        {--skip-migrations : Do not run the migrations}
        {--skip-optimize : Do not warm the caches}
        {--no-queue-restart : Do not ask the queue workers to reload}';

    protected $description = 'Publish a release and roll it back automatically when the health probe fails';

    public function handle(): int
    {
        $source = (string) $this->option('source');

        if ($source === '') {
            $this->error('The --source option is required: it is the directory holding the build.');

            return self::FAILURE;
        }

        $healthUrl = $this->option('health-url');
        $healthUrl = $healthUrl !== null && $healthUrl !== ''
            ? (string) $healthUrl
            : config('deploy.health_url');

        $timeout = (int) ($this->option('health-timeout') ?: config('deploy.health_timeout'));

        try {
            $outcome = $this->publisher()->publish(
                source: $source,
                releaseId: $this->option('release') !== null ? (string) $this->option('release') : null,
                healthUrl: $healthUrl !== null ? (string) $healthUrl : null,
                installDependencies: ! $this->option('skip-dependencies'),
                migrate: ! $this->option('skip-migrations'),
                optimize: ! $this->option('skip-optimize'),
                restartQueue: ! $this->option('no-queue-restart'),
            );
        } catch (Throwable $e) {
            $this->error('The release could not be prepared: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($outcome->healthy) {
            $this->info('Published '.$outcome->releasePath.' — the health probe passed.');

            return self::SUCCESS;
        }

        $this->error('The health probe failed for '.$outcome->releasePath.'.');

        if ($outcome->rolledBack && $outcome->restoredPath !== null) {
            $this->warn('Rolled back to '.$outcome->restoredPath.'.');

            return self::FAILURE;
        }

        $this->error('There is no previous release to roll back to: the server has no known good release.');

        return self::FAILURE;
    }
}
