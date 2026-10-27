<?php

declare(strict_types=1);

namespace Tests\Concurrency\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * Prepares the schema of the dedicated concurrency database.
 *
 * The suite owns its database, so it can rebuild the schema from the real
 * migrations instead of relying on a fixture dump: the concurrency
 * behaviour (CHECK constraints, unique indexes, InnoDB row locks) comes
 * from the same migrations the application ships.
 */
final class ConcurrencySchema
{
    private static bool $prepared = false;

    public static function prepare(): void
    {
        if (self::$prepared) {
            return;
        }

        Artisan::call('migrate:fresh', [
            '--database' => ConcurrencyConnection::NAME,
            '--force' => true,
        ]);

        Barrier::ensureTable();

        self::$prepared = true;
    }

    /**
     * Whether the schema was already built in this process.
     */
    public static function isPrepared(): bool
    {
        return self::$prepared;
    }
}
