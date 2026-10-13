<?php

declare(strict_types=1);

use App\Support\Logging\RequestIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

it('promotes context.request_id to extra.request_id', function (): void {
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'json',
        level: Level::Info,
        message: 'hello',
        context: ['request_id' => 'abc-123'],
        extra: [],
    );

    $out = (new RequestIdProcessor)($record);

    expect($out->extra)->toBe(['request_id' => 'abc-123'])
        ->and($out->context)->toBe(['request_id' => 'abc-123']);
});

it('leaves extra.request_id untouched when it is already set', function (): void {
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'json',
        level: Level::Info,
        message: 'hello',
        context: ['request_id' => 'abc-123'],
        extra: ['request_id' => 'override'],
    );

    $out = (new RequestIdProcessor)($record);

    expect($out->extra)->toBe(['request_id' => 'override']);
});

it('is a no-op when context has no request_id', function (): void {
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'json',
        level: Level::Info,
        message: 'hello',
        context: ['foo' => 'bar'],
        extra: [],
    );

    $out = (new RequestIdProcessor)($record);

    expect($out->extra)->toBe([]);
});

it('implements Monolog\\Processor\\ProcessorInterface', function (): void {
    expect(is_a(RequestIdProcessor::class, ProcessorInterface::class, true))->toBeTrue();
});
