<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Support;

use App\Modules\Inventory\Domain\Exceptions\InventoryContentionException;
use Throwable;

/**
 * Runs a transactional unit of work and retries it when the database
 * reports lock contention (deadlock, lock-wait timeout) or when another
 * transaction won the race on the idempotency key.
 *
 * The retry is safe because the callback is idempotent by construction:
 * it locks the idempotency row first and the inventory rows afterwards,
 * in that fixed order.
 */
trait RetriesOnContention
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withContentionRetry(callable $callback, int $maxAttempts = 3): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $callback();
            } catch (Throwable $e) {
                if (! ContentionDetector::isRetryable($e)) {
                    throw $e;
                }

                if ($attempt >= $maxAttempts) {
                    if (ContentionDetector::isDuplicateIdempotencyKey($e)) {
                        // The caller decides what a lost idempotency race
                        // means (usually: return the winning hold).
                        throw $e;
                    }

                    throw InventoryContentionException::afterRetries($maxAttempts);
                }

                // Small growing backoff so the competing transaction can
                // finish and release its locks.
                usleep($attempt * 50_000);
            }
        }
    }
}
