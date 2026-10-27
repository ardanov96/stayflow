<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\IdempotencyConflictException;
use Tests\Concurrency\Support\ConcurrencyConnection;
use Tests\Concurrency\Support\WorkerPool;

/**
 * The idempotency key is claimed with an INSERT, so a race on the key is
 * decided by the unique index and never by a gap lock.
 */
it('returns the same hold to every caller that replays the same idempotency key', function (): void {
    $roomType = $this->createRoomType(totalUnits: 5);

    $workers = ConcurrencyConnection::workerCount();

    $jobs = [];

    for ($i = 0; $i < $workers; $i++) {
        $jobs[] = $this->job('same-key-'.$i, [
            'type' => 'hold',
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'units' => 2,
            'idempotency_key' => 'shared-key',
            'barrier' => $this->barrier('same-key', $workers),
        ]);
    }

    $results = (new WorkerPool)->run($jobs);

    $holdIds = [];

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());

        $operations = $result->operations();

        expect($operations)->toHaveCount(1);
        expect($operations[0]['status'])->toBe('ok', $result->describe());

        /** @var array<string, mixed> $value */
        $value = $operations[0]['value'];
        $holdIds[] = (int) $value['id'];
    }

    // Every caller got the very same hold.
    expect(array_values(array_unique($holdIds)))->toHaveCount(1);

    // The units were taken once, not once per caller.
    $counters = $this->counters($roomType, $this->checkIn);

    expect($counters['held'])->toBe(2)
        ->and($counters['sold'])->toBe(0);

    expect($this->holdCounts())->toBe(['active' => 1]);
});

it('rejects a concurrent replay of the same key with a different payload', function (): void {
    $roomType = $this->createRoomType(totalUnits: 5);

    $workers = ConcurrencyConnection::workerCount();

    $jobs = [];

    for ($i = 0; $i < $workers; $i++) {
        $jobs[] = $this->job('mixed-key-'.$i, [
            'type' => 'hold',
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'units' => $i % 2 === 0 ? 2 : 3,
            'idempotency_key' => 'mixed-key',
            'barrier' => $this->barrier('mixed-key', $workers),
        ]);
    }

    $results = (new WorkerPool)->run($jobs);

    $successes = 0;
    $conflicts = 0;
    $winnerUnits = null;

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());

        $operation = $result->operations()[0];

        if ($operation['status'] === 'ok') {
            $successes++;

            /** @var array<string, mixed> $value */
            $value = $operation['value'];
            $winnerUnits = (int) $value['units'];

            continue;
        }

        expect((string) ($operation['error']['class'] ?? ''))
            ->toBe(IdempotencyConflictException::class, $result->describe());

        $conflicts++;
    }

    expect($successes + $conflicts)->toBe($workers)
        ->and($successes)->toBeGreaterThan(0);

    // One hold, and the counters match the payload that won the race.
    expect($this->holdCounts())->toBe(['active' => 1]);

    $counters = $this->counters($roomType, $this->checkIn);

    expect($counters['held'])->toBe($winnerUnits);
});
