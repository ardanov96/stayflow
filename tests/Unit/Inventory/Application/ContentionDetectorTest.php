<?php

declare(strict_types=1);

use App\Modules\Inventory\Application\Support\ContentionDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('detects a deadlock exception', function (): void {
    expect(ContentionDetector::isDeadlock(new DeadlockException('Deadlock found')))->toBeTrue();
});

it('detects a deadlock reported as a serialization failure', function (): void {
    // PDOException carries the SQLSTATE in its code.
    $pdo = new PDOException('Deadlock found when trying to get lock', 40001);

    expect(ContentionDetector::isDeadlock(new QueryException('mysql', 'select 1', [], $pdo)))->toBeTrue();
});

it('detects a deadlock reported by the mysql driver code', function (): void {
    $pdo = new PDOException('Deadlock found when trying to get lock');
    $pdo->errorInfo = ['40001', 1213, 'Deadlock found'];

    expect(ContentionDetector::isDeadlock(new QueryException('mysql', 'select 1', [], $pdo)))->toBeTrue();
});

it('detects a lock wait timeout', function (): void {
    $pdo = new PDOException('Lock wait timeout exceeded; try restarting transaction');
    $pdo->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];

    expect(ContentionDetector::isLockWaitTimeout(new QueryException('mysql', 'select 1', [], $pdo)))->toBeTrue()
        ->and(ContentionDetector::isDeadlock(new QueryException('mysql', 'select 1', [], $pdo)))->toBeFalse();
});

it('detects the idempotency key collision', function (): void {
    $pdo = new PDOException(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'key-1' "
        ."for key 'inventory_holds.inventory_holds_org_idempotency_unique'"
    );
    $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];

    $exception = new UniqueConstraintViolationException('mysql', 'insert into inventory_holds ...', [], $pdo);

    expect(ContentionDetector::isDuplicateIdempotencyKey($exception))->toBeTrue()
        ->and(ContentionDetector::isRetryable($exception))->toBeTrue();
});

it('does not treat a duplicate on another index as a lost idempotency race', function (): void {
    $pdo = new PDOException(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' "
        ."for key 'inventory_hold_lines.inventory_hold_lines_hold_date_unique'"
    );
    $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];

    $exception = new UniqueConstraintViolationException(
        'mysql',
        'insert into inventory_hold_lines ...',
        [],
        $pdo,
    );

    // A real conflict must never be swallowed as a replay.
    expect(ContentionDetector::isDuplicateIdempotencyKey($exception))->toBeFalse()
        ->and(ContentionDetector::isRetryable($exception))->toBeFalse();
});

it('detects the idempotency key collision reported by sqlite', function (): void {
    $pdo = new PDOException(
        'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: '
        .'inventory_holds.organization_id, inventory_holds.idempotency_key'
    );
    $pdo->errorInfo = ['23000', 19, 'UNIQUE constraint failed'];

    $exception = new UniqueConstraintViolationException(
        'sqlite',
        'insert into inventory_holds ...',
        [],
        $pdo,
    );

    expect(ContentionDetector::isDuplicateIdempotencyKey($exception))->toBeTrue();
});

it('ignores unrelated failures', function (): void {
    expect(ContentionDetector::isRetryable(new RuntimeException('boom')))->toBeFalse()
        ->and(ContentionDetector::isDeadlock(new RuntimeException('boom')))->toBeFalse()
        ->and(ContentionDetector::isLockWaitTimeout(new RuntimeException('boom')))->toBeFalse()
        ->and(ContentionDetector::isDuplicateIdempotencyKey(new RuntimeException('boom')))->toBeFalse();
});
