<?php

declare(strict_types=1);

use App\Modules\Inventory\Application\Support\RetriesOnContention;
use App\Modules\Inventory\Domain\Exceptions\InventoryContentionException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

$subject = fn (): object => new class
{
    use RetriesOnContention;

    public function run(callable $callback): mixed
    {
        return $this->withContentionRetry($callback);
    }
};

$duplicateIdempotencyKey = function (): UniqueConstraintViolationException {
    $pdo = new PDOException(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'key-1' "
        ."for key 'inventory_holds.inventory_holds_org_idempotency_unique'"
    );
    $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];

    return new UniqueConstraintViolationException('mysql', 'insert into inventory_holds ...', [], $pdo);
};

$deadlock = function (): QueryException {
    $pdo = new PDOException('Deadlock found when trying to get lock');
    $pdo->errorInfo = ['40001', 1213, 'Deadlock found'];

    return new QueryException('mysql', 'select 1', [], $pdo);
};

it('retries a deadlock and gives up with a "try again" error', function () use ($subject, $deadlock): void {
    $worker = $subject();

    $attempts = 0;

    $call = function () use ($worker, &$attempts, $deadlock): void {
        $worker->run(function () use (&$attempts, $deadlock): never {
            $attempts++;

            throw $deadlock();
        });
    };

    expect($call)->toThrow(InventoryContentionException::class);

    expect($attempts)->toBe(3);
});

it('returns the result of the callback when nothing fails', function () use ($subject): void {
    $worker = $subject();

    expect($worker->run(fn (): string => 'ok'))->toBe('ok');
});

it('does not retry a duplicate idempotency key', function () use ($subject, $duplicateIdempotencyKey): void {
    $worker = $subject();

    $attempts = 0;

    // The same INSERT would fail the same way: the winner has to be
    // resolved by the caller, not by a retry.
    $call = function () use ($worker, &$attempts, $duplicateIdempotencyKey): void {
        $worker->run(function () use (&$attempts, $duplicateIdempotencyKey): never {
            $attempts++;

            throw $duplicateIdempotencyKey();
        });
    };

    expect($call)->toThrow(UniqueConstraintViolationException::class);

    expect($attempts)->toBe(1);
});

it('does not retry an error that is not contention', function () use ($subject): void {
    $worker = $subject();

    $attempts = 0;

    $call = function () use ($worker, &$attempts): void {
        $worker->run(function () use (&$attempts): never {
            $attempts++;

            throw new RuntimeException('boom');
        });
    };

    expect($call)->toThrow(RuntimeException::class);

    expect($attempts)->toBe(1);
});
