<?php

declare(strict_types=1);

use App\Support\Logging\PiiScrubberProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

it('scrubs message, context, and extra via PiiScrubber', function (): void {
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'json',
        level: Level::Info,
        message: 'login attempt for user@example.com',
        context: ['api_key' => 'sk_live_secret', 'host' => 'localhost'],
        extra: ['password' => 'hunter2'],
    );

    $out = (new PiiScrubberProcessor)($record);

    expect($out->message)->toBe('login attempt for ***@***.***')
        ->and($out->context)->toBe(['api_key' => '***', 'host' => 'localhost'])
        ->and($out->extra)->toBe(['password' => '***']);
});

it('implements Monolog\\Processor\\ProcessorInterface', function (): void {
    expect(is_a(PiiScrubberProcessor::class, ProcessorInterface::class, true))->toBeTrue();
});
