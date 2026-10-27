<?php

declare(strict_types=1);

/**
 * Entry point of a concurrency worker process.
 *
 * The worker boots the application, points every query at the dedicated
 * MySQL 8 connection, and then runs the operation(s) it was given. An
 * operation may declare a barrier: the worker waits there until every
 * other worker of the same wave arrived, so the whole wave hits the
 * database at the same instant.
 *
 * A failing operation does not abort the worker: the failure is recorded
 * and the worker moves on to the next operation. Otherwise one rejected
 * hold would keep the remaining waves from ever reaching their barrier.
 *
 * Usage: php worker.php <job.json> <result.json>
 */

use App\Modules\Inventory\Application\Actions\ExpireHoldsAction;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\Support\Barrier;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$inputPath = $argv[1] ?? null;
$outputPath = $argv[2] ?? null;

if ($inputPath === null || $outputPath === null) {
    fwrite(STDERR, "Usage: worker.php <job.json> <result.json>\n");
    exit(2);
}

/**
 * Resolve the hold an operation addresses.
 *
 * `hold_id` addresses a hold by its absolute id, `hold_ref` by its
 * position among the holds the worker attempted, and the default is the
 * last hold the worker attempted.
 *
 * Null means "the hold was never created" (the hold itself was
 * rejected), so the operation has nothing to do.
 *
 * @param  array<string, mixed>  $operation
 * @param  list<int|null>  $holdIds
 */
function resolveHoldId(array $operation, array $holdIds): ?int
{
    if (isset($operation['hold_ref'])) {
        $index = (int) $operation['hold_ref'];

        if (! array_key_exists($index, $holdIds)) {
            throw new RuntimeException("The worker has no hold at position {$index}.");
        }

        return $holdIds[$index];
    }

    $holdId = $operation['hold_id'] ?? null;

    if ($holdId === 'last' || $holdId === null) {
        if ($holdIds === []) {
            throw new RuntimeException('The operation needs a hold id, but the worker has none.');
        }

        return end($holdIds);
    }

    return (int) $holdId;
}

/**
 * @param  array<string, mixed>  $operation
 */
function executeOperation(array $operation, InventoryService $inventoryService, array $holdIds): mixed
{
    $type = (string) ($operation['type'] ?? '');

    if ($type === 'hold') {
        return $inventoryService->hold(
            propertyId: (int) $operation['property_id'],
            roomTypeId: (int) $operation['room_type_id'],
            checkIn: (string) $operation['check_in'],
            checkOut: (string) $operation['check_out'],
            units: (int) $operation['units'],
            idempotencyKey: (string) $operation['idempotency_key'],
            ttlSeconds: isset($operation['ttl_seconds']) ? (int) $operation['ttl_seconds'] : null,
        )->toArray();
    }

    if ($type === 'expire') {
        return app(ExpireHoldsAction::class)->execute(
            organizationId: (int) $operation['organization_id'],
            limit: (int) ($operation['limit'] ?? 500),
        );
    }

    if ($type !== 'commit' && $type !== 'release') {
        throw new RuntimeException("Unknown operation type: {$type}");
    }

    $holdId = resolveHoldId($operation, $holdIds);

    if ($holdId === null) {
        return null;
    }

    if ($type === 'commit') {
        return $inventoryService->commitHold(
            holdId: $holdId,
            reservationId: isset($operation['reservation_id']) ? (int) $operation['reservation_id'] : null,
        )->toArray();
    }

    return $inventoryService->releaseHold(
        holdId: $holdId,
        reason: isset($operation['reason']) ? (string) $operation['reason'] : null,
    )->toArray();
}

/**
 * @param  array<string, mixed>  $job
 * @return array<string, mixed>
 */
$runJob = function (array $job): array {
    $worker = (string) ($job['worker'] ?? 'worker');

    /** @var array<string, mixed> $connection */
    $connection = (array) ($job['connection'] ?? []);

    config([
        'database.default' => 'concurrency',
        'database.connections.concurrency' => $connection,
    ]);

    DB::purge('concurrency');

    $organizationId = (int) $job['organization_id'];
    $propertyId = (int) $job['property_id'];

    $tenant = TenantContext::forOrganizationAndProperty($organizationId, $propertyId);

    $context = app(OrganizationContext::class);

    /** @var list<array<string, mixed>> $operations */
    $operations = is_array($job['operations'] ?? null)
        ? array_values($job['operations'])
        : [(array) ($job['operation'] ?? [])];

    $startedAt = microtime(true);
    $barrierReleasedAt = 0.0;

    /** @var list<array<string, mixed>> $executed */
    $executed = $context->swap($tenant, function () use ($operations, $worker, &$barrierReleasedAt): array {
        $inventoryService = app(InventoryService::class);

        $results = [];
        $holdIds = [];

        foreach ($operations as $operation) {
            /** @var array{name: string, parties: int, timeout?: int}|null $barrier */
            $barrier = is_array($operation['barrier'] ?? null) ? $operation['barrier'] : null;

            $type = (string) ($operation['type'] ?? '');

            if ($barrier !== null) {
                $barrierReleasedAt = Barrier::arrive(
                    name: (string) $barrier['name'],
                    worker: $worker,
                    parties: (int) $barrier['parties'],
                    timeoutSeconds: (int) ($barrier['timeout'] ?? 120),
                );
            }

            try {
                $value = executeOperation($operation, $inventoryService, $holdIds);

                // Only the holds the worker attempted are addressable by
                // position; a commit or a release returns a hold too.
                if ($type === 'hold') {
                    $holdIds[] = is_array($value) && isset($value['id']) ? (int) $value['id'] : null;
                }

                if ($value === null) {
                    // The hold was never created, so there is nothing to
                    // commit or to release.
                    $results[] = ['status' => 'skipped'];

                    continue;
                }

                $results[] = [
                    'status' => 'ok',
                    'value' => $value,
                ];
            } catch (Throwable $e) {
                // The hold was rejected: it still takes a position in the
                // sequence, so the operations that address it are skipped
                // instead of failing.
                if ($type === 'hold') {
                    $holdIds[] = null;
                }

                $results[] = [
                    'status' => 'error',
                    'error' => [
                        'class' => $e::class,
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        }

        return $results;
    });

    return [
        'status' => 'ok',
        'result' => $executed,
        'duration_ms' => (microtime(true) - $startedAt) * 1000,
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
        'barrier_released_at' => $barrierReleasedAt,
    ];
};

try {
    $job = json_decode((string) file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($job)) {
        throw new RuntimeException('The job payload is not an object.');
    }

    $payload = $runJob($job);
} catch (Throwable $e) {
    $payload = [
        'status' => 'error',
        'result' => null,
        'error' => [
            'class' => $e::class,
            'message' => $e->getMessage(),
        ],
        'duration_ms' => 0,
        'started_at' => 0,
        'finished_at' => 0,
        'barrier_released_at' => 0,
    ];
}

file_put_contents($outputPath, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

exit($payload['status'] === 'ok' ? 0 : 1);
