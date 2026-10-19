<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\AuditLog;

use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\AuditLog\AuditEvent;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * The default {@see Auditor} adapter. Translates a {@see SubjectChange}
 * and a {@see TenantHint} into a row in the `audit_logs` table.
 *
 * Responsibilities (in this order):
 *  1. Validate the {@see TenantHint} (positive organization id, etc).
 *  2. Capture the actor. `Auth::id()` is null for system events
 *     (console jobs, webhooks, queued work) and an int for user-driven
 *     changes. We do not call `Auth::check()` first because `Auth::id()`
 *     already returns null when no user is bound.
 *  3. Build the row. `organization_id` and `property_id` come straight
 *     from the hint; the audit log is an org-level record even when
 *     the change is property-scoped.
 *  4. Insert via Eloquent. The `AuditLog` model is append-only (see
 *     {@see AuditLog::save()}
 *     and ::delete()), so this insert is the only write path.
 *
 * The insert happens inside the same transaction as the source change
 * because the caller is the `Auditable` trait's Eloquent event handler
 * (which fires synchronously after the source save). If the insert
 * fails, the source transaction is rolled back: there is no "write
 * succeeded but audit log missing" outcome.
 *
 * The {@see OrganizationContext} is
 * deliberately NOT consulted here: the hint the trait passes is the
 * source of truth, and the adapter is callable from any context
 * (HTTP, queue, console, seeder, test) without depending on a
 * context being on the stack.
 */
final class EloquentAuditor implements Auditor
{
    public function record(AuditEvent $event, SubjectChange $change, TenantHint $tenant): void
    {
        $this->write($event->value, $change, $tenant);
    }

    public function recordCustom(string $event, SubjectChange $change, TenantHint $tenant): void
    {
        $this->validateCustomEvent($event);
        $this->write($event, $change, $tenant);
    }

    private function write(string $event, SubjectChange $change, TenantHint $tenant): void
    {
        AuditLog::create([
            'organization_id' => $tenant->organizationId,
            'property_id' => $tenant->propertyId,
            'actor_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $change->subjectType,
            'subject_id' => $change->subjectId,
            'old_values' => $change->oldValues,
            'new_values' => $change->newValues,
            'metadata' => $change->metadata,
        ]);
    }

    /**
     * Custom event names go straight into the `event` VARCHAR(64) column
     * so we enforce the column width here, at the seam, with a clear
     * error message. The convention is "{module}.{action}.{past-tense}"
     * (e.g. `rate.bulk.updated`, `member.role.changed`).
     */
    private function validateCustomEvent(string $event): void
    {
        if ($event === '') {
            throw new RuntimeException('Custom audit event name must be a non-empty string.');
        }
        if (strlen($event) > 64) {
            throw new RuntimeException(sprintf(
                'Custom audit event name must be at most 64 characters; got %d for "%s...".',
                strlen($event),
                substr($event, 0, 32),
            ));
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $event) === 1) {
            throw new RuntimeException('Custom audit event name must not contain control characters.');
        }
    }
}
