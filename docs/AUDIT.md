# Audit log

Status: **accepted** for P1-04. Design and invariants are fixed by the
port contract (see `app/Modules/Platform/Application/AuditLog/Ports/Auditor.php`)
and the migration (`database/migrations/2026_10_15_000001_create_audit_logs_table.php`).
Tested by `tests/Unit/Platform/{Domain,Application}/AuditLog/*` and
`tests/Feature/Platform/AuditLog/*` (PHPStan clean, Pest green on
`feat/P1-04-audit-log`). This document is the human-readable policy
the code enforces.

## 1. What is captured

Every state change to an opt-in Eloquent model is recorded as a row in
the `audit_logs` table. Each row carries:

| Column | Source | Notes |
|---|---|---|
| `organization_id` | subject model (`organization_id`) or active `OrganizationContext` | Always set. The audit log is tenant-scoped. |
| `property_id` | subject model (`property_id`) or context | Nullable; null means org-scoped event. |
| `actor_id` | `Auth::id()` | Null for system events (console, jobs, webhooks). |
| `event` | one of `created`, `updated`, `deleted`, `restored`, or a custom string for non-CRUD events | Custom names follow `{module}.{action}.{past-tense}` (e.g. `rate.bulk.updated`). Max 64 chars. |
| `subject_type` | FQCN of the model class | The morphTo target. |
| `subject_id` | model primary key | No FK by design; the subject can be hard-deleted and the audit row must remain meaningful. |
| `old_values` | `getOriginal()` before the change | Null for `created` and `restored`. |
| `new_values` | `getAttributes()` after the change | Null for `deleted`. |
| `metadata` | caller-supplied bag | Used for custom events; null for CRUD events. |
| `created_at` | Eloquent `freshTimestamp()` | The only timestamp. There is no `updated_at` column. |

## 2. Public types

| Type | Lives at | Purpose |
|---|---|---|
| `AuditEvent` (enum) | `App\Modules\Platform\Domain\AuditLog\AuditEvent` | The four CRUD verb codes (`Created`, `Updated`, `Deleted`, `Restored`). Pure PHP. |
| `SubjectChange` (VO) | `App\Modules\Platform\Application\AuditLog\SubjectChange` | Holds `subjectId`, `subjectType`, optional `oldValues`, `newValues`, `metadata`. Pure PHP. |
| `TenantHint` (VO) | `App\Modules\Platform\Application\AuditLog\TenantHint` | Holds the `organizationId` (and optional `propertyId`) the audit row must carry. Pure PHP. |
| `Auditor` (port) | `App\Modules\Platform\Application\AuditLog\Ports\Auditor` | `record(AuditEvent, SubjectChange, TenantHint)` and `recordCustom(string, SubjectChange, TenantHint)`. Every other module writes through this port. |
| `EloquentAuditor` (adapter) | `App\Modules\Platform\Infrastructure\AuditLog\EloquentAuditor` | The default `Auditor` implementation. Resolves the actor from `Auth::id()`, persists via the append-only `AuditLog` model. |
| `AuditLog` (model) | `App\Modules\Platform\Infrastructure\Models\AuditLog` | The Eloquent row. Uses `BelongsToTenant`; overrides `save()` to reject updates to existing rows and `delete()` to throw. |
| `Auditable` (trait) | `App\Modules\Platform\Infrastructure\Concerns\Auditable` | Opt-in for Eloquent models. Hooks `created` / `updated` / `deleted` / `restored` and delegates to the `Auditor` port. |

## 3. How to opt a model in

One line per model:

```php
use App\Modules\Platform\Infrastructure\Concerns\Auditable;

class Membership extends Model
{
    use BelongsToTenant; // already required for tenant scoping
    use Auditable;       // one line; the rest of the class is unchanged
    use SoftDeletes;     // SoftDeletes is required for `restored` to fire
}
```

The trait reads `organization_id` and `property_id` from the model
at event time, so it works with any `BelongsToTenant` model. If a
model does not have `organization_id` (no future case in the
codebase, but the seam is in place), the active `OrganizationContext`
is consulted. If neither is present, the trait throws.

### 3.1 Sensitive attributes

Models that carry sensitive columns (passwords, 2FA secrets, encrypted
PII) override `auditExclude()`:

```php
class User extends Model
{
    use BelongsToTenant;
    use Auditable;

    protected function auditExclude(): array
    {
        return ['password', 'two_factor_secret', 'two_factor_recovery_codes'];
    }
}
```

Return `['*']` to redact everything; return `[]` (the default) to
keep all attributes. The filter runs on `getAttributes()` and
`getOriginal()` so Eloquent encrypted casts never reach the audit
log unless explicitly opted in.

