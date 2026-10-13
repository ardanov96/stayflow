<?php

declare(strict_types=1);

use App\Modules\Platform\Application\OrganizationContextManager;
use App\Modules\Platform\Domain\Tenancy\TenantContext;

it('returns null when the stack is empty', function (): void {
    $manager = new OrganizationContextManager;

    expect($manager->current())->toBeNull();
});

it('pushes a context inside swap() and pops it on return', function (): void {
    $manager = new OrganizationContextManager;
    $ctx = TenantContext::forOrganization(42);
    $observed = null;
    $manager->swap($ctx, function () use ($manager, &$observed): void {
        $observed = $manager->current();
    });

    expect($observed)->toBe($ctx)
        ->and($manager->current())->toBeNull();
});

it('restores the previous context after nested swaps', function (): void {
    $manager = new OrganizationContextManager;
    $a = TenantContext::forOrganization(1);
    $b = TenantContext::forOrganization(2);
    $order = [];
    $manager->swap($a, function () use ($manager, $b, &$order): void {
        $manager->swap($b, function () use ($manager, &$order): void {
            $order[] = $manager->current()?->organizationId;
        });
        $order[] = $manager->current()?->organizationId;
    });

    expect($order)->toBe([2, 1])
        ->and($manager->current())->toBeNull();
});

it('pops the context even when the callback throws', function (): void {
    $manager = new OrganizationContextManager;
    $a = TenantContext::forOrganization(1);
    try {
        $manager->swap($a, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }
    expect($manager->current())->toBeNull();
});

it('clears the stack inside without() and restores it after', function (): void {
    $manager = new OrganizationContextManager;
    $a = TenantContext::forOrganization(1);
    $inside = null;
    $manager->swap($a, function () use ($manager, &$inside): void {
        $manager->without(function () use ($manager, &$inside): void {
            $inside = $manager->current();
        });
    });

    expect($inside)->toBeNull()
        ->and($manager->current())->toBeNull();
});

it('restores the stack on exception in without()', function (): void {
    $manager = new OrganizationContextManager;
    $a = TenantContext::forOrganization(1);
    $manager->swap($a, function () use ($manager): void {
        try {
            $manager->without(function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected: without() finally restored the stack to $a
        }
        // After the without() call (which threw), the outer context
        // is back. Proves the finally block in without() ran.
        expect($manager->current()?->organizationId)->toBe(1);
    });
});
