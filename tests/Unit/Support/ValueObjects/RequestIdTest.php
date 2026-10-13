<?php

declare(strict_types=1);

use App\Support\ValueObjects\RequestId;

it('generate() returns a 16-char hex string', function (): void {
    $id = RequestId::generate();

    expect($id->value)->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('generate() returns a unique value each time', function (): void {
    $first = RequestId::generate()->value;
    $second = RequestId::generate()->value;

    expect($first)->not->toBe($second);
});

it('fromHeader() accepts safe headers (alphanumeric, dash, underscore)', function (): void {
    expect(RequestId::fromHeader('abc123-XYZ_99')->value)->toBe('abc123-XYZ_99');
});

it('fromHeader() returns null header by generating', function (): void {
    $id = RequestId::fromHeader(null);

    expect($id->value)->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('fromHeader() returns empty header by generating', function (): void {
    $id = RequestId::fromHeader('   ');

    expect($id->value)->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('fromHeader() rejects headers containing newlines (log-injection guard)', function (): void {
    $id = RequestId::fromHeader("abc\nDEF");

    expect($id->value)->not->toContain("\n")
        ->and($id->value)->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('fromHeader() rejects headers with spaces', function (): void {
    $id = RequestId::fromHeader('has spaces');

    expect($id->value)->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('fromHeader() rejects headers over 64 chars', function (): void {
    $tooLong = str_repeat('a', 65);
    $id = RequestId::fromHeader($tooLong);

    expect($id->value)->not->toBe($tooLong)
        ->and($id->value)->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('fromHeader() trims surrounding whitespace from valid headers', function (): void {
    expect(RequestId::fromHeader('  abc-def_123  ')->value)->toBe('abc-def_123');
});

it('__toString returns the value', function (): void {
    $id = new RequestId('abc');

    expect((string) $id)->toBe('abc');
});
