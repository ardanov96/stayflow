<?php

declare(strict_types=1);

use App\Modules\Platform\Cache\TenantCache;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;

beforeEach(function (): void {
    $this->cache = app(TenantCache::class);
    $this->context = app(OrganizationContext::class);
});

it('prefixes the key with org:NN when a context is active', function (): void {
    $this->context->swap(TenantContext::forOrganization(1), function (): void {
        $this->cache->put('k', 'v1', 60);
    });
    $this->context->swap(TenantContext::forOrganization(2), function (): void {
        $this->cache->put('k', 'v2', 60);
    });

    $this->context->swap(TenantContext::forOrganization(1), function (): void {
        expect($this->cache->get('k'))->toBe('v1');
    });
    $this->context->swap(TenantContext::forOrganization(2), function (): void {
        expect($this->cache->get('k'))->toBe('v2');
    });
});

it('passes the key through when no context is active', function (): void {
    $this->cache->put('system', 'sys', 60);

    expect($this->cache->get('system'))->toBe('sys');
});

it('forget() only clears the prefixed key for the active org', function (): void {
    $this->context->swap(TenantContext::forOrganization(1), function (): void {
        $this->cache->put('k', 'v1', 60);
    });
    $this->context->swap(TenantContext::forOrganization(2), function (): void {
        $this->cache->put('k', 'v2', 60);
    });

    $this->context->swap(TenantContext::forOrganization(1), function (): void {
        expect($this->cache->forget('k'))->toBeTrue();
    });
    $this->context->swap(TenantContext::forOrganization(2), function (): void {
        expect($this->cache->get('k'))->toBe('v2');
    });
});

it('has() reflects the prefixed key only', function (): void {
    $this->context->swap(TenantContext::forOrganization(1), function (): void {
        $this->cache->put('k', 'v1', 60);
    });
    $this->context->swap(TenantContext::forOrganization(2), function (): void {
        expect($this->cache->has('k'))->toBeFalse();
    });
    $this->context->swap(TenantContext::forOrganization(1), function (): void {
        expect($this->cache->has('k'))->toBeTrue();
    });
});

it('remember() runs the callback only on cache miss', function (): void {
    $hits = 0;
    $builder = function () use (&$hits): string {
        $hits++;

        return 'computed';
    };

    $this->context->swap(TenantContext::forOrganization(1), function () use ($builder): void {
        $first = $this->cache->remember('k', 60, $builder);
        $second = $this->cache->remember('k', 60, $builder);
        expect($first)->toBe('computed')
            ->and($second)->toBe('computed');
    });

    expect($hits)->toBe(1);
});
