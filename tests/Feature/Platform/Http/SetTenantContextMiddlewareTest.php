<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('echoes the organization id from the X-Organization-Id header', function (): void {
    $response = $this->getJson('/_testing/tenant/ping', ['X-Organization-Id' => '42']);

    $response->assertOk();
    expect($response->json('organization_id'))->toBe(42)
        ->and($response->json('property_id'))->toBeNull();
});

it('returns a null context when the header is missing', function (): void {
    $response = $this->getJson('/_testing/tenant/ping');

    $response->assertOk();
    expect($response->json('organization_id'))->toBeNull()
        ->and($response->json('property_id'))->toBeNull();
});

it('also picks up X-Property-Id when present', function (): void {
    $response = $this->getJson('/_testing/tenant/ping', [
        'X-Organization-Id' => '42',
        'X-Property-Id' => '7',
    ]);

    $response->assertOk();
    expect($response->json('organization_id'))->toBe(42)
        ->and($response->json('property_id'))->toBe(7);
});

it('ignores non-numeric headers and runs the request with no context', function (): void {
    $response = $this->getJson('/_testing/tenant/ping', ['X-Organization-Id' => 'abc']);

    $response->assertOk();
    expect($response->json('organization_id'))->toBeNull();
});

it('ignores zero and negative header values', function (): void {
    foreach (['0', '-1', '-42'] as $bad) {
        $response = $this->getJson('/_testing/tenant/ping', ['X-Organization-Id' => $bad]);
        $response->assertOk();
        expect($response->json('organization_id'))->toBeNull();
    }
});

it('does not leak context across requests', function (): void {
    $this->getJson('/_testing/tenant/ping', ['X-Organization-Id' => '42'])
        ->assertOk();
    $this->getJson('/_testing/tenant/ping')
        ->assertOk()
        ->assertJson(['organization_id' => null, 'property_id' => null]);
});
