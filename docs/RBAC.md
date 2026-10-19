# Role-Based Access Control (RBAC)

Status: **accepted** for P1-03. Design, entities, and invariants are implemented in
`app/Modules/Platform/{Domain,Application,Infrastructure}/Identity/*`. Tested by
`tests/Unit/Platform/Domain/Identity/*` and `tests/Feature/Platform/Identity/*`.
This document is the human-readable policy enforced by the code.

## 1. The Six Pilot Roles

Roles are stored as backed strings on the `memberships.role` column (`VARCHAR(32)`).
They represent a closed domain enum (`App\Modules\Platform\Domain\Identity\Role`):

| Role | Scope | Key Responsibilities | 2FA Required |
|---|---|---|---|
| `owner` | Organization-wide | Super-user for the entire organization. Manages billing, invites staff, views audit logs, configures settings. | Yes |
| `manager` | Property-scoped or Org-wide | General manager for a property (or group). Full operational authority over catalog, rates, inventory, reservations, folios, refunds, staff, settings. No billing or audit log read access. | Yes |
| `front_desk` | Property-scoped | Front office staff. Creates/updates reservations, performs check-in and check-out, posts folio room and incidental charges, manages guest profiles. Cannot refund or void payments/folios. | No |
| `housekeeping` | Property-scoped | Room attendants and housekeeping supervisors. Reads room status, marks clean/dirty/inspected, manages housekeeping tasks. Cannot view or edit rates or reservations. | No |
| `revenue` | Property-scoped or Org-wide | Revenue manager. Manages rate plans, daily prices, minimum stays, CTA/CTD, stop-sells, inventory materialization, and reports. Cannot invite users or charge folios. | No |
| `marketing` | Property-scoped or Org-wide | Marketing coordinator. Manages promotional codes, special offers, booking engine content, guest CRM segments, and marketing reports. Cannot create reservations or manage room rates. | No |

## 2. Organization-Wide vs. Property-Scoped Authority

A user may hold one or more `Membership` records in the same organization:

- **Org-Wide Authority (`property_id === null`):**
  The permissions of the role apply across every current and future property in the organization.
  `Role::Owner` is always org-wide (`Role::isOrgWide() === true`).
- **Property-Scoped Authority (`property_id === $propertyId`):**
  The permissions apply only when the active context matches that specific property.
- When an operation is checked without property context (`$propertyId === null`), any active membership in the organization satisfies the check.

## 3. Fine-Grained Permissions & RolePermissionMatrix

The domain defines 24 granular permissions under `App\Modules\Platform\Domain\Identity\Permission`:

- **Users & Roles:** `users.invite`, `users.remove`, `roles.assign`
- **Catalog:** `properties.read`, `properties.update`, `room_types.manage`, `policies.manage`
- **Inventory:** `rate_plans.read`, `rate_plans.manage`, `inventory.manage`, `restrictions.manage`
- **Reservations:** `reservations.read`, `reservations.create`, `reservations.update`, `reservations.cancel`, `reservations.check_in`, `reservations.check_out`
- **Folio & Payments:** `folio.read`, `folio.charge`, `folio.refund`, `payments.refund`
- **Housekeeping:** `housekeeping.read`, `housekeeping.manage`
- **Guests (CRM):** `guests.read`, `guests.write`
- **Booking Engine & Promos:** `promos.manage`, `booking_engine.read`, `booking_engine.manage`
- **Reporting:** `reports.read`
- **Platform (Admin):** `audit.read`, `settings.manage`, `billing.manage`

The sealed singleton `App\Modules\Platform\Domain\Identity\RolePermissionMatrix` maps each `Role` to its allowed permissions:

- `Owner` has the complete superset of all permissions.
- `Manager` has all permissions except `audit.read` and `billing.manage`.
- `FrontDesk` has operational permissions (reservations, check-in/out, folio charge), but no refund or rate management permissions.
- `Housekeeping` has housekeeping read/manage and property read only.
- `Revenue` has rate plans, inventory, restrictions, reports, and room types.
- `Marketing` has promos, booking engine, guest write, and reports.

## 4. Application Port: RoleChecker

The application seam for authorization queries is `App\Modules\Platform\Application\Identity\Ports\RoleChecker`:

```php
interface RoleChecker
{
    public function membershipsIn(User $user, int $organizationId): Collection;
    public function hasRole(User $user, int $organizationId, ?int $propertyId, Role $role): bool;
    public function hasPermission(User $user, int $organizationId, ?int $propertyId, Permission $permission): bool;
}
```

The concrete implementation `App\Modules\Platform\Infrastructure\Identity\EloquentRoleChecker` is bound as a singleton in `PlatformServiceProvider`.

## 5. Role Mutations: AssignRole & RevokeRole

Actions in `App\Modules\Platform\Application\Identity` handle role assignments and audit them:

- `AssignRole`: Updates or inserts a `Membership` record for `(user, organization, property)`. Emits `member.role.assigned` or `member.role.changed` to the append-only audit log.
- `RevokeRole`: Soft-deletes the `Membership` record. Emits `member.role.revoked` to the audit log. Returns `false` idempotently if no membership existed.

## 6. HTTP Authorization & 403 Enforcement

Routes and endpoints enforce permissions using the `permission:<permission_name>` middleware (`App\Modules\Platform\Http\Middleware\AuthorizePermission`):

1. Unauthenticated requests abort with **401 Unauthorized**.
2. If the user lacks the permission in the active tenant context:
   - A structured warning is written to Monolog (`Forbidden: user lacks required permission`) with `user_id`, `organization_id`, `property_id`, `permission`, and `path`.
   - An `auth.forbidden` event is recorded in the append-only `audit_logs` table via the `Auditor` port.
   - Aborts with **403 Forbidden**.
3. Laravel Gate integration is wired via `Gate::before` in `PlatformServiceProvider::boot()`, enabling `$user->can('reservations.create')` or `Gate::allows('reservations.create')`.

## 7. Acceptance Mapping (BACKLOG P1-03)

| BACKLOG bullet | Implementation & Test Proof |
|---|---|
| Roles per property (owner, manager, front_desk, housekeeping, revenue, marketing) | Implemented in `Role` enum, `Membership` model with typed cast, `MembershipFactory`. Covered by `tests/Unit/Platform/Domain/Identity/RoleTest.php`. |
| Permission matrix test covers every route/action | Implemented in `RolePermissionMatrix`. Exhaustively verified in `tests/Unit/Platform/Domain/Identity/RolePermissionMatrixTest.php`. |
| Forbidden access returns 403 and is logged | Implemented in `AuthorizePermission` middleware with `Log::warning()` and `Auditor::recordCustom('auth.forbidden', ...)`. Tested in `tests/Feature/Platform/Identity/AuthorizePermissionMiddlewareTest.php`. |
| Policies & Gate integration | Registered in `PlatformServiceProvider::boot()`, tested in `AuthorizePermissionMiddlewareTest::it wires Gate::allows()`. |
| Role management actions | `AssignRole` and `RevokeRole` actions tested in `tests/Feature/Platform/Identity/AssignAndRevokeRoleTest.php`. |
