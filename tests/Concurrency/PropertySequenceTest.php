<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\HoldStateException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientInventoryException;
use Tests\Concurrency\Support\ConcurrencyConnection;
use Tests\Concurrency\Support\WorkerPool;

/**
 * docs/ARCHITECTURE.md §5.5 — "Property-based test: random sequences of
 * hold/commit/release/cancel keep the invariant".
 *
 * The invariant is the one the availability engine promises:
 *
 *     held_units + sold_units + blocked_units <= total_units
 *
 * and, more precisely, the counters of every day are exactly the sum of
 * the holds that cover that day. The `cancel` operation (giving back the
 * **sold** units of a confirmed reservation) belongs to the Reservations
 * module (P2-01, see docs/INVENTORY.md §6.7), so the sequence covers
 * hold/commit/release; the invariant check is written so that `cancel`
 * joins the sequence as soon as it exists.
 */
it('keeps the availability invariant through a random sequence of holds, commits and releases', function (): void {
    // A fixed seed: a failure is reproducible.
    mt_srand(20261027);

    $roomTypes = [];

    for ($i = 0; $i < 4; $i++) {
        $roomTypes[] = $this->createRoomType(totalUnits: 4);
    }

    $dates = [];

    for ($i = 0; $i < 8; $i++) {
        $dates[] = now()->addDay()->addDays($i)->format('Y-m-d');
    }

    $workers = ConcurrencyConnection::workerCount();
    $steps = 8;

    $jobs = [];

    for ($worker = 0; $worker < $workers; $worker++) {
        $operations = [];
        $ownHolds = 0;

        for ($step = 0; $step < $steps; $step++) {
            $roll = mt_rand(1, 100);

            if ($roll <= 60 || $ownHolds === 0) {
                $roomType = $roomTypes[mt_rand(0, count($roomTypes) - 1)];
                $start = mt_rand(0, count($dates) - 2);
                $length = mt_rand(1, min(3, count($dates) - 1 - $start));

                $operations[] = [
                    'type' => 'hold',
                    'property_id' => $this->property->id,
                    'room_type_id' => $roomType->id,
                    'check_in' => $dates[$start],
                    'check_out' => $dates[$start + $length],
                    'units' => mt_rand(1, 2),
                    'idempotency_key' => 'property-'.$worker.'-'.$step,
                    'barrier' => $this->barrier('property-step-'.$step, $workers),
                ];

                $ownHolds++;

                continue;
            }

            $operations[] = [
                'type' => $roll <= 80 ? 'commit' : 'release',
                'hold_ref' => mt_rand(0, $ownHolds - 1),
                'reservation_id' => 1000 + $worker,
                'reason' => 'property-release',
                'barrier' => $this->barrier('property-step-'.$step, $workers),
            ];
        }

        $jobs[] = $this->job('property-'.$worker, operations: $operations);
    }

    $results = (new WorkerPool)->run($jobs);

    foreach ($results as $result) {
        expect($result->ok)->toBeTrue($result->describe());

        foreach ($result->failedOperationClasses() as $class) {
            // A rejected hold and an illegal transition are legitimate
            // outcomes of a random sequence; a deadlock is not.
            expect($class)->toBeIn([
                InsufficientInventoryException::class,
                HoldStateException::class,
            ], $result->describe().' '.json_encode($result->failedOperations()));
        }
    }

    $expected = $this->expectedCounters();

    foreach ($this->allCounters() as $roomTypeId => $days) {
        foreach ($days as $date => $counters) {
            expect($counters['held'])->toBeGreaterThanOrEqual(0);
            expect($counters['sold'])->toBeGreaterThanOrEqual(0);

            expect($counters['held'] + $counters['sold'] + $counters['blocked'])
                ->toBeLessThanOrEqual($counters['total']);

            expect($counters['held'])->toBe($expected[$roomTypeId][$date]['held'] ?? 0);
            expect($counters['sold'])->toBe($expected[$roomTypeId][$date]['sold'] ?? 0);
        }
    }
});
