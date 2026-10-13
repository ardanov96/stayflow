# Tenancy

Status: **accepted**; design and invariants are fixed by
ADR-0003 (single-database multi-tenancy) and tested by
`tests/Feature/Platform/Tenancy/*`. This document is the
human-readable policy that the code enforces.

## 1. Hierarchy

```
Organization (tenant root)        <-- 1 row per customer account
  +-- Property (sub-tenant)        <-- 1+ rows per hotel/villa inside the org
        +-- everything else         <-- RatePlan, RoomType, Reservation, ...
```

Every tenant-owned table carries `organization_id` (NOT NULL, indexed,
foreign key to `organizations.id` with `RESTRICT ON DELETE`). Composite
uniqueness always includes `organization_id` so the same logical key
(e.g. a slug) is unique per tenant, not globally.

`organization_id` is filled in by the `BelongsToTenant` trait on
create (the active `OrganizationContext` is the source) and is the
filter applied on every read by the same trait's global scope.

`Property` is created with a stub row in P1-01 (P1-06 owns the full
CRUD). The stub is needed so the FK from `properties` and
`memberships.property_id` has a target and so the inventory engine in
P1-08 has somewhere to hang from.

## 2. Why in-house, not stancl/tenancy

`docs/TECH_STACK.md` lists "in-house global scope trait" as the
preferred approach. ADR-0003 also commits us to the
`organization_id` global-scope model. The dependency and module
maturation costs of stancl/tenancy in single-database mode are not
worth a third-party abstraction for a 30-line scope class.

## 3. Public types

| Type | Lives at | Purpose |
|---|---|---|
| `TenantContext` (immutable VO) | `App\Modules\Platform\Domain\Tenancy\TenantContext` | Holds `organizationId` and optional `propertyId`. Rejects non-positive ids at construction. Pure PHP, no Eloquent. |
| `OrganizationContext` (interface) | `App\Modules\Platform\Contracts\OrganizationContext` | The cross-module entry point. Methods: `current(): ?TenantContext`, `swap(TenantContext, callable): mixed`, `without(callable): mixed`. |
| `OrganizationContextManager` (impl) | `App\Modules\Platform\Application\OrganizationContextManager` | Stack-based default implementation. Pure PHP, no Eloquent. |
| `BelongsToTenant` (trait) | `App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant` | Opt-in for Eloquent models. Auto-fills `organization_id` on create from the active context. Registers `BelongsToTenantScope`. |
| `BelongsToTenantScope` (Scope) | `App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope` | Filters by `organization_id` and (if the table has the column) `property_id`. Returns 0 rows when no context is active. |
| `TenantCache` | `App\Modules\Platform\Cache\TenantCache` | Wraps the cache repository; prefixes keys with `org:{organizationId}:` when a context is active. Property-level isolation is the caller's responsibility (include `property_id` in the key). |
| `PropagatesTenantContext` (queue middleware) | `App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext` | Pushes a `TenantContext` onto the worker's stack for the duration of the job. |
| `SetTenantContext` (HTTP middleware) | `App\Modules\Platform\Http\Middleware\SetTenantContext` | Reads `X-Organization-Id` and `X-Property-Id` request headers. **Dev-only**; replaced by an auth-aware resolver in P1-02 (Identity). |
| `TenantLogProcessor` (Monolog processor) | `App\Modules\Platform\Logging\TenantLogProcessor` | Adds `organization_id` and `property_id` to every log record's `extra` on the `json` log channel. Closes the deferred P0-07 follow-up. |

## 4. How the scope behaves

| Situation | Result |
|---|---|
| Read with a `TenantContext` whose `organizationId` matches the row | Row returned |
| Read with a `TenantContext` whose `organizationId` does NOT match | Row not returned |
| Read with `propertyId` set and the table has a `property_id` column | Filtered to that property too |
| Read with no `TenantContext` | Zero rows. The scope adds `WHERE 1 = 0`. This is the defense-in-depth default: tenant tables must never leak when no context is active. |
| Create with no `TenantContext` and no explicit `organization_id` | `RuntimeException` at save time. Lends a hand in catching a missing middleware. |
| Create with a `TenantContext` and no explicit `organization_id` | `organization_id` auto-filled from the context. |
| Create with an explicit `organization_id` (system seeders, test fixtures) | Explicit value wins; the context is ignored. |
| Raw SQL via `DB::table()` | Bypasses the scope. The developer MUST add `WHERE organization_id = ...` explicitly. Per `ENGINEERING_STANDARDS.md` section 2.8 raw SQL is reviewed at PR time. |
| `Model::withoutGlobalScope(BelongsToTenantScope::class)` | Bypasses the scope for that one query. Reserved for system jobs (nightly billing, retention cleanup) and for tests. |

