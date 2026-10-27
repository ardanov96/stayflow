<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Support;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Recognises the database errors that mean "somebody else is holding the
 * inventory locks right now".
 *
 * Pure inspection of the exception: no database access, fully unit
 * testable without a MySQL server.
 */
final class ContentionDetector
{
    /**
     * MySQL error codes (driver level).
     */
    private const MYSQL_DEADLOCK = 1213;

    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;

    /**
     * SQLSTATE for a serialization failure (deadlock in InnoDB).
     */
    private const SQLSTATE_SERIALIZATION_FAILURE = '40001';

    /**
     * Name of the unique index that guards the idempotency key.
     */
    private const IDEMPOTENCY_INDEX = 'inventory_holds_org_idempotency_unique';

    public static function isDeadlock(Throwable $e): bool
    {
        if ($e instanceof DeadlockException) {
            return true;
        }

        if (! $e instanceof QueryException) {
            return false;
        }

        if ((string) $e->getCode() === self::SQLSTATE_SERIALIZATION_FAILURE) {
            return true;
        }

        return self::driverCode($e) === self::MYSQL_DEADLOCK;
    }

    public static function isLockWaitTimeout(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        return self::driverCode($e) === self::MYSQL_LOCK_WAIT_TIMEOUT;
    }

    /**
     * Whether the failure is the unique index on the idempotency key:
     * another request committed the same key first.
     */
    public static function isDuplicateIdempotencyKey(Throwable $e): bool
    {
        if (! $e instanceof UniqueConstraintViolationException) {
            return false;
        }

        $message = $e->getMessage();

        return str_contains($message, self::IDEMPOTENCY_INDEX)
            || str_contains($message, 'idempotency_key');
    }

    /**
     * Whether the transaction may be retried as-is.
     */
    public static function isRetryable(Throwable $e): bool
    {
        return self::isDeadlock($e)
            || self::isLockWaitTimeout($e)
            || self::isDuplicateIdempotencyKey($e);
    }

    private static function driverCode(QueryException $e): ?int
    {
        $errorInfo = $e->errorInfo ?? null;

        if (! is_array($errorInfo) || ! isset($errorInfo[1])) {
            return null;
        }

        return (int) $errorInfo[1];
    }
}
