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
 * it claims the idempotency key first (with an INSERT) and the inventory
 * rows afterwards, in that fixed order.
 *
 * A duplicate idempotency key is deliberately NOT retried here: the same
 * statement would fail the same way. The caller resolves the winner
 * instead (see `HoldInventoryAction`), so the violation is rethrown
 * immediately.
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
                // A lost idempotency race is not contention: the winner
                // is already committed and the same INSERT would fail
                // again. The caller resolves it.
                if (ContentionDetector::isDuplicateIdempotencyKey($e)) {
                    throw $e;
                }

                if (! ContentionDetector::isRetryable($e)) {
                    throw $e;
                }

                if ($attempt >= $maxAttempts) {
                    throw InventoryContentionException::afterRetries($maxAttempts);
                }

                // Small growing backoff so the competing transaction can
                // finish and release its locks.
                usleep($attempt * 50_000);
            }
        }
    }
}
