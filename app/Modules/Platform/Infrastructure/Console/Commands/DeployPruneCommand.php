<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

/**
 * Removes the releases that are too old to be useful.
 *
 * The current release and the one before it (the rollback target) are
 * always kept.
 */
final class DeployPruneCommand extends DeploymentCommand
{
    protected $signature = 'deploy:prune
        {--root= : The server root (default: config("deploy.root"))}
        {--keep=5 : How many releases to keep}';

    protected $description = 'Remove the releases that are older than the ones to keep';

    public function handle(): int
    {
        $keep = max(2, (int) ($this->option('keep') ?: 5));
        $current = $this->pointer()->currentPath() ?? '';

        $removed = $this->manager()->prune($current, $keep);

        $this->info("Removed {$removed} old release(s); {$keep} are kept.");

        return self::SUCCESS;
    }
}
