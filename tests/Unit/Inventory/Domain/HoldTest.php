<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\HoldStateException;
use App\Modules\Inventory\Domain\Hold\Hold;
use App\Modules\Inventory\Domain\Hold\HoldRequest;
use App\Modules\Inventory\Domain\Hold\HoldStatus;
use DateTimeImmutable;
use InvalidArgumentException;

function makeHold(
    HoldStatus $status = HoldStatus::Active,
    DateTimeImmutable $expiresAt = new DateTimeImmutable('2026-11-01 12:00:00'),
): Hold {
    return new Hold(
        id: 7,
        organizationId: 1,
        propertyId: 2,
        roomTypeId: 3,
        checkIn: '2026-11-01',
        checkOut: '2026-11-03',
        units: 2,
        idempotencyKey: 'key-1',
        expiresAt: $expiresAt,
        status: $status,
    );
}

it('starts active and is not past its expiry', function (): void {
    $hold = makeHold();

    expect($hold->status())->toBe(HoldStatus::Active)
        ->and($hold->isActive())->toBeTrue()
        ->and($hold->isPastExpiry(new DateTimeImmutable('2026-11-01 11:59:59')))->toBeFalse()
        ->and($hold->isPastExpiry(new DateTimeImmutable('2026-11-01 12:00:00')))->toBeTrue();
});

it('commits a hold once and records the reservation', function (): void {
    $hold = makeHold();
    $at = new DateTimeImmutable('2026-11-01 10:00:00');

    $hold->commit(reservationId: 42, at: $at);

    expect($hold->status())->toBe(HoldStatus::Committed)
        ->and($hold->reservationId())->toBe(42)
        ->and($hold->committedAt()?->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00');
});

it('releases a hold once and records the reason', function (): void {
    $hold = makeHold();
    $at = new DateTimeImmutable('2026-11-01 10:00:00');

    $hold->release(reason: 'guest cancelled', at: $at);

    expect($hold->status())->toBe(HoldStatus::Released)
        ->and($hold->releaseReason())->toBe('guest cancelled')
        ->and($hold->releasedAt()?->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00');
});

it('expires a hold once', function (): void {
    $hold = makeHold();
    $at = new DateTimeImmutable('2026-11-01 12:00:00');

    $hold->expire(at: $at);

    expect($hold->status())->toBe(HoldStatus::Expired)
        ->and($hold->expiredAt()?->format('Y-m-d H:i:s'))->toBe('2026-11-01 12:00:00');
});

it('refuses a second transition', function (string $action): void {
    $hold = makeHold();
    $hold->release();

    expect(fn () => $hold->{$action}())->toThrow(HoldStateException::class);
})->with(['commit', 'release', 'expire']);

it('knows which statuses are terminal', function (): void {
    expect(HoldStatus::Active->isTerminal())->toBeFalse()
        ->and(HoldStatus::Active->isActive())->toBeTrue()
        ->and(HoldStatus::Committed->isTerminal())->toBeTrue()
        ->and(HoldStatus::Released->isTerminal())->toBeTrue()
        ->and(HoldStatus::Expired->isTerminal())->toBeTrue()
        ->and(HoldStatus::Committed->isActive())->toBeFalse();
});

it('rejects a malformed hold request', function (array $arguments): void {
    expect(fn () => new HoldRequest(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    'no units' => [['propertyId' => 1, 'roomTypeId' => 2, 'checkIn' => '2026-11-01', 'checkOut' => '2026-11-02', 'units' => 0]],
    'negative units' => [['propertyId' => 1, 'roomTypeId' => 2, 'checkIn' => '2026-11-01', 'checkOut' => '2026-11-02', 'units' => -1]],
    'check-out before check-in' => [['propertyId' => 1, 'roomTypeId' => 2, 'checkIn' => '2026-11-03', 'checkOut' => '2026-11-02', 'units' => 1]],
    'same day' => [['propertyId' => 1, 'roomTypeId' => 2, 'checkIn' => '2026-11-03', 'checkOut' => '2026-11-03', 'units' => 1]],
    'no ttl' => [['propertyId' => 1, 'roomTypeId' => 2, 'checkIn' => '2026-11-01', 'checkOut' => '2026-11-02', 'units' => 1, 'ttlSeconds' => 0]],
    'no property' => [['propertyId' => 0, 'roomTypeId' => 2, 'checkIn' => '2026-11-01', 'checkOut' => '2026-11-02', 'units' => 1]],
    'no room type' => [['propertyId' => 1, 'roomTypeId' => 0, 'checkIn' => '2026-11-01', 'checkOut' => '2026-11-02', 'units' => 1]],
]);

it('derives the stay dates and the expiry from a hold request', function (): void {
    $request = new HoldRequest(
        propertyId: 1,
        roomTypeId: 2,
        checkIn: '2026-11-01',
        checkOut: '2026-11-04',
        units: 2,
        ttlSeconds: 600,
    );

    expect($request->nights())->toBe(3)
        ->and($request->stayDates())->toBe(['2026-11-01', '2026-11-02', '2026-11-03'])
        ->and($request->expiresAt(new DateTimeImmutable('2026-11-01 10:00:00'))->format('H:i:s'))->toBe('10:10:00');
});

it('compares a request with an existing hold', function (): void {
    $hold = makeHold();
    $same = new HoldRequest(
        propertyId: 2,
        roomTypeId: 3,
        checkIn: '2026-11-01',
        checkOut: '2026-11-03',
        units: 2,
    );
    $other = new HoldRequest(
        propertyId: 2,
        roomTypeId: 3,
        checkIn: '2026-11-01',
        checkOut: '2026-11-03',
        units: 5,
    );

    expect($same->describesSameStayAs($hold))->toBeTrue()
        ->and($other->describesSameStayAs($hold))->toBeFalse();
});
