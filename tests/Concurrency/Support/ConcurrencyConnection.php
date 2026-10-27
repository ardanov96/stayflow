<?php

declare(strict_types=1);

namespace Tests\Concurrency\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Access to the dedicated MySQL 8 connection used by the concurrency suite.
 *
 * The suite (task P1-10) needs a real MySQL server: SQLite serializes the
 * writes and would hide every race the suite exists to catch. The server is
 * opt-in, so a machine without MySQL simply skips the suite instead of
 * failing the build.
 *
 * The `CONCURRENCY_TESTS` environment variable decides the behaviour:
 *
 *  - `auto`  (default) run the suite when the server answers, skip otherwise
 *  - `1`     the suite is required: a missing server fails the build
 *  - `0`     the suite is disabled
 */
final class ConcurrencyConnection
{
    public const NAME = 'concurrency';

    public const MODE_AUTO = 'auto';

    public const MODE_REQUIRED = 'required';

    public const MODE_DISABLED = 'disabled';

    /**
     * The connection configuration, with every value resolved.
     *
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $config = config('database.connections.'.self::NAME);

        if (! is_array($config)) {
            throw new RuntimeException('The "concurrency" database connection is not configured.');
        }

        return $config;
    }

    /**
     * The resolved `CONCURRENCY_TESTS` mode.
     */
    public static function mode(): string
    {
        $mode = strtolower(trim((string) env('CONCURRENCY_TESTS', self::MODE_AUTO)));

        return match ($mode) {
            '0', 'false', 'off', 'no' => self::MODE_DISABLED,
            '1', 'true', 'on', 'yes', 'required' => self::MODE_REQUIRED,
            default => self::MODE_AUTO,
        };
    }

    public static function isDisabled(): bool
    {
        return self::mode() === self::MODE_DISABLED;
    }

    public static function isRequired(): bool
    {
        return self::mode() === self::MODE_REQUIRED;
    }

    /**
     * Whether the server answers. Never throws: a missing server is a
     * skip, not an error, unless the suite is explicitly required.
     */
    public static function isReachable(): bool
    {
        $config = self::config();

        // A `url` connection carries everything the DSN needs; let the
        // framework build it.
        if (! empty($config['url'])) {
            try {
                DB::connection(self::NAME)->getPdo();

                return true;
            } catch (Throwable) {
                return false;
            }
        }

        $socket = (string) ($config['unix_socket'] ?? '');

        $dsn = $socket !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s', $socket, (string) $config['database'])
            : sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                (string) $config['host'],
                (string) $config['port'],
                (string) $config['database'],
            );

        try {
            // A short connect timeout: the probe must not hang when the
            // server is not there.
            new PDO(
                $dsn,
                (string) $config['username'],
                (string) $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ],
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The number of worker processes the scenarios may use.
     */
    public static function workerCount(int $default = 24): int
    {
        $configured = (int) env('CONCURRENCY_WORKERS', $default);

        return max(1, $configured);
    }
}
