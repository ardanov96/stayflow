<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Concerns;

use App\Modules\Platform\Contracts\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Opt-in trait for Eloquent models whose table has an
 * `organization_id` column. Applies {@see BelongsToTenantScope}
 * globally and auto-fills `organization_id` on create from the
 * current {@see OrganizationContext}.
 *
 * This is the only supported way for a model to be considered
 * tenant-scoped. The CI gate
 * (tests/Feature/Platform/Tenancy/TenantTableArchTest) fails the
 * build when a new tenant table is added without this trait, or
 * when the trait is added to a table that lacks the column.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new BelongsToTenantScope);

        static::creating(static function (Model $model): void {
            // Explicit assignment wins; this lets system seeders
            // (OrganizationContext::without() + set org_id manually)
            // create rows without an active context.
            if ($model->getAttribute('organization_id') !== null) {
                return;
            }

            $current = app(OrganizationContext::class)->current();
            if ($current === null) {
                throw new RuntimeException(sprintf(
                    'Cannot create %s without an active OrganizationContext. '
                    .'Either set the context via OrganizationContext::swap() '
                    .'or middleware, or pass organization_id explicitly.',
                    $model::class,
                ));
            }

            $model->setAttribute('organization_id', $current->organizationId);
        });
    }
}
