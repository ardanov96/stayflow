<?php

declare(strict_types=1);

use App\Support\Date\DateFormatter;
use App\Support\Date\PropertyClock;
use App\Support\Date\StayDate;
use Illuminate\Support\Facades\Date;

/**
 * The midnight boundary: the same instant is a different day for two
 * hotels in two timezones.
 */
it('gives a different day to two properties in two timezones', function (): void {
    // 17:00 UTC: it is already the next day in Bali (UTC+8), and still
    // the same day in Los Angeles (UTC-7 in October).
    $now = new DateTimeImmutable('2026-10-27 17:00:00', new DateTimeZone('UTC'));

    $clock = new PropertyClock;

    expect($clock->today('Asia/Makassar', $now)->toString())->toBe('2026-10-28')
        ->and($clock->today('America/Los_Angeles', $now)->toString())->toBe('2026-10-27')
        ->and($clock->today('UTC', $now)->toString())->toBe('2026-10-27');
});

it('gives the moment of the property, not the moment of the server', function (): void {
    $now = new DateTimeImmutable('2026-10-27 17:00:00', new DateTimeZone('UTC'));

    $local = (new PropertyClock)->now('Asia/Makassar', $now);

    expect($local->format('Y-m-d H:i:s'))->toBe('2026-10-28 01:00:00')
        ->and($local->getTimezone()->getName())->toBe('Asia/Makassar');
});

it('follows the clock of the application when it is frozen', function (): void {
    Date::setTestNow('2026-10-27 17:00:00');

    try {
        expect((new PropertyClock)->today('Asia/Makassar')->toString())->toBe('2026-10-28')
            ->and((new PropertyClock)->businessDate('Asia/Makassar')->toString())->toBe('2026-10-28');
    } finally {
        Date::setTestNow();
    }
});

it('formats a stay date in the locale of the reader', function (): void {
    $date = StayDate::fromString('2026-10-28');

    $english = (new DateFormatter('en'))->date($date);
    $indonesian = (new DateFormatter('id'))->date($date);

    expect($english)->toBe('Oct 28, 2026')
        ->and($indonesian)->toContain('28')
        ->and($indonesian)->not->toBe($english);
});

it('formats a number in the locale of the reader', function (): void {
    expect((new DateFormatter('en'))->number(1234567))->toBe('1,234,567')
        ->and((new DateFormatter('id'))->number(1234567))->toBe('1.234.567');
});
