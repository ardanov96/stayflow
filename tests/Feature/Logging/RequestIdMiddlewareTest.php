<?php

declare(strict_types=1);

use App\Support\ValueObjects\RequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('echoes a freshly generated request id on the response when no header is sent', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get(RequestId::HEADER))->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});

it('echoes a safe X-Request-Id header from the caller back on the response', function (): void {
    $response = $this->withHeader(RequestId::HEADER, 'caller-supplied-id-1')->get('/');

    $response->assertOk();
    expect($response->headers->get(RequestId::HEADER))->toBe('caller-supplied-id-1');
});

it('rejects an X-Request-Id with a newline and generates a fresh one', function (): void {
    $response = $this->withHeader(RequestId::HEADER, "abc\nfake-second-header: yes")->get('/');

    $response->assertOk();
    expect($response->headers->get(RequestId::HEADER))->not->toContain("\n")
        ->and($response->headers->get(RequestId::HEADER))->toMatch('/\A[A-Fa-f0-9]{16}\z/');
});
