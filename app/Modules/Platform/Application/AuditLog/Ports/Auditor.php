<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\AuditLog\Ports;

use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Domain\AuditLog\AuditEvent;
use App\Modules\Platform\Infrastructure\AuditLog\EloquentAuditor;
use App\Modules\Platform\Infrastructure\PlatformServiceProvider;

/**
 * The single entry point other modules use to record an audit event.
 * Concrete adapter is bound in {@see PlatformServiceProvider}
 * to {@see EloquentAuditor}.
 *
 * Per AGENTS.md §3 (no cross-module Eloquent coupling) and the
 * `tests/Arch/EloquentBoundariesTest` rule (Application sub-trees never
 * import Eloquent), this port is pure PHP: it accepts a {@see SubjectChange}
 * value object and an {@see AuditEvent} (or a free-form string for custom
 * events). The Infrastructure layer is the only place that touches the
 * `audit_logs` table.
 *
 * Contract:
 *  - The Auditor MUST require a {@see TenantHint} for every write.
 *    The hint is the only way the Application layer expresses which
 *    tenant the audit row belongs to; the adapter does not consult
 *    the `OrganizationContext` itself. This keeps the contract
 *    usable from any caller (HTTP request, queued job, seeder, test)
 *    without depending on the context being on the stack.
 *  - The Auditor MUST capture the current actor (`Auth::id()`) at the
 *    time of the call. For system events (queued jobs, webhooks, console
 *    commands) the actor is null; the caller does not have to opt in.
 *  - The Auditor MUST write the row inside the same database transaction
 *    as the source change. It does not call `afterCommit` on purpose:
 *    rolling back the source change MUST also discard the audit row.
 */
interface Auditor
{
    /**
     * Record a CRUD-shaped event (`created`, `updated`, `deleted`,
     * `restored`) for the given subject. `oldValues` / `newValues`
     * follow the semantics of the {@see AuditEvent}:
     *  - `Created`:  oldValues is null, newValues holds the full Eloquent
     *                attribute set after creation.
     *  - `Updated`:  oldValues holds the Eloquent "original" array BEFORE
     *                the change, newValues holds the current attributes.
     *  - `Deleted`:  oldValues holds the Eloquent "original" array at
     *                delete time, newValues is null.
     *  - `Restored`: oldValues is null (the soft-deleted state is not a
     *                "real" old version), newValues holds the attributes
     *                after the restore.
     */
    public function record(
        AuditEvent $event,
        SubjectChange $change,
        TenantHint $tenant,
    ): void;

    /**
     * Record a custom, non-CRUD event (e.g. `rate.bulk.updated`,
     * `member.role.changed`, `payment.refund.issued`). The `event`
     * argument is a free-form string up to 64 chars (the column width
     * in the migration). Callers are responsible for keeping the
     * namespace consistent (`{module}.{action}.{past-tense}` is the
     * convention used elsewhere in the project).
     *
     * The `metadata` bag is a free-form JSON array that the caller can
     * populate with whatever context the custom event needs (request id,
     * IP, a list of affected ids, etc.). It is stored as-is in
     * `audit_logs.metadata`.
     */
    public function recordCustom(
        string $event,
        SubjectChange $change,
        TenantHint $tenant,
    ): void;
}
