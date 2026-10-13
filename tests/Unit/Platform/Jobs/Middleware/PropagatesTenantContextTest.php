<?php

declare(strict_types=1);

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext;

beforeEach(function (): void {
    $this->context = app(OrganizationContext::class);
});

it('sets the explicit context for the job execution', function (): void {
    $ctx = TenantContext::forOrganization(42);
    $middleware = new PropagatesTenantContext($ctx);
    $observed = null;
    $middleware->handle(new stdClass, function () use (&$observed): void {
        $observed = app(OrganizationContext::class)->current();
    });
    expect($observed)->not->toBeNull()
        ->and($observed->organizationId)->toBe(42)
        ->and($observed->propertyId)->toBeNull();
});

it('captures the active context when no explicit one is passed', function (): void {
    $this->context->swap(TenantContext::forOrganization(99), function (): void {
        $middleware = new PropagatesTenantContext;
        $observed = null;
        $middleware->handle(new stdClass, function () use (&$observed): void {
            $observed = app(OrganizationContext::class)->current();
        });
        expect($observed)->not->toBeNull()
            ->and($observed->organizationId)->toBe(99);
    });
});

it('runs the job with no context when no context is active', function (): void {
    $middleware = new PropagatesTenantContext;
    $observed = 'sentinel';
    $middleware->handle(new stdClass, function () use (&$observed): void {
        $observed = app(OrganizationContext::class)->current();
    });
    expect($observed)->toBeNull();
});

it('restores the previous context after the job runs', function (): void {
    $ctx = TenantContext::forOrganization(42);
    $middleware = new PropagatesTenantContext($ctx);
    $this->context->swap(TenantContext::forOrganization(1), function () use ($middleware): void {
        $middleware->handle(new stdClass, function (): void {
            // inside the job, context is 42
            expect(app(OrganizationContext::class)->current()->organizationId)->toBe(42);
        });
        // after the job, context is back to 1
        expect(app(OrganizationContext::class)->current()->organizationId)->toBe(1);
    });
});