The membership table's `(user_id, organization_id, property_id)`
uniqueness uses a stored generated column `property_id_key =
COALESCE(property_id, 0)` so the `UNIQUE` index can include the
"all properties in this org" case (`property_id IS NULL`) without
letting two of them in. The sentinel is 0 because property ids
auto-increment from 1.

## 5. Test surface

| Test file | What it covers |
|---|---|
| `tests/Unit/Platform/Domain/Tenancy/TenantContextTest` | VO construction, immutability, equality, error paths. |
| `tests/Unit/Platform/Application/OrganizationContextManagerTest` | Stack semantics, swap/without, exception safety. |
| `tests/Unit/Platform/Cache/TenantCacheTest` | Key prefixing, per-org visibility, pass-through without context, remember-miss. |
| `tests/Unit/Platform/Logging/TenantLogProcessorTest` | extra.organization_id, extra.property_id, no-op without context, preserves existing extra. |
| `tests/Unit/Platform/Jobs/Middleware/PropagatesTenantContextTest` | Middleware sets context for the job, restores previous context, no context -> worker runs without. |
| `tests/Feature/Platform/Tenancy/BelongsToTenantTest` | Filter on read, block on update, block on delete, auto-fill on create, explicit override, throws without context, withoutGlobalScope escape hatch. |
| `tests/Feature/Platform/Http/SetTenantContextMiddlewareTest` | X-Organization-Id header sets context, missing header is no-op, X-Property-Id optional, validation rejects bad input, no state leak. |
| `tests/Feature/Platform/Tenancy/TenantIsolationTest` | The end-to-end BACKLOG acceptance: cross-tenant read/update/delete blocked across Eloquent, cache, jobs, and HTTP. |
| `tests/Feature/Platform/Tenancy/TenantTableArchTest` | The CI gate: every Eloquent model whose table has `organization_id` MUST use the `BelongsToTenant` trait. Adding a new tenant table without the trait fails the build. |

## 6. What P1-02 changes

P1-02 (Identity and 2FA) replaces the dev-only `SetTenantContext`
middleware. The new resolver will:
1. Read the authenticated user from the session / token.
2. Look up the user Memberships.
3. Pick the current organization (from session, route param, or
   user preference).
4. Construct a `TenantContext` and call `OrganizationContext::swap`
   for the request lifetime.

After P1-02 the `X-Organization-Id` header path is removed (or
gated to internal service-to-service calls only). Everything below
the middleware is unchanged.

## 7. Anti-patterns

These trip the tests or are documented as forbidden by the
existing arch tests:

- Importing a tenant model across module boundaries. The
  `tests/Arch/ModuleBoundariesTest` blocks `App\Modules\Platform`
  from `use`ing `App\Modules\Catalog` (and the symmetric rule for
  every other module). Cross-module data joins belong in the
  `Contracts/` namespace or in a `Application/` query class.
- Eloquent models under `Domain/` or `Application/`. The
  `tests/Arch/EloquentBoundariesTest` blocks `Model` imports from
  those sub-trees. The Application layer is pure PHP.
- `App\Models\User` is the only model in `app/Models/`. P1-02 will
  move it into `App\Modules\Platform\Infrastructure\Models\User`
  and update every reference; until then, Platform's Membership
  model imports it directly because it is a system table shared
  by the Laravel scaffolding.

## 8. Operational notes

- The trait short-circuits to `1 = 0` when no context is active.
  This means every tenant-scoped query in a fresh PHP process
  before a context is set returns zero rows. That is the desired
  security property: a forgotten `swap` is loudly visible as
  empty results, and the `RuntimeException` at create time catches
  it on the write path.
- The scope caches `Schema::hasColumn($table, "property_id")`
  per table on first call. This is a micro-optimization for hot
  read paths and avoids per-query schema introspection.
- `SetTenantContext` is registered in the `web` middleware group
  in `bootstrap/app.php`. It is a no-op when no header is present,
  so public routes (e.g. `/`, `/locale`) keep working. P1-02
  replaces this registration with an auth-aware resolver.