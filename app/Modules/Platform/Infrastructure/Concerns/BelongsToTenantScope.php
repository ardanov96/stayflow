<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Concerns;

use App\Modules\Platform\Contracts\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

/**
 * Eloquent global scope that filters every query by the current
 * {@see OrganizationContext}.
 *
 * Security property (docs/ARCHITECTURE.md §4, ADR-0003): queries
 * against tenant tables MUST NOT return rows from another organization.
 *
 * - When a context is active, the scope adds
 *   `WHERE organization_id = <current>` and, if the table has a
 *   `property_id` column and the context carries a propertyId,
 *   `AND property_id = <current.propertyId>`.
 * - When no context is active, the scope short-circuits to a
 *   `1 = 0` predicate. Returning zero rows is the safe default;
 *   the developer is expected to set a context first (HTTP
 *   middleware does this per request; job middleware does it
 *   per job). The create-time guard in {@see BelongsToTenant}
 *   also throws if a model is created without context, so the
 *   mistake is caught loudly.
 *
 * To legitimately read across tenants (nightly billing, retention
 * cleanup) use OrganizationContext::without() AND
 * Model::withoutGlobalScope(BelongsToTenantScope::class) together.
 */
final class BelongsToTenantScope implements Scope
{
    /** @var array<string, bool> */
    private static array $propertyIdCache = [];

    public function apply(Builder $builder, Model $model): void
    {
        $context = app(OrganizationContext::class);
        $current = $context->current();
        $table = $model->getTable();

        if ($current === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where("{$table}.organization_id", $current->organizationId);

        if ($current->propertyId !== null && $this->tableHasPropertyId($table)) {
            $builder->where("{$table}.property_id", $current->propertyId);
        }
    }

    private function tableHasPropertyId(string $table): bool
    {
        if (! isset(self::$propertyIdCache[$table])) {
            self::$propertyIdCache[$table] = Schema::hasColumn($table, 'property_id');
        }

        return self::$propertyIdCache[$table];
    }
}
