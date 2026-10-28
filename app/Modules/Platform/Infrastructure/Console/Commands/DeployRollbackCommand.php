<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

/**
 * Points the server back at the release that was serving before.
 */
final class DeployRollbackCommand extends DeploymentCommand
{
    protected $signature = 'deploy:rollback
        {--root= : The server root (default: config("deploy.root"))}';

    protected $description = 'Point the server back at the previous release';

    public function handle(): int
    {
        $restored = $this->publisher()->rollback();

        if ($restored === null) {
            $this->error('There is no previous release to roll back to.');

            return self::FAILURE;
        }

        $this->info('Rolled back to '.$restored.'.');

        return self::SUCCESS;
    }
}
