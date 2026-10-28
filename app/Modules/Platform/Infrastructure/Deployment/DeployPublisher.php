<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Deployment;

/**
 * Publishes a release and keeps the server serving.
 *
 * The order is the whole point:
 *
 *   1. prepare the release (code, dependencies, migrations, caches)
 *   2. move the pointer to it
 *   3. probe the health endpoint
 *   4. when the probe fails, move the pointer back to the release that
 *      was serving before
 *
 * A release that fails to prepare never becomes the one that serves
 * traffic, and a release that serves badly is rolled back automatically.
 */
final class DeployPublisher
{
    public function __construct(
        private readonly ReleaseManager $manager,
        private readonly ReleasePointer $pointer,
        private readonly HealthProbe $probe,
    ) {}

    /**
     * @param  string|null  $healthUrl  When null, the release is published without a probe.
     */
    public function publish(
        string $source,
        ?string $releaseId = null,
        ?string $healthUrl = null,
        bool $installDependencies = true,
        bool $migrate = true,
        bool $optimize = true,
        bool $restartQueue = true,
    ): PublishOutcome {
        // Captured before the swap: this is the release the server goes
        // back to when the new one does not survive the probe.
        $previous = $this->pointer->currentPath();

        $release = $this->manager->createRelease($source, $releaseId);

        if ($installDependencies) {
            $this->manager->installDependencies($release);
        }

        if ($migrate) {
            $this->manager->runMigrations($release);
        }

        if ($optimize) {
            $this->manager->optimize($release);
        }

        $this->manager->linkEnvironment($release);

        $this->pointer->swap($release);

        if ($healthUrl === null) {
            return new PublishOutcome(
                releasePath: $release,
                healthy: true,
                rolledBack: false,
                restoredPath: null,
            );
        }

        $healthy = $this->probe->waitUntilHealthy($healthUrl);

        if ($healthy) {
            if ($restartQueue) {
                $this->manager->restartQueue($release);
            }

            return new PublishOutcome(
                releasePath: $release,
                healthy: true,
                rolledBack: false,
                restoredPath: null,
            );
        }

        if ($previous !== null) {
            $this->pointer->swap($previous);
        }

        return new PublishOutcome(
            releasePath: $release,
            healthy: false,
            rolledBack: $previous !== null,
            restoredPath: $previous,
        );
    }

    /**
     * Point the server back at the release that was serving before.
     */
    public function rollback(): ?string
    {
        $current = $this->pointer->currentPath();

        if ($current === null) {
            return null;
        }

        $releases = $this->pointer->releases();
        $previous = null;

        foreach ($releases as $release) {
            if ($release !== $current) {
                $previous = $release;
                break;
            }
        }

        if ($previous === null) {
            return null;
        }

        $this->pointer->swap($previous);

        return $previous;
    }
}
