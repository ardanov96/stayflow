<?php

declare(strict_types=1);

namespace App\Modules\Platform\Cache;

use App\Modules\Platform\Contracts\OrganizationContext;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache wrapper that automatically prefixes every key with the
 * active organization id. Backed by the configured cache store
 * (file/database/redis in prod, array in tests). This is the
 * tenant-aware cache promised by BACKLOG P1-01.
 *
 * Cross-tenant cache reads are impossible: organization 42 writes
 * to "org:42:foo" and organization 43 reads from "org:43:foo".
 *
 * Caller responsibility: include property_id in the key when
 * property-level isolation is needed inside a single organization.
 * TenantCache only adds the organization prefix.
 *
 * When no OrganizationContext is active (system jobs, console
 * commands, tests outside a tenant block), keys are passed through
 * unchanged. This is intentional: the cache then becomes a system
 * shared cache, which is the correct semantic for cross-tenant
 * code.
 */
final class TenantCache
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly OrganizationContext $context,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->prefix($key), $default);
    }

    public function put(string $key, mixed $value, int|DateInterval|DateTimeInterface|null $ttl = null): void
    {
        $this->cache->put($this->prefix($key), $value, $ttl);
    }

    public function remember(string $key, int|DateInterval|DateTimeInterface|null $ttl, Closure $callback): mixed
    {
        return $this->cache->remember($this->prefix($key), $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($this->prefix($key));
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->prefix($key));
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->cache->pull($this->prefix($key), $default);
    }

    private function prefix(string $key): string
    {
        $current = $this->context->current();
        if ($current === null) {
            return $key;
        }

        return "org:{$current->organizationId}:".$key;
    }
}
