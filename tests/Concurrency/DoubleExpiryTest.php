<?php

declare(strict_types=1);

use Tests\Concurrency\Support\ConcurrencyConnection;
use Tests\Concurrency\Support\WorkerPool;

/**
 * docs/ARCHITECTURE.md §5.5 — "Hold expiry returns units exactly once,
 * even if the expiry job runs twice concurrently".
 */
it('returns the units exactly once when the expiry job runs twice concurrently', function (): void {
    $roomType = $this->createRoomType(totalUnits: 3);

    $this->context->swap($this->tenant, fn () => $this->inventoryService->hold(
        propertyId: $this->property->id,
        roomTypeId: $roomType->id,
        checkIn: $this->checkIn,
        checkOut: $this->checkOut,
        units: 2,
        idempotencyKey: 'expiring-hold',
        ttlSeconds: 1,
    ));

    // Let the time-to-live pass.
    sleep(2);

    $workers = ConcurrencyConnection::workerCount();

    $jobs = [];

    for ($i = 0; $i < $workers; $i++) {
        $jobs[] = $this->job('expire-'.$i, [
            'type' => 'expire',
            'organization_id' => $this->organization->id,
            'limit' => 100,
            'barrier' => $this->barrier('expiry', $workers),
        ]);
    }

    $results = (new WorkerPool)->run($jobs);

    $expired = 0;

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());

        $operation = $result->operations()[0];

        expect($operation['status'])->toBe('ok', $result->describe());

        $expired += (int) $operation['value'];
    }

    // Exactly one of the concurrent expiry runs did the work.
    expect($expired)->toBe(1);

    $counters = $this->counters($roomType, $this->checkIn);

    expect($counters['held'])->toBe(0)
        ->and($counters['sold'])->toBe(0);

    expect($this->holdCounts())->toBe(['expired' => 1]);
});

it('never gives the same units back twice when a hold is released and expired at the same time', function (): void {
    $roomType = $this->createRoomType(totalUnits: 2);

    $hold = $this->context->swap($this->tenant, fn () => $this->inventoryService->hold(
        propertyId: $this->property->id,
        roomTypeId: $roomType->id,
        checkIn: $this->checkIn,
        checkOut: $this->checkOut,
        units: 1,
        idempotencyKey: 'release-or-expire',
        ttlSeconds: 1,
    ));

    sleep(2);

    $workers = ConcurrencyConnection::workerCount();

    $jobs = [];

    for ($i = 0; $i < $workers; $i++) {
        $jobs[] = $this->job('mixed-'.$i, [
            'type' => $i % 2 === 0 ? 'expire' : 'release',
            'organization_id' => $this->organization->id,
            'limit' => 100,
            'hold_id' => $hold->id,
            'reason' => 'concurrent-release',
            'barrier' => $this->barrier('release-or-expire', $workers),
        ]);
    }

    $results = (new WorkerPool)->run($jobs);

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());
    }

    // The units came back exactly once, whatever won the race.
    $counters = $this->counters($roomType, $this->checkIn);

    expect($counters['held'])->toBe(0)
        ->and($counters['sold'])->toBe(0);

    $statuses = $this->holdCounts();

    expect($statuses['expired'] ?? 0)->toBeLessThanOrEqual(1);
    expect(($statuses['expired'] ?? 0) + ($statuses['released'] ?? 0))->toBe(1);
});
