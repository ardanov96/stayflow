<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('answers the health probe without authentication or a tenant context', function (): void {
    $response = $this->get('/healthz');

    $response->assertOk()
        ->assertJsonPath('status', 'ok');
});

it('reports every component the deployment pipeline cares about', function (): void {
    $response = $this->get('/healthz');

    $response->assertOk();

    $payload = $response->json();

    expect($payload)->toHaveKeys(['status', 'version', 'environment', 'checks'])
        ->and($payload['checks'])->toHaveKeys(['database', 'migrations', 'cache', 'storage', 'queue'])
        ->and($payload['checks']['database']['status'])->toBe('ok')
        ->and($payload['checks']['migrations']['status'])->toBe('ok');
});

it('answers 503 when a component is broken and never leaks the reason', function (): void {
    // A cache store that does not exist makes the cache check fail for
    // real, through the same code path a broken Redis would.
    config(['cache.default' => 'store-that-does-not-exist']);

    $response = $this->get('/healthz');

    $response->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.cache.status', 'failed')
        ->assertJsonPath('checks.database.status', 'ok');

    // The failing component is named, the reason is not disclosed.
    expect($response->getContent())
        ->not->toContain('store-that-does-not-exist');
});

it('keeps the cache check from leaving anything behind', function (): void {
    $before = Cache::getStore()->getPrefix();

    $this->get('/healthz')->assertOk();

    expect(Cache::getStore()->getPrefix())->toBe($before);
});
