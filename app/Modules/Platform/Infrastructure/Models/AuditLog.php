<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Models;

use App\Models\User;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only audit trail for state changes across the platform.
 * Rows are written through {@see \App\Modules\Platform\Application\AuditLog\Ports\Auditor}
 * (the {@see \App\Modules\Platform\Infrastructure\AuditLog\EloquentAuditor}
 * adapter) and are never updated or deleted through the app.
 *
 * Append-only enforcement is at the Application boundary:
 *  - `save()` rejects updates to existing rows (the only legal `save()` is
 *    the initial insert of a freshly-constructed model).
 *  - `delete()` throws unconditionally. This also covers `forceDelete()`
 *    because Eloquent routes it through `delete()`.
 *  - Raw SQL (`DB::table('audit_logs')->update(...)`) bypasses the model
 *    and is documented as a contract violation. A future task may add a
 *    database trigger if a stronger guarantee is needed.
 *
 * The `BelongsToTenant` trait puts this model under the standard tenant
 * global scope, so `TenantTableArchTest` automatically enforces that
 * the trait is present here and that reads filter by the active
 * `OrganizationContext`. Cross-tenant reads must go through
 * `withoutGlobalScope(BelongsToTenantScope::class)` (used by the
 * P2-15 admin viewer).
 *
 * `subject_type` / `subject_id` deliberately do NOT have a foreign key
 * to any concrete table; the subject can be hard-deleted after the
 * audit row is written and the audit row must remain queryable. The
 * `subject()` morphTo returns null for hard-deleted subjects.
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    use BelongsToTenant;

    /**
     * The model is single-timestamped: `created_at` only. There is no
     * `updated_at` column on the table (the migration omits it on
     * purpose so that an `UPDATE` is a schema violation) and we tell
     * Eloquent not to look for one. The DB default `useCurrent()`
     * fires on raw inserts as a safety net.
     */
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'property_id',
        'actor_id',
        'event',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The Property model lives in the Catalog module so the relation is
     * NOT declared here (Platform must not import Catalog per
     * `tests/Arch/ModuleBoundariesTest`). Viewers look up the property
     * by id through the Catalog `Property` Contracts port or via a
     * dedicated query class.
     */

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Append-only: block updates to existing rows. The only legal call
     * is the implicit `save()` Eloquent makes during `Model::create()`,
     * where `$this->exists` is false.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException(
                'AuditLog entries are append-only and cannot be updated.'
            );
        }

        return parent::save($options);
    }

    /**
     * Append-only: block deletes unconditionally. `forceDelete()` goes
     * through this method too, so the override covers both soft and
     * hard delete attempts.
     */
    public function delete(): never
    {
        throw new RuntimeException(
            'AuditLog entries are append-only and cannot be deleted.'
        );
    }
}