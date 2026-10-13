<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Domain\Tenancy\TenantContext;

/**
 * Public contract for the current tenant context.
 *
 * Used by:
 *   - the BelongsToTenant global scope (read on every tenant query)
 *   - the tenant cache wrapper (read for key prefixing)
 *   - the tenant log processor (read for the log extra fields)
 *   - the PropagatesTenantContext job middleware (set on jobs)
 *   - the SetTenantContext HTTP middleware (set per request)
 *
 * The implementation is a stack so nested {@see swap()} calls are
 * well-defined. Cross-module access is via this contract only.
 */
interface OrganizationContext
{
    /**
     * The current tenant context, or null if no context is active
     * (system jobs, console commands, tests outside a tenant block).
     */
    public function current(): ?TenantContext;

    /**
     * Run a callback with the current context temporarily cleared.
     * Used by system jobs that legitimately span tenants (nightly
     * billing, retention cleanup). Restores the previous state on
     * exception too.
     */
    public function without(callable $callback): mixed;

    /**
     * Run a callback with a specific tenant context active. The
     * previous context (if any) is restored after the callback,
     * even on exception. Safe to nest.
     */
    public function swap(TenantContext $context, callable $callback): mixed;
}
