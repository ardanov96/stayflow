<?php

declare(strict_types=1);

use App\Support\Helpers\PiiScrubber;

it('redacts simple email addresses', function (): void {
    expect(PiiScrubber::scrubString('Contact john@example.com please'))
        ->toBe('Contact ***@***.*** please');
});

it('redacts multiple emails in one string', function (): void {
    $scrubbed = PiiScrubber::scrubString('a@x.com and b@y.com');

    expect($scrubbed)->not->toContain('a@x.com')
        ->and($scrubbed)->not->toContain('b@y.com')
        ->and($scrubbed)->toContain('***@***.***');
});

it('redacts phone numbers with + prefix', function (): void {
    expect(PiiScrubber::scrubString('Call +62 812 3456 7890 now'))
        ->toContain('+***********');
});

it('redacts credential assignments in env-file shape', function (): void {
    expect(PiiScrubber::scrubString('api_key=sk_live_secret'))
        ->toBe('api_key=***');
});

it('redacts credential assignments in header shape, preserving the original key case', function (): void {
    // The captured key name is preserved (case-sensitive) so callers can still
    // tell which header was scrubbed; the value is fully redacted.
    expect(PiiScrubber::scrubString('Authorization: Bearer abc123def456'))
        ->toBe('Authorization=***');
});

it('does not rewrite JSON-shaped strings (handled by key-aware scrubArray instead)', function (): void {
    // Quoted JSON keys break the simple `key=value` regex; we deliberately do
    // not touch them here. The context-array path replaces them via the
    // sensitive-key lookup in scrubArray().
    $json = '"api_key": "sk_live_secret"';

    expect(PiiScrubber::scrubString($json))->toBe($json);
});

it('redacts a `key: jwt` shape as a credential (the JWT pattern is never reached)', function (): void {
    $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';

    // When a key prefix is present, the CREDENTIAL pattern wins (matches the
    // whole `key: jwt` shape) and the value is replaced with `***`. The JWT
    // shape is not separately preserved in the output.
    expect(PiiScrubber::scrubString("token: $jwt"))->toBe('token=***');
});

it('redacts a bare three-segment JWT (no key prefix)', function (): void {
    $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';

    expect(PiiScrubber::scrubString($jwt))->toBe('***.***');
});

it('leaves clean strings alone', function (): void {
    expect(PiiScrubber::scrubString('Operation completed in 120ms'))->toBe('Operation completed in 120ms');
});

it('redacts emails in nested context arrays', function (): void {
    $scrubbed = PiiScrubber::scrubArray([
        'user' => [
            'name' => 'John',
            'email' => 'john@example.com',
        ],
    ]);

    expect($scrubbed['user']['email'])->toBe('***@***.***')
        ->and($scrubbed['user']['name'])->toBe('John');
});

it('redacts any value when the key name is sensitive (password, token, secret, ...)', function (): void {
    $scrubbed = PiiScrubber::scrubArray([
        'password' => 'hunter2',
        'api_key' => 'sk_live_xyz',
        'session_id' => 'abc123',
        'X-Auth-Token' => 'whatever',
    ]);

    expect($scrubbed)->toBe([
        'password' => '***',
        'api_key' => '***',
        'session_id' => '***',
        'X-Auth-Token' => '***',
    ]);
});

it('preserves non-string scalar context values', function (): void {
    $scrubbed = PiiScrubber::scrubArray([
        'count' => 42,
        'ok' => true,
        'data' => null,
    ]);

    expect($scrubbed)->toBe(['count' => 42, 'ok' => true, 'data' => null]);
});

it('scrub() dispatches to the right helper based on input type', function (): void {
    expect(PiiScrubber::scrub('a@b.com'))->toBe('***@***.***')
        ->and(PiiScrubber::scrub(['email' => 'a@b.com']))->toBe(['email' => '***@***.***']);
});
