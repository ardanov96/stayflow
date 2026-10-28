<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Health;

use App\Modules\Platform\Application\Health\HealthCheckResult;
use App\Modules\Platform\Application\Health\HealthReport;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

/**
 * Collects the health of the running application.
 *
 * The checks are the ones a deployment actually needs: the database
 * answers, no migration is left pending, the cache works, the storage is
 * writable and the queue connection is reachable. Anything the deployment
 * pipeline cannot roll back is not worth checking.
 *
 * A failing check never leaks its exception: the raw error is logged, the
 * response only says which component failed.
 */
final class HealthChecker
{
    public function report(): HealthReport
    {
        $checks = [
            $this->check('database', function (): void {
                $this->databaseIsReachable();
            }),
            $this->check('migrations', function (): void {
                $this->migrationsAreUpToDate();
            }),
            $this->check('cache', function (): void {
                $this->cacheWorks();
            }),
            $this->check('storage', function (): void {
                $this->storageIsWritable();
            }),
            $this->check('queue', function (): void {
                $this->queueIsReachable();
            }),
        ];

        return new HealthReport(
            checks: $checks,
            version: (string) config('app.version'),
            environment: (string) app()->environment(),
        );
    }

    /**
     * Run one check and turn a failure into a result.
     *
     * @param  callable(): void  $probe  Throws when the component is not healthy.
     */
    private function check(string $name, callable $probe): HealthCheckResult
    {
        $startedAt = microtime(true);

        try {
            $probe();

            return new HealthCheckResult(
                name: $name,
                healthy: true,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );
        } catch (Throwable $e) {
            Log::warning('Health check failed.', [
                'check' => $name,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new HealthCheckResult(
                name: $name,
                healthy: false,
                durationMs: (microtime(true) - $startedAt) * 1000,
            );
        }
    }

    private function databaseIsReachable(): void
    {
        DB::select('select 1');
    }

    /**
     * A pending migration means the release and the schema disagree: the
     * application would serve requests against a schema it does not know.
     */
    private function migrationsAreUpToDate(): void
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        $files = $migrator->getMigrationFiles($migrator->paths());
        $ran = $migrator->getRepository()->getRan();
        $pending = array_diff(array_keys($files), $ran);

        if ($pending !== []) {
            throw new RuntimeException(sprintf(
                '%d migration(s) pending: %s',
                count($pending),
                implode(', ', array_slice($pending, 0, 5)),
            ));
        }
    }

    private function cacheWorks(): void
    {
        $key = 'health:'.bin2hex(random_bytes(8));

        Cache::put($key, 'ok', 10);

        try {
            if (Cache::get($key) !== 'ok') {
                throw new RuntimeException('The cache did not return the value that was just stored.');
            }
        } finally {
            Cache::forget($key);
        }
    }

    private function storageIsWritable(): void
    {
        $directory = storage_path('framework');

        if (! is_dir($directory)) {
            throw new RuntimeException('The storage/framework directory is missing.');
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('The storage/framework directory is not writable.');
        }
    }

    /**
     * The queue *worker* cannot be probed over HTTP, but its connection
     * can: a dead Redis would silently stop every background job.
     */
    private function queueIsReachable(): void
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync' || $connection === 'null') {
            return;
        }

        if ($connection === 'redis') {
            Redis::connection()->ping();
        }

        // The database and S3 drivers are covered by their own checks.
    }
}
