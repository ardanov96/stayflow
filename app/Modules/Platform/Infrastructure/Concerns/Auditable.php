<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Concerns;

use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\AuditLog\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * Opt-in trait for Eloquent models that should be audited.
 *
 * Hooks the four Eloquent events that the audit log cares about
 * (`created`, `updated`, `deleted`, `restored`) and delegates the
 * actual write to the {@see Auditor} port. Models opt in with one
 * `use Auditable;` line; nothing else changes.
 *
 * ## What is captured
 *
 * For each event, the trait builds a {@see SubjectChange} value object
 * from the model and a {@see TenantHint} from the model's tenant
 * columns, and passes both to the port. The contents follow the
 * verb semantics:
 *
 * | Event    | oldValues                     | newValues                    |
 * |----------|-------------------------------|------------------------------|
 * | created  | null                          | full attributes after create |
 * | updated  | getOriginal() before update   | getAttributes() after update |
 * | deleted  | getOriginal() at delete time  | null                         |
 * | restored | null                          | full attributes after restore|
 *
 * `restored` only fires for models that use `SoftDeletes`. Models
 * without `SoftDeletes` never raise the event; the registration is
 * guarded by a `class_uses_recursive` check so the trait is safe to
 * apply to any Eloquent model.
 *
 * ## Tenant resolution
 *
 * The {@see TenantHint} the trait passes to the port is resolved in
 * this order:
 *  1. The subject model's own `organization_id` (filled in by
 *     {@see BelongsToTenant} on create, or set explicitly by a
 *     seeder / factory). This is the common case for tests and
 *     seeders, and it is also the case that matters in production:
 *     by the time the `created` event fires, the model already
 *     carries its tenant.
 *  2. The subject's `property_id`, when the table has the column.
 *  3. If the subject has no `organization_id` column at all (e.g.
 *     a future auditable model that is not tenant-scoped), the
 *     active `OrganizationContext` is consulted.
 *  4. If neither is present, the trait refuses to write the audit
 *     row and throws. The same fail-loud rule the platform applies
 *     to every other tenant-scoped write.
 *
 * ## Sensitive attributes
 *
 * Models that carry sensitive columns (passwords, 2FA secrets, PII
 * blobs, encrypted casts) override {@see self::auditExclude()} to
 * return the attribute names that must not appear in the audit log.
 * The default is an empty list (nothing excluded). Membership is
 * the first opt-in and its columns are all safe; future models add
 * the override.
 *
 * ## Transactional semantics
 *
 * The event handlers run inside the same database transaction as the
 * source change. If the {@see Auditor} throws, the source transaction
 * is rolled back; there is no "change committed, audit row missing"
 * outcome. Callers that mutate auditable models WITHOUT an explicit
 * `DB::transaction()` therefore rely on the implicit per-request
 * transaction; in practice the entire HTTP request is wrapped by the
 * framework.
 *
 * ## What is NOT captured
 *
 * Raw SQL via `DB::table(...)->update(...)` / `->delete(...)` bypasses
 * Eloquent events and therefore this trait. This is consistent with
 * the project-wide rule (see `docs/ARCHITECTURE.md` and
 * `tests/Feature/Platform/Tenancy/BelongsToTenantTest`) that Eloquent
 * is the only supported write path.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(static function (Model $model): void {
            $tenant = self::resolveTenant($model);
            $change = SubjectChange::crud(
                subjectId: (int) $model->getKey(),
                subjectType: $model::class,
                oldValues: null,
                newValues: self::filterForAudit($model, $model->getAttributes()),
            );
            app(Auditor::class)->record(AuditEvent::Created, $change, $tenant);
        });

        static::updated(static function (Model $model): void {
            // Eloquent fires `updated` for every `save()` that changed
            // at least one column. We do not call `wasChanged()` here
            // because the audit log is expected to record every save,
            // even "no-op" ones (they indicate a user click that hit
            // a stale value, which is interesting for the viewer).
            $tenant = self::resolveTenant($model);
            $change = SubjectChange::crud(
                subjectId: (int) $model->getKey(),
                subjectType: $model::class,
                oldValues: self::filterForAudit($model, $model->getOriginal()),
                newValues: self::filterForAudit($model, $model->getAttributes()),
            );
            app(Auditor::class)->record(AuditEvent::Updated, $change, $tenant);
        });

        static::deleted(static function (Model $model): void {
            $tenant = self::resolveTenant($model);
            $change = SubjectChange::crud(
                subjectId: (int) $model->getKey(),
                subjectType: $model::class,
                oldValues: self::filterForAudit($model, $model->getOriginal()),
                newValues: null,
            );
            app(Auditor::class)->record(AuditEvent::Deleted, $change, $tenant);
        });

        // `restored` is only a valid event on models that use
        // `SoftDeletes`. Calling `static::restored(...)` on a model
        // without the trait throws `BadMethodCallException` because
        // the event is registered by the trait, not by Eloquent
        // itself. Guard the registration so the trait is safe to
        // apply to any Eloquent model.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(static function (Model $model): void {
                $tenant = self::resolveTenant($model);
                $change = SubjectChange::crud(
                    subjectId: (int) $model->getKey(),
                    subjectType: $model::class,
                    oldValues: null,
                    newValues: self::filterForAudit($model, $model->getAttributes()),
                );
                app(Auditor::class)->record(AuditEvent::Restored, $change, $tenant);
            });
        }
    }

    /**
     * List of attribute names that must NOT appear in the audit log
     * for this model. Override per-model to redact sensitive columns
     * (passwords, 2FA secrets, encrypted blobs, PII). Return `['*']`
     * to redact everything; return `[]` (the default) to keep all
     * attributes.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return [];
    }

    /**
     * Build a {@see TenantHint} for the subject. See the class-level
     * docblock for the resolution order.
     */
    private static function resolveTenant(Model $model): TenantHint
    {
        $orgId = $model->getAttribute('organization_id');
        if (is_int($orgId) && $orgId > 0) {
            $propertyId = $model->getAttribute('property_id');
            return new TenantHint(
                organizationId: $orgId,
                propertyId: is_int($propertyId) && $propertyId > 0 ? $propertyId : null,
            );
        }

        // Subject has no tenant column populated. Fall back to the
        // active OrganizationContext so the adapter stays usable
        // from contexts where the subject is a cross-tenant record
        // (none today, but the seam is in place).
        $current = app(OrganizationContext::class)->current();
        if ($current !== null) {
            return new TenantHint(
                organizationId: $current->organizationId,
                propertyId: $current->propertyId,
            );
        }

        throw new RuntimeException(sprintf(
            'Cannot audit %s: the subject has no organization_id and '
            .'no OrganizationContext is active. Either add the '
            .'BelongsToTenant trait, set organization_id explicitly, '
            .'or wrap the call in OrganizationContext::swap().',
            $model::class,
        ));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function filterForAudit(Model $model, array $values): array
    {
        $exclude = method_exists($model, 'auditExclude')
            ? $model->auditExclude()
            : [];

        if ($exclude === []) {
            return $values;
        }
        if ($exclude === ['*']) {
            return [];
        }

        return array_diff_key($values, array_flip($exclude));
    }
}