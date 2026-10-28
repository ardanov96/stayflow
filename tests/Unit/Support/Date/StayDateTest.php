<?php

declare(strict_types=1);

use App\Support\Date\StayDate;

it('parses a stored stay date', function (): void {
    $date = StayDate::fromString('2026-10-28');

    expect($date->toString())->toBe('2026-10-28')
        ->and($date->year)->toBe(2026)
        ->and($date->month)->toBe(10)
        ->and($date->day)->toBe(28)
        ->and((string) $date)->toBe('2026-10-28');
});

it('rejects a date that does not exist', function (): void {
    expect(fn (): StayDate => StayDate::fromString('2026-02-30'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): StayDate => StayDate::fromString('28-10-2026'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): StayDate => StayDate::fromString('2026-10-28 10:00'))
        ->toThrow(InvalidArgumentException::class);
});

it('adds and removes days across a month and a year', function (): void {
    expect(StayDate::fromString('2026-10-31')->plusDays(1)->toString())->toBe('2026-11-01')
        ->and(StayDate::fromString('2026-01-01')->minusDays(1)->toString())->toBe('2025-12-31')
        ->and(StayDate::fromString('2028-02-28')->plusDays(1)->toString())->toBe('2028-02-29');
});

it('counts the nights between two stay dates', function (): void {
    expect(StayDate::fromString('2026-11-01')->nightsUntil(StayDate::fromString('2026-11-04')))->toBe(3)
        ->and(StayDate::fromString('2026-11-01')->nightsUntil(StayDate::fromString('2026-11-01')))->toBe(0)
        ->and(StayDate::fromString('2026-11-04')->nightsUntil(StayDate::fromString('2026-11-01')))->toBe(-3);
});

it('builds the stay dates of a stay, without the check-out', function (): void {
    $dates = StayDate::fromString('2026-11-01')->range(StayDate::fromString('2026-11-04'));

    expect($dates)->toHaveCount(3)
        ->and($dates[0]->toString())->toBe('2026-11-01')
        ->and($dates[2]->toString())->toBe('2026-11-03');
});

it('compares two stay dates', function (): void {
    $first = StayDate::fromString('2026-11-01');
    $second = StayDate::fromString('2026-11-02');

    expect($first->isBefore($second))->toBeTrue()
        ->and($second->isAfter($first))->toBeTrue()
        ->and($first->equals(StayDate::fromString('2026-11-01')))->toBeTrue()
        ->and($first->compare($second))->toBe(-1)
        ->and($first->isBetween($first, $second))->toBeTrue();
});
