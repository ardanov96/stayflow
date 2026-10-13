<?php

declare(strict_types=1);
use App\Support\Logging\PiiScrubberProcessor;
use App\Support\Logging\RequestIdProcessor;
use Illuminate\Support\Facades\Log;

it('writes newline-delimited JSON to the json channel with PII redacted and request_id in extra', function (): void {
    // Use a temp file so we don't pollute storage/logs/ during the test run.
    $tmp = tempnam(sys_get_temp_dir(), 'stayflow-json-').'.jsonl';
    config(['logging.channels.json.with.stream' => $tmp]);

    Log::shareContext(['request_id' => 'test-id-1234']);

    $channel = Log::channel('json');
    $channel->info('user login', [
        'email' => 'john@example.com',
        'api_key' => 'sk_live_secret',
    ]);

    // Flush the underlying Monolog handlers so the write is durable before
    // we read the file.
    foreach ($channel->getLogger()->getHandlers() as $h) {
        if (method_exists($h, 'close')) {
            $h->close();
        }
    }

    $rawContent = (string) file_get_contents($tmp);
    unlink($tmp);

    // The appendNewline=true on the JsonFormatter means each line is
    // terminated with \n; we only check the line itself, not the terminator.
    $line = rtrim($rawContent, "\n");
    $decoded = json_decode($line, true);

    expect($rawContent)->toEndWith("\n")
        ->and($decoded)->toBeArray()
        ->and($decoded['message'])->toBe('user login')
        ->and($decoded['context']['email'])->toBe('***@***.***')
        ->and($decoded['context']['api_key'])->toBe('***')
        ->and($decoded['extra']['request_id'] ?? null)->toBe('test-id-1234');
});

it('the json channel is wired with the scrubber and request_id processors', function (): void {
    $processors = config('logging.channels.json.processors');

    expect($processors)->toContain(PiiScrubberProcessor::class)
        ->and($processors)->toContain(RequestIdProcessor::class);
});
