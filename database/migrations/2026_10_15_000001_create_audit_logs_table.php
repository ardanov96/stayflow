<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-04 (Audit log): append-only event log for state changes on auditable
 * models across the platform. The table is tenant-scoped (organization_id
 * is non-null and the `AuditLog` Eloquent model uses the
 * `BelongsToTenant` trait), so the `TenantTableArchTest` automatically
 * requires the trait here and `BelongsToTenantScope` filters reads.
 *
 * Column policy:
 *  - organization_id, property_id: tenant context, taken from the active
 *    `OrganizationContext` at write time. `property_id` is null when the
 *    event is organization-scoped (e.g. a settings change) so the same
 *    row is visible regardless of which property-scoped context reads it.
 *  - actor_id: `users.id` for the principal that triggered the change, or
 *    null for system events (console jobs, webhooks). Soft FK; `SET NULL`
 *    so a hard-deleted user does not orphan the audit trail.
 *  - event: short code from {@see \App\Modules\Platform\Domain\AuditLog\AuditEvent}
 *    for CRUD verbs, or a free-form string (e.g. `rate.bulk.updated`) up to
 *    64 chars for custom events written through `Auditor::recordCustom`.
 *  - subject_type / subject_id: identifies the audited row. `subject_type`
 *    is the FQCN of the model class (no FK by design; the subject row
 *    can be hard-deleted and the log entry must still be meaningful).
 *  - old_values / new_values: full Eloquent attribute arrays at the time
 *    of the change, JSON-encoded. Null when not applicable (create has no
 *    old, delete has no new). Sensitive attributes (passwords, 2FA
 *    secrets, recovery codes) are filtered by the adapter before write.
 *  - metadata: free-form JSON bag for future fields (request_id, ip,
 *    user_agent). Empty in this PR; the contract is in place so the HTTP
 *    layer can populate it without a migration.
 *  - created_at: the only timestamp. `updated_at` is intentionally absent
 *    so any `UPDATE` on this table is a schema violation.
 *
 * Index policy: the three query shapes that matter for the viewer and the
 * "last changes by subject" lookup are
 *   1. recent events for an organization (with optional property scope),
 *   2. all events for one subject row (admin "show history" view),
 *   3. all events by one actor (security review of an ex-employee).
 * A property-only time index supports the property-scoped global scope
 * without a full table scan when the active context has a `property_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('event', 64);
            $table->string('subject_type', 255);
            $table->unsignedBigInteger('subject_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at'], 'audit_logs_org_time_idx');
            $table->index(['property_id', 'created_at'], 'audit_logs_property_time_idx');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_idx');
            $table->index(['actor_id', 'created_at'], 'audit_logs_actor_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};