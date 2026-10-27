<?php

declare(strict_types=1);

namespace Tests\Concurrency\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A barrier shared by the worker processes.
 *
 * The workers are independent PHP processes, so the rendezvous point has to
 * live in the database. Every worker inserts its own arrival row and then
 * waits until the expected number of arrivals is visible.
 *
 * This is what makes the scenarios real: the workers do not merely run at
 * the same time, they are all released at the same instant, so they all
 * hit the same inventory row while it is still locked by the first one.
 */
final class Barrier
{
    public const TABLE = 'concurrency_barrier_arrivals';

    /**
     * Create the barrier table. It is not a migration: it belongs to the
     * test harness, not to the product schema.
     */
    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            $table->string('worker', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['name', 'worker'], 'concurrency_barrier_name_worker_unique');
        });
    }

    /**
     * Announce the arrival and block until every party is here.
     *
     * @return float The monotonic timestamp of the release.
     */
    public static function arrive(
        string $name,
        string $worker,
        int $parties,
        int $timeoutSeconds = 120,
    ): float {
        DB::table(self::TABLE)->insertOrIgnore([
            'name' => $name,
            'worker' => $worker,
            'created_at' => now(),
        ]);

        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $arrived = DB::table(self::TABLE)->where('name', $name)->count();

            if ($arrived >= $parties) {
                return microtime(true);
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException(sprintf(
                    'Barrier "%s" timed out after %d seconds: %d of %d workers arrived.',
                    $name,
                    $timeoutSeconds,
                    $arrived,
                    $parties,
                ));
            }

            usleep(1000);
        }
    }

    /**
     * Remove the arrivals of a barrier (or of every barrier).
     */
    public static function reset(?string $name = null): void
    {
        $query = DB::table(self::TABLE);

        if ($name === null) {
            $query->delete();

            return;
        }

        $query->where('name', $name)->delete();
    }
}