### 3.2 Custom events

For non-CRUD events (rate bulk updates, role changes, payment
refunds), call the `Auditor` port directly:

```php
app(Auditor::class)->recordCustom(
    'rate.bulk.updated',
    SubjectChange::custom(
        subjectId: $ratePlan->id,
        subjectType: RatePlan::class,
        metadata: ['affected_dates' => 30, 'reason' => 'seasonal'],
    ),
    new TenantHint(organizationId: $ratePlan->organization_id, propertyId: $ratePlan->property_id),
);
```

Custom event names go into the `event` VARCHAR(64) column. The adapter
enforces the width and rejects control characters at the seam.

## 4. Append-only enforcement

`AuditLog` overrides two methods:

- `save()` throws `RuntimeException` if `$this->exists` is true. The
  only legal `save()` is the implicit one Eloquent makes during
  `Model::create()`, where the model is brand new.
- `delete()` throws `RuntimeException` unconditionally. `forceDelete()`
  routes through `delete()`, so the override covers hard deletes too.

Raw SQL via `DB::table('audit_logs')->update(...)` bypasses the model
and is a documented contract violation. A future hardening task may
add a database trigger to close that hole; for now the application
layer is the contract and review is the enforcement.

## 5. Tenant scope

The `AuditLog` model uses `BelongsToTenant`, so:

- Reads are filtered by the active `OrganizationContext` (and, if the
  context has a `propertyId`, also by that). The `TenantTableArchTest`
  enforces that the trait is present here.
- Writes auto-fill `organization_id` from the context, but the
  `EloquentAuditor` passes `organization_id` explicitly, so the
  `creating` event short-circuits.
- Cross-tenant reads use `withoutGlobalScope(BelongsToTenantScope::class)`
  - the future P2-15 admin viewer is the only place that does this.

## 6. Transactional semantics

The `Auditable` trait event handlers run inside the same database
transaction as the source change. If the `Auditor` throws (e.g. the
adapter catches a missing-tenant edge case), the source transaction
is rolled back: there is no "change committed, audit row missing"
outcome.

`RefreshDatabase` wraps each Pest test in a transaction, so a failing
test does not leave an orphan audit row in the database. Production
code relies on the framework per-request transaction or on
`DB::transaction()` blocks for multi-write flows.

## 7. What is NOT captured

- Raw SQL via `DB::table(...)->update(...)` / `->delete(...)` /
  `->truncate()`. Bypasses Eloquent events by design.
- The `Organization` model. It is the tenant root, not a tenant row;
  the trait short-circuits to "needs an active context", which
  Organization never has. Adding `use Auditable;` to Organization
  is a contract bug.
- The `LoginAttempt` table. Security events already go through the
  dedicated `LoginAttemptRecorder` port (P1-02). The generic
  `AuditLog` is for state changes on business models, not security
  audit.

## 8. Operational notes

- The trait uses `app(Auditor::class)->record(...)` rather than
  constructor-injecting the `Auditor` into the model: the `Auditor`
  is resolved at event time so tests that bind a fake `Auditor` are
  picked up automatically.
- The `EloquentAuditor` is bound as a singleton in
  `PlatformServiceProvider`. It has no constructor dependencies, so
  Laravel auto-instantiates it. The singleton binding is for
  consistency with the other Platform ports.
- Index strategy:
  - `(organization_id, created_at)` - the "recent activity for an org"
    query the viewer uses.
  - `(property_id, created_at)` - supports the property-scoped global
    scope when the active context has a `propertyId`.
  - `(subject_type, subject_id)` - "show me the history of row N".
  - `(actor_id, created_at)` - security review of an ex-employee.

## 9. Acceptance mapping (BACKLOG P1-04)

| BACKLOG bullet | Where it is implemented / tested |
|---|---|
| Record actor, subject, event, changes, timestamp for configured models | `Auditable` trait; `Auditor::record()`; `AuditLog` model. Tested by `tests/Feature/Platform/AuditLog/AuditableTest`. |
| Updates to rates, reservations, and roles appear in the log | Future: P2-01 (rate updates), P2-05 (reservations), P1-03 (role changes) all opt their models in with `use Auditable;`. The Membership opt-in in this PR is the proof of pattern. |
| Log entries cannot be edited via the app | `AuditLog::save()` throws on existing rows; `AuditLog::delete()` throws unconditionally. `tests/Feature/Platform/AuditLog/AuditLogAppendOnlyTest` covers both plus the `update()` and `forceDelete()` paths. |
| Read-only viewer in admin | Follow-up in P2-15 "activity feed" (Filament resource). Not in this PR per AGENTS.md section 4 (one task per PR). |
