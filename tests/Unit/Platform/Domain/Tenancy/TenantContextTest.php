<?php

declare(strict_types=1);

use App\Modules\Platform\Domain\Tenancy\TenantContext;

it('creates an organization-only context', function (): void {
    $ctx = TenantContext::forOrganization(42);

    expect($ctx->organizationId)->toBe(42)
        ->and($ctx->propertyId)->toBeNull();
});

it('creates an organization-and-property context', function (): void {
    $ctx = TenantContext::forOrganizationAndProperty(42, 7);

    expect($ctx->organizationId)->toBe(42)
        ->and($ctx->propertyId)->toBe(7);
});

it('returns a new context when adding a property; original is untouched', function (): void {
    $a = TenantContext::forOrganization(42);
    $b = $a->withProperty(7);

    expect($b->organizationId)->toBe(42)
        ->and($b->propertyId)->toBe(7)
        ->and($a->propertyId)->toBeNull();
});

it('equals another context with identical ids', function (): void {
    $a = TenantContext::forOrganizationAndProperty(42, 7);
    $b = TenantContext::forOrganizationAndProperty(42, 7);
    $c = TenantContext::forOrganizationAndProperty(42, 8);

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('rejects a non-positive organizationId at construction', function (): void {
    expect(static fn (): TenantContext => TenantContext::forOrganization(0))
        ->toThrow(InvalidArgumentException::class);
    expect(static fn (): TenantContext => TenantContext::forOrganization(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a non-positive propertyId when explicitly set', function (): void {
    expect(static fn (): TenantContext => TenantContext::forOrganizationAndProperty(42, 0))
        ->toThrow(InvalidArgumentException::class);
    expect(static fn (): TenantContext => TenantContext::forOrganization(42)->withProperty(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('stringifies as org:NN (no property)', function (): void {
    expect((string) TenantContext::forOrganization(42))->toBe('org:42');
});

it('stringifies as org:NN/prop:NN (with property)', function (): void {
    expect((string) TenantContext::forOrganizationAndProperty(42, 7))->toBe('org:42/prop:7');
});
