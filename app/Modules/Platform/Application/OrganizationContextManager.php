<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application;

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\PlatformServiceProvider;

/**
 * Default {@see OrganizationContext} implementation.
 *
 * Pure PHP, no Eloquent dependency, so it can be unit-tested in
 * isolation and so the Application layer stays free of the framework
 * (enforced by tests/Arch/EloquentBoundariesTest).
 *
 * Bound as a singleton in
 * {@see PlatformServiceProvider}.
 * The stack is process-local: it is set per request by HTTP middleware,
 * per job by the job middleware, and per test by the test harness.
 */
final class OrganizationContextManager implements OrganizationContext
{
    /** @var list<TenantContext> */
    private array $stack = [];

    public function current(): ?TenantContext
    {
        if ($this->stack === []) {
            return null;
        }

        return $this->stack[array_key_last($this->stack)];
    }

    public function without(callable $callback): mixed
    {
        $saved = $this->stack;
        $this->stack = [];
        try {
            return $callback();
        } finally {
            $this->stack = $saved;
        }
    }

    public function swap(TenantContext $context, callable $callback): mixed
    {
        $this->stack[] = $context;
        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }
}
