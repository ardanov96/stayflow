<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use Tests\Concurrency\Support\ConcurrencyConnection;
use Tests\Concurrency\Support\WorkerPool;

/**
 * docs/ARCHITECTURE.md §5.5 — "500 concurrent `hold()` calls for the last
 * unit → exactly 1 success".
 */
it('gives the last unit to exactly one of the concurrent hold calls', function (): void {
    $roomType = $this->createRoomType(totalUnits: 1);

    $attempts = (int) env('CONCURRENCY_ATTEMPTS', 500);
    $workers = min($attempts, ConcurrencyConnection::workerCount());

    $jobs = $this->waveJobs(
        attempts: $attempts,
        workers: $workers,
        operation: fn (int $index): array => [
            'type' => 'hold',
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'units' => 1,
            'idempotency_key' => 'last-unit-'.$index,
        ],
        prefix: 'last-unit',
    );

    $results = (new WorkerPool)->run($jobs);

    // Every worker must have reported: a worker that died would hide a
    // broken scenario behind a smaller number of calls.
    expect($results)->toHaveCount($workers);

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe().' | '.$result->stderr);
    }

    $successes = 0;
    $failures = [];

    foreach ($results as $result) {
        foreach ($result->operations() as $operation) {
            if ($operation['status'] === 'ok') {
                $successes++;

                continue;
            }

            $failures[] = (string) ($operation['error']['class'] ?? '');
        }
    }

    expect($successes)->toBe(1)
        ->and($failures)->toHaveCount($attempts - 1)
        ->and(array_unique($failures))->toBe([InsufficientInventoryException::class]);

    // The counters tell the same story: the unit is held exactly once.
    $counters = $this->counters($roomType, $this->checkIn);

    expect($counters['total'])->toBe(1)
        ->and($counters['held'])->toBe(1)
        ->and($counters['sold'])->toBe(0)
        ->and($counters['blocked'])->toBe(0);

    expect($this->holdCounts())->toBe(['active' => 1]);
});

it('never lets the available units go below zero under a concurrent burst', function (): void {
    $roomType = $this->createRoomType(totalUnits: 3);

    $attempts = (int) env('CONCURRENCY_ATTEMPTS', 500);
    $workers = min($attempts, ConcurrencyConnection::workerCount());

    $jobs = $this->waveJobs(
        attempts: $attempts,
        workers: $workers,
        operation: fn (int $index): array => [
            'type' => 'hold',
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'units' => 1,
            'idempotency_key' => 'burst-'.$index,
        ],
        prefix: 'burst',
    );

    $results = (new WorkerPool)->run($jobs);

    $successes = 0;

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());

        $successes += count($result->successfulOperations());
    }

    expect($successes)->toBe(3);

    $counters = $this->counters($roomType, $this->checkIn);

    expect($counters['held'])->toBe(3)
        ->and($counters['sold'])->toBe(0)
        ->and($counters['total'] - $counters['held'] - $counters['sold'] - $counters['blocked'])->toBe(0);
});
