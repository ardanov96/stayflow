<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Settings;

use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Application\Settings\SettingDefinition;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Contracts\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The settings, on the database and in the cache.
 *
 * A value is read once and kept for a while; writing it invalidates the
 * cache, so a change is visible at once. The time in the cache is only a
 * safety net.
 */
final class EloquentSettings implements Settings
{
    public function __construct(
        private readonly OrganizationContext $organizationContext,
        private readonly Auditor $auditor,
    ) {}

    public function get(string $key, ?int $propertyId = null): mixed
    {
        $definition = $this->definition($key);
        $organizationId = $this->organizationId();

        return Cache::remember(
            $this->cacheKey($organizationId, $propertyId, $key),
            (int) config('settings.cache_seconds', 300),
            fn (): mixed => $this->read($organizationId, $propertyId, $definition),
        );
    }

    public function getInt(string $key, ?int $propertyId = null): int
    {
        return (int) $this->get($key, $propertyId);
    }

    public function getString(string $key, ?int $propertyId = null): string
    {
        return (string) $this->get($key, $propertyId);
    }

    public function getBool(string $key, ?int $propertyId = null): bool
    {
        return (bool) $this->get($key, $propertyId);
    }

    public function set(string $key, mixed $value, ?int $propertyId = null, ?int $actorId = null): void
    {
        $definition = $this->definition($key);
        $organizationId = $this->organizationId();

        if (! $definition->accepts($value)) {
            throw new InvalidArgumentException(sprintf(
                'The setting "%s" is a %s and does not accept the given value.',
                $key,
                $definition->type,
            ));
        }

        $scope = $definition->scope === SettingDefinition::SCOPE_PROPERTY ? $propertyId : null;

        if ($definition->scope === SettingDefinition::SCOPE_PROPERTY && $scope === null) {
            throw new InvalidArgumentException("The setting \"{$key}\" belongs to a property.");
        }

        $cast = $definition->cast($value);
        $previous = $this->read($organizationId, $propertyId, $definition);

        DB::table('settings')->updateOrInsert(
            [
                'organization_id' => $organizationId,
                'property_id' => $scope,
                'key' => $key,
            ],
            [
                'value' => json_encode($cast),
                'updated_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // The change is visible at once.
        Cache::forget($this->cacheKey($organizationId, $propertyId, $key));

        $this->auditor->recordCustom(
            'settings.updated',
            new SubjectChange(
                subjectId: $scope ?? $organizationId,
                subjectType: $scope === null ? 'Organization' : 'Property',
                oldValues: ['value' => $previous],
                newValues: ['value' => $cast],
                metadata: ['key' => $key],
            ),
            new TenantHint(
                organizationId: $organizationId,
                propertyId: $scope,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?int $propertyId = null): array
    {
        $values = [];

        foreach (array_keys($this->definitions()) as $key) {
            $values[$key] = $this->get($key, $propertyId);
        }

        return $values;
    }

    private function read(int $organizationId, ?int $propertyId, SettingDefinition $definition): mixed
    {
        $scope = $definition->scope === SettingDefinition::SCOPE_PROPERTY ? $propertyId : null;

        $row = DB::table('settings')
            ->where('organization_id', $organizationId)
            ->where('property_id', $scope)
            ->where('key', $definition->key)
            ->first();

        if ($row === null) {
            return $definition->default;
        }

        $value = json_decode((string) $row->value, true);

        return $value ?? $definition->default;
    }

    private function definition(string $key): SettingDefinition
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException("The setting \"{$key}\" does not exist.");
        }

        return $definitions[$key];
    }

    /**
     * @return array<string, SettingDefinition>
     */
    private function definitions(): array
    {
        $definitions = [];

        foreach ((array) config('settings.settings', []) as $key => $definition) {
            $definitions[(string) $key] = SettingDefinition::fromConfig((string) $key, (array) $definition);
        }

        return $definitions;
    }

    private function organizationId(): int
    {
        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            throw new InvalidArgumentException('A tenant context is required to read or write a setting.');
        }

        return $organizationId;
    }

    private function cacheKey(int $organizationId, ?int $propertyId, string $key): string
    {
        return sprintf('settings:%d:%s:%s', $organizationId, $propertyId ?? 'organization', $key);
    }
}
