<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Inventory\Domain\Exceptions\InventoryContentionException;
use Tests\Concurrency\Support\ConcurrencyConnection;
use Tests\Concurrency\Support\WorkerPool;

/**
 * docs/ARCHITECTURE.md §5.5 — "Overlapping ranges across many room types
 * → no deadlock reaches the caller; no negative availability".
 */
it('never surfaces a deadlock when the ranges of many room types overlap', function (): void {
    $roomTypes = [];

    for ($i = 0; $i < 6; $i++) {
        $roomTypes[] = $this->createRoomType(totalUnits: 5);
    }

    $dates = [];

    for ($i = 0; $i < 10; $i++) {
        $dates[] = now()->addDay()->addDays($i)->format('Y-m-d');
    }

    $workers = ConcurrencyConnection::workerCount();
    $steps = 5;

    $jobs = [];

    for ($worker = 0; $worker < $workers; $worker++) {
        $operations = [];

        for ($step = 0; $step < $steps; $step++) {
            $roomType = $roomTypes[($worker + $step) % count($roomTypes)];
            $start = ($worker * 3 + $step * 2) % 7;
            $length = 2 + (($worker + $step) % 4);
            $end = min($start + $length, count($dates) - 1);

            $operations[] = [
                'type' => 'hold',
                'property_id' => $this->property->id,
                'room_type_id' => $roomType->id,
                'check_in' => $dates[$start],
                'check_out' => $dates[$end],
                'units' => 1 + (($worker + $step) % 2),
                'idempotency_key' => 'overlap-'.$worker.'-'.$step,
                'barrier' => $this->barrier('overlap-step-'.$step, $workers),
            ];
        }

        $jobs[] = $this->job('overlap-'.$worker, operations: $operations);
    }

    $results = (new WorkerPool)->run($jobs);

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());

        foreach ($result->failedOperationClasses() as $class) {
            // Only "there is not enough inventory" may reach the caller.
            // A deadlock must be resolved by the retry, never surfaced.
            expect($class)->toBe(InsufficientInventoryException::class, $result->describe());
            expect($class)->not->toBe(InventoryContentionException::class);
        }
    }

    $expected = $this->expectedCounters();

    foreach ($this->allCounters() as $roomTypeId => $days) {
        foreach ($days as $date => $counters) {
            // No negative availability.
            expect($counters['held'])->toBeGreaterThanOrEqual(0);
            expect($counters['sold'])->toBeGreaterThanOrEqual(0);
            expect($counters['blocked'])->toBeGreaterThanOrEqual(0);

            // The unit budget is never exceeded.
            expect($counters['held'] + $counters['sold'] + $counters['blocked'])
                ->toBeLessThanOrEqual($counters['total']);

            // The counters are exactly the sum of the holds that cover the day.
            expect($counters['held'])->toBe($expected[$roomTypeId][$date]['held'] ?? 0);
            expect($counters['sold'])->toBe($expected[$roomTypeId][$date]['sold'] ?? 0);
        }
    }
});
