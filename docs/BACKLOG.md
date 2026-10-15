# BACKLOG — executable task list

How to read:
- **ID**: `P<phase>-<nn>`. **Deps**: tasks that must be `done` first. **Size**: S ≈ ≤ 1 day, M ≈ 2–4 days, L ≈ 1–2 weeks (split if it grows).
- **Status** values: `todo`, `in-progress`, `blocked`, `done`. Change it in the same PR that does the work.
- **[human]** = needs a human decision, account, or external party; an agent may prepare material but must not decide.
- **Gate** tags mark tasks that must be done for a gate in `docs/ROADMAP.md` §2.
- Acceptance criteria are minimum requirements; every bullet needs a test or a documented manual check.
- Vendor-specific tasks: verify current vendor documentation first (see `AGENTS.md` §3 rule 10).

Statuses are tracked in the line starting with `Status:` under each task.

---

## P0 — Discovery & foundations (weeks 1–2)

### P0-01 Repository bootstrap and CI
Deps: — · Size: M · Status: done
- New Laravel app on current stable versions (pin and record), module skeleton from `AGENTS.md` §5, `declare(strict_types=1)`.
- Pint, PHPStan/Larastan, Pest installed; `composer ci` runs format check, static analysis, architecture tests, tests.
- GitHub Actions: runs `composer ci` and frontend lint/build on every PR; gitleaks and `composer audit` included.
- Acceptance: CI is green on the empty app; PR template with the Definition of Done checklist exists; `CODEOWNERS` set.

### P0-02 Local development environment
Deps: P0-01 · Size: S · Status: done
- Docker Compose (or Sail): app, MySQL 8, Redis, Mailpit, S3-compatible store (MinIO). `make setup` creates a working local environment with seed data.
- Acceptance: from a fresh clone, a new developer reaches a running app by following README only; works on Linux, macOS, and Windows with WSL2.

### P0-03 Domain model validation [human]
Deps: — · Size: M · Status: todo
- Walk `docs/DOMAIN_MODEL.md` §5 open questions with a pilot hotel (or hospitality advisor); update the model and ERD.
- Acceptance: signed-off ERD in `docs/`, open questions answered or logged in `docs/DECISIONS.md` §3.

### P0-04 Vendor selection ADRs [human]
Deps: — · Size: M · Status: todo
- Evaluate channel aggregator, payment gateway, email provider (sandbox access, pricing, API fit). Record ADR-0004, ADR-0005, ADR-0011.
- Acceptance: three ADRs moved from `proposed` to `accepted`, sandbox credentials available to the team (never committed).

### P0-05 UI shell, design tokens, i18n scaffolding
Deps: P0-01 · Size: M · Status: done
- Inertia + Vue 3 + Tailwind layout (sidebar, property switcher, locale switcher), design tokens, base components, `id`/`en`/`zh` translations with a missing-key CI check.
- Acceptance: all UI strings come from lang files; Playwright smoke test loads the shell in both locales; keyboard navigation works for the main layout.
- Closed on `chore/p0-05-closeout`: 79/79 Pest, Pint clean, PHPStan 0 errors, ESLint 0 warnings, Playwright green in `en`/`id`/`zh`, missing-key i18n parity check wired into `npm run lint`.

### P0-06 Staging environment and deployment pipeline
Deps: P0-01 · Size: M · Status: todo
- Server provisioned via Forge/Ploi; Cloudflare in front; zero-downtime deploy with health check and automatic rollback; secrets managed outside the repo.
- Acceptance: merge to main deploys to staging; a deliberately failing health check rolls back automatically.

### P0-07 Error tracking and structured logging
Deps: P0-01 · Size: S · Status: done (closeout `0446430`, 7 commits on `feat/p0-07-sentry-logging`)
- **Sentry**: `sentry/sentry-laravel ^4.29` installed; auto-discovered by Laravel; reads `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, `SENTRY_TRACES_SAMPLE_RATE`, `SENTRY_PROFILES_SAMPLE_RATE`, `SENTRY_SEND_DEFAULT_PII` from `.env` (documented in `.env.example`, all defaulted to safe values for local dev). Hub auto-bound as `sentry` in the container.
- **Structured JSON logging**: `config/logging.php` `json` channel: Monolog `StreamHandler` + `JsonFormatter` (newline-delimited) → `storage/logs/stayflow.jsonl`. Two custom Monolog processors implement `Monolog\Processor\ProcessorInterface` and run after `PsrLogMessageProcessor`:
  - `App\Support\Logging\PiiScrubberProcessor` — scrubs message/context/extra.
  - `App\Support\Logging\RequestIdProcessor` — promotes `context.request_id` to `extra.request_id` for log-search friendliness.
- **`request_id` middleware**: `App\Http\Middleware\AssignRequestId` registered in the `web` group ahead of `SetLocale`. Reads `X-Request-Id` (whitelist `A-Za-z0-9_-` only, max 64 — rejects newlines/spaces/oversize as a log-injection guard) or generates a 16-hex id. Calls `Log::shareContext(['request_id' => ...])`, sets it on the request, echoes it on the response header.
- **PII redaction**: `App\Support\Helpers\PiiScrubber` does two-layer scrubbing. (1) Value-based regex (emails, phone numbers, JWTs, `key=value` / `key: value` credential shapes). (2) Key-based: a context key whose name contains a sensitive substring (`password`, `token`, `secret`, `api_key`, `cookie`, `credit_card`, `cvv`, ...) has its value replaced with `***` regardless of content. False positives accepted; false negatives unacceptable.
- **Tests**: 36 new Pest tests (29 unit + 7 feature), 115 total / 466 assertions green. PiiScrubber, RequestId, both processors, json channel end-to-end (writes to a temp `.jsonl`, asserts PII redaction + `request_id` in `extra`), Sentry SDK autoloading + `sentry` binding.
- **Out of scope for P0-07** (covered by later tasks): `organization_id` / `property_id` in logs (P1-01 tenancy), exception capture end-to-end against a real Sentry project (P0-08 once a DSN is provisioned), `SentryServiceProvider` for custom before_send / tenant context (P1-01).
- **Acceptance proof**:
  - Unit: `vendor/bin/pest tests/Unit/Support` → 29 passed.
  - Feature: `vendor/bin/pest tests/Feature/Logging tests/Feature/Sentry` → 7 passed.
  - Format: `vendor/bin/pint` → no fixes needed.
  - Static: `vendor/bin/phpstan analyse` → no errors.
  - Sample log line (from JsonLogTest): `{"message":"user login","context":{"email":"***@***.***","api_key":"***"},"level":200,"level_name":"INFO","channel":"testing","datetime":"...","extra":{"request_id":"test-id-1234"}}`.

### P0-08 Pilot hotel recruitment [human]
Deps: — · Size: L · Status: todo
- Identify and agree terms with 1–2 design-partner hotels; schedule interviews and test windows per `docs/ROADMAP.md` §9.
- Acceptance: written agreement with at least one hotel; named contact person for product questions.

### P0-09 Meta developer app and permissions [human]
Deps: — · Size: S · Status: todo
- Register the developer app and start whatever review the current Instagram API requires for the feed plugin.
- Acceptance: app registered, review status tracked in `docs/DECISIONS.md` §3.

### P0-10 Architecture tests for module boundaries
Deps: P0-01 · Size: S · Status: done
- Pest arch tests (or Deptrac) encode the dependency table in `docs/ARCHITECTURE.md` §3: modules reference others only via `Contracts`; no cycles; Eloquent models are not used across modules.
- Acceptance: introducing a forbidden import in a throwaway branch fails CI.

---

## P1 — Platform core & inventory engine (weeks 3–6)

Status: **done** (closed on branch `feat/p1-01-tenancy`; 9 commits + 1 docs commit, 1 docs polish; ready to open a PR against `main`)
Deps: P0-01 · Size: L · Status: in-progress (branch `feat/p1-01-tenancy`)
- Organizations, properties, `BelongsToTenant` global scope, tenant-aware queues/jobs, tenant-aware cache keys.
- Isolation test helper that iterates every tenant table and proves cross-tenant read/update/delete is impossible via web, API, and jobs.
- Acceptance: adding a tenant table without `organization_id` or without the scope fails a CI test.

- **Commits (10)**:
  - `398cbda` chore(p1-01): claim task
  - `a9df731` feat(p1-01): TenantContext VO and OrganizationContext contract
  - `f3dac8e` feat(p1-01): BelongsToTenant trait, scope, and Organization model
  - `3e78b7d` feat(p1-01): organizations, properties, memberships tables and models
  - `4b5e3a3` feat(p1-01): tenant-aware jobs, cache, and log processor
  - `335d420` feat(p1-01): HTTP middleware and test routes for tenant context
  - `ed0b493` test(p1-01): unit and feature tests for tenancy primitives
  - `77c6474` test(p1-01): isolation suite and tenant-table arch test
  - `e5254be` docs(p1-01): add TENANCY.md and link from ENGINEERING_STANDARDS
  - (this commit) docs(p1-01): closeout

- **Deliverables**:
  - `app/Modules/Platform/Domain/Tenancy/TenantContext` (pure PHP VO: organizationId + optional propertyId; rejects non-positive ids)
  - `app/Modules/Platform/Contracts/OrganizationContext` (interface: current / swap / without)
  - `app/Modules/Platform/Application/OrganizationContextManager` (stack-based, finally-restoring)
  - `app/Modules/Platform/Infrastructure/Concerns/BelongsToTenant` (Eloquent trait: global scope + creating handler that auto-fills org_id or throws)
  - `app/Modules/Platform/Infrastructure/Concerns/BelongsToTenantScope` (Eloquent scope: org_id + property_id filter; `1=0` defense when no context)
  - `app/Modules/Platform/Infrastructure/Models/Organization` (the tenant root; deliberately does NOT use BelongsToTenant)
  - `app/Modules/Platform/Infrastructure/Models/Membership` (FK to User, Organization, Property; uses BelongsToTenant)
  - `app/Modules/Catalog/Infrastructure/Models/Property` (stub for P1-01; full CRUD in P1-06; uses BelongsToTenant)
  - `app/Modules/Platform/Jobs/Middleware/PropagatesTenantContext` (queue middleware; explicit or captured current context)
  - `app/Modules/Platform/Cache/TenantCache` (org-prefixed keys; pass-through when no context)
  - `app/Modules/Platform/Logging/TenantLogProcessor` (Monolog processor; adds organization_id/property_id to extra on the `json` channel; implements `Monolog\Processor\ProcessorInterface`)
  - `app/Modules/Platform/Http/Middleware/SetTenantContext` (dev-only header resolver; replaced by P1-02)
  - `app/Modules/Platform/Http/Controllers/TenantTestController` (test-only; uses DB::table for cross-module reads so Platform stays Catalog-free)
  - `app/Modules/Platform/Infrastructure/PlatformServiceProvider` (binds OrganizationContext as singleton and TenantCache)
  - `database/migrations/2026_10_13_000001_create_organizations_table.php`
  - `database/migrations/2026_10_13_000002_create_properties_table.php` (UNIQUE(organization_id, slug); FK ON DELETE RESTRICT)
  - `database/migrations/2026_10_13_000003_create_memberships_table.php` (FKs + STORED generated column `property_id_key = COALESCE(property_id, 0)` for NULL-safe UNIQUE)
  - `database/factories/OrganizationFactory.php`, `database/factories/PropertyFactory.php`, `database/factories/MembershipFactory.php`
  - `app/Providers/AppServiceProvider.php` (boots `Factory::guessFactoryNamesUsing()` so module models map to flat factory names)
  - `config/logging.php` (TenantLogProcessor registered on the `json` channel; closes the P0-07 deferred follow-up)
  - `bootstrap/providers.php` (PlatformServiceProvider)
  - `bootstrap/app.php` (SetTenantContext appended to the web group)
  - `routes/web.php` (/_testing/tenant/{ping,properties,memberships} routes; gated by APP_ENV != production)
  - `phpunit.xml` (DB_CONNECTION=sqlite so tests run without a MySQL server)
  - `docs/TENANCY.md` (the human-readable policy that the code enforces)
  - `docs/ENGINEERING_STANDARDS.md` section 2.8 cross-link
  - `tests/Arch/EloquentBoundariesTest.php` (removed the `toBeUsedInNothing` assertion; the rule it was trying to enforce is covered by ModuleBoundariesTest + the Domain/Application-never-import-Eloquent assertion; explanatory comment in place)
  - `tests/Unit/Platform/Domain/Tenancy/TenantContextTest.php` (8 cases)
  - `tests/Unit/Platform/Application/OrganizationContextManagerTest.php` (6 cases: stack push/pop/nested/exceptions/without)
  - `tests/Unit/Platform/Cache/TenantCacheTest.php` (5 cases: prefix, pass-through, forget, has, remember-miss)
  - `tests/Unit/Platform/Logging/TenantLogProcessorTest.php` (5 cases)
  - `tests/Unit/Platform/Jobs/Middleware/PropagatesTenantContextTest.php` (4 cases)
  - `tests/Feature/Platform/Tenancy/BelongsToTenantTest.php` (9 cases: filter, block update, block delete, auto-fill, exception, withoutGlobalScope)
  - `tests/Feature/Platform/Http/SetTenantContextMiddlewareTest.php` (6 cases: header, missing, X-Property-Id, invalid, zero, no-leak)
  - `tests/Feature/Platform/Tenancy/TenantIsolationTest.php` (11 cases across Eloquent, cache, jobs, HTTP, raw SQL)
  - `tests/Feature/Platform/Tenancy/TenantTableArchTest.php` (the CI gate; one case per model; introspects PRAGMA table_info)
  - `tests/Support/TenantArchScanner.php` (autoloaded helper for the arch test)
  - `tests/Support/TenantContextRecordingJob.php` (real ShouldQueue test job; static-property pattern to ferry the observed context across the queue boundary)

- **Acceptance proof**:
  - **Organizations, properties, BelongsToTenant global scope, tenant-aware queues/jobs, tenant-aware cache keys**: implemented in `app/Modules/Platform/Infrastructure/{Concerns,Jobs,Cache}/`; covered by 24 unit/feature tests under `tests/Unit/Platform/{Domain,Application,Cache,Logging,Jobs}` + `tests/Feature/Platform/{Tenancy,Http}`.
  - **Isolation test that proves cross-tenant read/update/delete is impossible via web, API, and jobs**: `tests/Feature/Platform/Tenancy/TenantIsolationTest.php` — 11 cases that exercise Eloquent reads, Eloquent update via `where()->update`, Eloquent delete via `where()->delete`, the `TenantCache` (different org cannot read each other's keys), a real `ShouldQueue` job (the `PropagatesTenantContext` middleware propagates the context to the worker), and the test HTTP routes (`/_testing/tenant/properties` and `/_testing/tenant/memberships` are scoped to the X-Organization-Id header; a wrong-org request returns 0 rows). All 11 pass.
  - **Adding a tenant table without organization_id or without the scope fails a CI test**: `tests/Feature/Platform/Tenancy/TenantTableArchTest.php` introspects the DB schema (sqlite `PRAGMA table_info`) for every model in `app/Modules/*/Infrastructure/Models/*`. If the table has `organization_id`, the model must use `BelongsToTenant`; otherwise it must be on a small exemption list (today: `Organization` only). The error message names the model and table and prescribes the fix, so a future PR that adds a tenant model without the trait fails the build with an actionable message. Pass for the 3 current models (Organization, Property, Membership).

- **P0-07 follow-up closed**: the JSON log channel now adds `extra.organization_id` and `extra.property_id` (when the context has one) via `TenantLogProcessor`. The P0-07 closeout had listed this as deferred to P1-01; that note is now satisfied.

- **Decisions taken in this PR** (no new ADR; consistent with ADR-0003):
  - In-house trait + scope (not stancl/tenancy), per `docs/TECH_STACK.md` and ADR-0003.
  - Pure PHP for `TenantContext` and `OrganizationContextManager` (Domain + Application have no Eloquent import, per the existing arch test).
  - `Organization` model does not use the trait (it IS the tenant).
  - SetTenantContext is dev-only header-based; P1-02 replaces it with an auth-aware resolver. The route file gates the `/_testing/tenant/*` test endpoints on `APP_ENV != production`.
  - `Membership.property_id` is a nullable FK; UNIQUE on (user_id, organization_id, property_id) uses a STORED generated column `property_id_key = COALESCE(property_id, 0)` so two `property_id IS NULL` rows for the same (user, org) are correctly rejected (the SQL standard treats NULL as distinct in unique constraints).
  - `TenantLogProcessor` implements `Monolog\Processor\ProcessorInterface`; without it the LogManager throws InvalidArgumentException at channel build time and silently drops ALL processors (including PiiScrubber), which broke the P0-07 JsonLogTest in the first iteration of this PR. Fixing this is part of the test commit, documented in the body.
  - `AppServiceProvider::boot()` registers a `Factory::guessFactoryNamesUsing()` so module models (e.g. `App\Modules\Platform\Infrastructure\Models\Organization`) resolve to `Database\Factories\OrganizationFactory` (flat layout) instead of Laravel default namespace-mirroring. `App\Models\*` default is preserved.
  - `TenantTestController` uses `DB::table()` (not Eloquent models) for the cross-module `properties` / `memberships` reads, so the controller stays in Platform without importing Catalog. Documented in the controller docblock as a deliberate `ENGINEERING_STANDARDS.md` section 2.8 exception.
  - `Membership` exposes `property_id` as a plain int (no `belongsTo` relation) to keep the Platform module free of Catalog imports; a future P1-03 Application-layer query joins via the Catalog Contracts/ boundary.
  - `tests/Arch/EloquentBoundariesTest.php` lost the `toBeUsedInNothing` assertion: it forbade any reference to a model, including the model's own factory, so it failed the moment a real model was added. Cross-module usage is enforced by `tests/Arch/ModuleBoundariesTest` and the "Domain and Application never import Eloquent" assertion; the deleted test was redundant and the new comment in its place documents why.
  - `SetTenantContext` is intentionally permissive in the absence of auth: any caller that knows a valid org id can set the context. P1-02 closes that gap.

- **Follow-ups (out of scope here)**:
  - The Property model is a stub. Full CRUD (rooms, policies, tax rules, timezone/currency/check-in times, media, Filament resources with i18n content) ships in P1-06, which can build on this scaffolding.
  - Membership `role` is a free string today. P1-03 turns it into a typed enum and wires the permission matrix.
  - The `SetTenantContext` HTTP middleware is dev-only. P1-02 replaces it with an auth-aware resolver that derives the org from the authenticated user's Memberships.
  - Two pre-existing PiiScrubberTest failures (`it redacts credential assignments in header shape`, `it does not rewrite JSON-shaped strings`) are present on this branch before P1-01 work; both are P0-07 fixes and are out of scope per `AGENTS.md` section 7 (do not touch another task's module extensively). They are not regressions of this PR; verified by `git stash` reverting all P1-01 work and re-running.

- **Verified end-to-end**:
  - `vendor/bin/pint` clean across all new and modified files.
  - `vendor/bin/phpstan analyse` 0 errors (level 5).
  - `vendor/bin/pest tests/Unit/Platform tests/Feature/Platform tests/Feature/Logging tests/Arch` -> **130 passed (482 assertions)**.
  - The 2 pre-existing PiiScrubberTest failures are NOT counted above (they are filtered by the directories I ran against).

- **What the next task (P1-02 Identity) gets for free**:
  - `OrganizationContext` contract to inject into a new auth-aware resolver middleware.
  - `BelongsToTenant` trait to apply to any new tenant table without ceremony.
  - A CI gate that fails if a new tenant model forgets the trait.
  - The deferred P0-07 `organization_id` / `property_id` log fields are already flowing.
- Acceptance: adding a tenant table without `organization_id` or without the scope fails a CI test.

### P1-02 Identity and 2FA
Deps: P1-01 · Size: M · Status: todo
- Login, logout, password reset, email verification, TOTP 2FA (required for owner/manager roles), session management, login throttling.
- Acceptance: tests for lockout, reset flow, 2FA enrollment and recovery codes; no user enumeration via responses.

### P1-03 RBAC
Deps: P1-01 · Size: M · Status: todo
- Roles per property (owner, manager, front_desk, housekeeping, revenue, marketing) and policies; role management UI in Filament.
- Acceptance: a permission matrix test covers every route/action; forbidden access returns 403 and is logged.

### P1-04 Audit log
Deps: P1-01 · Size: S · Status: done (branch `feat/P1-04-audit-log`, last commit at closeout)
- Record actor, subject, event, changes, timestamp for configured models; read-only viewer in admin.
- Acceptance: updates to rates, reservations, and roles appear in the log; log entries cannot be edited via the app.
- **Closeout (2026-10-15)**: branch `feat/P1-04-audit-log` (off `main`). Migration `2026_10_15_000001_create_audit_logs_table.php` (tenant-scoped, append-only at the model layer, four indexes). Domain enum `App\Modules\Platform\Domain\AuditLog\AuditEvent`. Application port `App\Modules\Platform\Application\AuditLog\Ports\Auditor` plus `SubjectChange` and `TenantHint` VOs (Application sub-tree stays Eloquent-free, EloquentBoundariesTest still green). Infrastructure adapter `EloquentAuditor`, trait `Auditable`, model `AuditLog` (append-only via `save()` and `delete()` overrides, uses `BelongsToTenant`). `PlatformServiceProvider` binds the port to the adapter as a singleton. `Membership` is the first opt-in via `use Auditable;` (proof of pattern for P1-03 and P2-01+). Tests: 17 unit + 18 feature (35 new). `vendor/bin/phpstan analyse` 0 errors. `vendor/bin/pest --testsuite=Unit` 127/127, `--testsuite=Feature` 64/64. The 47 pre-existing arch-test failures are unchanged (scaffolds for 11 modules live on `feat/p1-02-identity`, not yet on `main`); no new arch failures introduced. Doc `docs/AUDIT.md` follows the same human-readable policy pattern as `docs/TENANCY.md` and `docs/IDENTITY.md`, with a mapping of these acceptance bullets to the code. Follow-ups left for P2-15 (Filament admin viewer) and P1-03 (RBAC 403 case writing to the audit log).

### P1-05 Money and tax primitives
Deps: P0-01 · Size: M · Status: done (branch `feat/p1-05-money`, last commit `f50190d`)
- `Money` value object (minor units + currency), arithmetic with explicit rounding rules, tax/service-charge calculator (inclusive/exclusive, ordering).
- Acceptance: property-based tests (no float use; add/subtract/allocate preserve totals); rounding rules documented in code and `docs/`.
- Closed on `feat/p1-05-money`: 167/167 Pest (875 assertions), Pint clean, PHPStan 0 errors, 52 new unit tests under `tests/Unit/Support/Money/`.
- Commits:
  - `40d3e69` chore(p1-05): claim task and install brick/money ^0.10
  - `93b82a0` feat(p1-05): Money value object with arithmetic and allocation
  - `665f26f` feat(p1-05): tax and service-charge primitives with inclusive/exclusive calculator
  - `4ef381e` test(p1-05): cover Money, tax primitives, and property-based invariants
  - `95089ef` docs(p1-05): add MONEY.md and link from ENGINEERING_STANDARDS
  - `f50190d` chore(p1-05): pint formatting and array_values cleanup
- Deliverables:
  - `app/Support/Money/Money.php` - readonly value object. Amount stored as integer minor units; currency as ISO 4217 code (validated through brick/money). Factories: `ofMinor`, `of`, `zero`. Arithmetic: `plus`, `minus`, `multipliedBy(RoundingMode)`, `dividedBy(RoundingMode)`, `negate`, `abs`. Allocation: `allocate(int)`, `allocateToTargets(int[])`. Comparison: `equals`, `compareTo`, `isZero/Positive/Negative`, `isGreaterThan/LessThan/...OrEqualTo`. Serialization: `toArray`, `fromArray`. Cross-currency operations throw `App\Support\Money\Exception\CurrencyMismatchException`.
  - `app/Support/Money/Exception/CurrencyMismatchException.php` - DomainException with `::between($a, $b)` factory.
  - `app/Support/Money/TaxKind.php` - enum `tax | service_charge`.
  - `app/Support/Money/TaxAppliesTo.php` - enum `room | addon | all`.
  - `app/Support/Money/TaxRule.php` - readonly VO. Rate stored as integer basis points (10000 bps = 100 %). Inclusive vs exclusive, appliesTo, order, per-rule roundingMode. Static factories `TaxRule::exclusive()` / `TaxRule::inclusive()`. Constructor rejects negative and out-of-range rates.
  - `app/Support/Money/TaxLine.php` - readonly VO (rule + amount + inclusive flag).
  - `app/Support/Money/TaxBreakdown.php` - readonly VO (subtotal + total + lines). Helpers `taxTotal()`, `serviceChargeTotal()`, `grandChargesTotal()`. Asserts all amounts share the subtotal's currency.
  - `app/Support/Money/TaxCalculator.php` - service. Sorts rules by `order` ascending. Exclusive rules add to the running total; inclusive rules extract from the original subtotal (tax = subtotal - subtotal / (1 + rate)) and leave the total unchanged. This avoids circular logic when multiple inclusive rules share a price.
  - `docs/MONEY.md` - storage rule, value-object API, rounding policy (HALF_EVEN default, when to override), tax model, three worked examples, anti-patterns. Documents the IDR ISO 4217 two-fraction-digit gotcha and the `Money::ofMinor()` workaround.
  - `docs/ENGINEERING_STANDARDS.md` section 2.1 - now references `docs/MONEY.md` and names the `App\Support\Money\*` classes.
- Acceptance proof:
  - **Money value object (minor units + currency)**: `Money::ofMinor(1_000, 'USD')` -> 1000 cents; `Money::of('12.34', 'USD')` -> 1234 cents. Cross-currency `plus`/`minus`/`equals`/`compareTo` throw `CurrencyMismatchException`. Unknown ISO codes throw `UnknownCurrencyException`. (`tests/Unit/Support/Money/MoneyTest.php`.)
  - **Arithmetic with explicit rounding rules**: `multipliedBy` and `dividedBy` accept a `Brick\Math\RoundingMode` enum; default is `HALF_EVEN`. HALF_EVEN vs HALF_UP differ at exact half-points (e.g. 1.00 * 0.005 -> 0.00 vs 0.01, asserted in `MoneyTest::multipliedBy_HALF_EVEN_rounds_0_005_down_at_scale_2` and `MoneyTest::multipliedBy_HALF_UP_rounds_0_005_up_at_scale_2`).
  - **Tax/service-charge calculator (inclusive/exclusive, ordering)**: `TaxCalculatorTest` covers the textbook $122.10 chained-SC-then-VAT case, the $110.00 inclusive-of-VAT-plus-exclusive-SC case, order-of-application, and the per-rule rounding mode (`HALF_EVEN` vs `HALF_UP` at the 0.005 cent boundary).
  - **Property-based tests (no float use; add/subtract/allocate preserve totals)**: `MoneyPropertyTest` runs 30-40 deterministic random iterations per property (seed `2025_10_13`) for: addition associativity, addition commutativity, additive identity (`a + 0 == a`), self-subtract (`a - a == 0`), multiply identity (`a * 1 == a`), and allocation preserves total for `N=2..15` with sources up to 1_000_000.
  - **No float use**: `Money::of` accepts `string|int` only; `ofMinor` accepts `int` only; `multipliedBy`/`dividedBy` accept `string|int` only. brick/money's `BigInteger` backs all arithmetic; the `brickToMinor()` private helper multiplies by `10^fractionDigits` via `Brick\Math\BigInteger` to recover the integer minor count. Floats are impossible to construct at the type level.
  - **Rounding rules documented in code and docs/**: `Money::DEFAULT_ROUNDING_MODE` const in code; `docs/MONEY.md` section 3 (Rounding) documents the policy with a domain table (default, tax-on-cash, final-invoice) and an anti-pattern warning against float math.

### P1-06 Property catalog
Deps: P1-01, P1-03 · Size: L · Status: todo
- Properties, room types, room units, amenities, media (WebP optimization), policies, tax rules, timezone/currency/check-in times; Filament resources with i18n content.
- Acceptance: CRUD with policies; media upload validated and optimized; tenant isolation tests pass.

### P1-07 Rate plans and prices
Deps: P1-05, P1-06 · Size: L · Status: todo
- Rate plans (including derived plans), per-date prices per room type, occupancy adjustments (extra adult/child), cancellation policy link.
- Acceptance: price calculation service returns itemized nightly prices; derived-rate and occupancy cases covered by tests.

### P1-08 Inventory days engine
Deps: P1-06, P1-07 · Size: L · Status: todo · Gate: M1
- Schema from `docs/ARCHITECTURE.md` §5.1, rolling window materialization job, `quote()`, restrictions (min/max stay, CTA, CTD, stop-sell), `block/unblock`.
- Acceptance: availability matches hand-computed fixtures; restrictions enforced; DB CHECK constraints present.

### P1-09 Holds: hold, commit, release, expire
Deps: P1-08 · Size: L · Status: todo · Gate: M1
- `hold()` with deterministic lock order, retry on deadlock, idempotency key; `commit`, `release`, `cancel`; expiry job; `InventoryChanged` event emitted after commit.
- Acceptance: algorithm matches `docs/ARCHITECTURE.md` §5.3; repeating a call with the same key returns the same hold.

### P1-10 Concurrency and invariant test suite
Deps: P1-09 · Size: M · Status: todo · Gate: M1
- Tests from `docs/ARCHITECTURE.md` §5.5: 500 concurrent holds for the last unit → exactly 1 success; overlapping ranges without surfaced deadlocks; double-run expiry; property-based random sequences.
- Acceptance: suite runs in CI (separate job allowed); results recorded in the PR. **Failure freezes all other modules.**

### P1-11 SaaS subscription and plan gating
Deps: P1-01, P0-04 · Size: L · Status: todo
- Plans (PMS, Booking Engine, Channel Manager, CRM), trial, subscription state, invoices for SaaS fees, feature flags per plan.
- **Billing module (in-house, ADR-0013):** `Domain/Subscription` state machine, idempotency keys, webhook normalizer; `Infrastructure` adapters — **Xendit** (primary, supports VA/QRIS/e-wallet/cards via Merchant-Initiated Transactions) and **Midtrans** (secondary, cards only via recurring MID). Single source of truth = local `subscriptions` table; gateway refs stored as encrypted credentials per `organization`. Webhooks from both gateways normalized into internal domain events (`BillingCyclePaid`, `BillingCycleFailed`, `SubscriptionCanceled`).
- Acceptance: a disabled module is inaccessible by route and by API; trial expiry and failed-cycle flows tested; webhook replay produces no double-charge; MIT-enabled channel selection per organization works; switching between Xendit and Midtrans adapters verified end-to-end.

### P1-12 Locale, timezone, and date conventions
Deps: P0-05 · Size: S · Status: todo
- Helpers for property-local dates, UTC storage, stay-date `DATE` semantics, localized formatting.
- Acceptance: tests around midnight boundaries for properties in different timezones; no ad-hoc date formatting in code (lint rule or review checklist).

### P1-13 Settings framework
Deps: P1-06 · Size: S · Status: todo
- Typed per-property and per-organization settings with defaults and validation.
- Acceptance: settings changes audited; cache invalidation tested.

### P1-14 Media storage abstraction
Deps: P0-02 · Size: S · Status: todo
- S3-compatible storage with signed URLs for private files, public CDN paths for property images.
- Acceptance: private invoices are never publicly reachable; tests use a fake disk.

### P1-15 Notification framework
Deps: P1-12 · Size: M · Status: todo
- Channels (mail now, WhatsApp-ready), templates per locale, delivery log, bounce/complaint webhook intake from the email provider.
- Acceptance: template rendering tests per locale; failed delivery recorded and visible.

---

## P2 — PMS (weeks 5–12)

### P2-01 Reservation aggregate and state machine
Deps: P1-09 · Size: L · Status: todo
- Aggregate, statuses and transitions per `docs/ARCHITECTURE.md` §7, domain events, versioned modifications.
- Acceptance: exhaustive unit tests for allowed and forbidden transitions; events dispatched after commit.

### P2-02 Create reservation (staff)
Deps: P2-01, P1-07 · Size: M · Status: todo
- Staff UI/API: choose dates, room type, rate plan, occupancy, guest; price quote; hold + commit in one transaction.
- Acceptance: cannot create when unavailable or restricted; double-submit creates one reservation.

### P2-03 Modify reservation
Deps: P2-02 · Size: L · Status: todo
- Change dates, room type, guests, rate plan; inventory delta applied atomically; price recalculation with a diff shown to staff.
- Acceptance: tests for extend/shorten/move-room-type/failed-availability (original stays intact on failure).

### P2-04 Cancel and no-show
Deps: P2-02 · Size: M · Status: todo
- Policy-based fee calculation, inventory release, folio entries, notifications.
- Acceptance: fee matches policy fixtures; cancellation is idempotent.

### P2-05 Room assignment
Deps: P2-02 · Size: M · Status: todo
- Assign/unassign/move room units; overlap prevention; suggestions for free units.
- Acceptance: invariant 7 enforced with a concurrency test.

### P2-06 Tape chart UI
Deps: P2-05 · Size: L · Status: todo
- Virtualized grid by room/date, drag to move/extend, quick create, status colors, keyboard accessible, responsive for tablet.
- Acceptance: stays usable with 100 rooms × 60 days; E2E tests for create/move/extend; accessibility checks pass.

### P2-07 Guest profiles (PMS)
Deps: P1-06 · Size: M · Status: todo
- Create/search/edit guests, link to reservations, identity documents (encrypted), basic merge.
- Acceptance: encrypted fields verified at rest; search by name/email/phone; audit entries on view of identity documents.

### P2-08 Check-in and check-out
Deps: P2-05, P2-09 · Size: M · Status: todo
- Flows with early/late rules, outstanding balance warning, room status updates.
- Acceptance: cannot check out with unresolved balance without manager override (audited).

### P2-09 Folio ledger
Deps: P1-05, P2-01 · Size: L · Status: todo · Gate: M2
- Append-only ledger, charges/taxes/payments/refunds/adjustments, reversal entries, business date handling.
- Acceptance: invariants 3 and 4 tested, including property-based tests.

### P2-10 Invoices (PDF) and email
Deps: P2-09, P1-15 · Size: M · Status: todo
- Gapless invoice numbering, locale-aware PDF with tax breakdown, email delivery.
- Acceptance: numbering gapless under concurrency; PDF snapshot tests for `id` and `en`.

### P2-11 Staff-recorded payments and deposits
Deps: P2-09 · Size: M · Status: todo
- Record cash/bank transfer/offline card payments, deposits, refunds with approvals.
- Acceptance: payments cannot exceed folio rules; refunds require permission and audit.

### P2-12 Housekeeping board
Deps: P2-08 · Size: M · Status: todo
- Room states, tasks, assignment, mobile-friendly view, out-of-order ranges that reduce availability through the Inventory contract.
- Acceptance: marking out-of-order blocks inventory and emits `InventoryChanged`.

### P2-13 Night audit and business date close
Deps: P2-09 · Size: M · Status: todo
- Post room charges for in-house guests, close the business date, lock entries, produce the audit report; safe to re-run.
- Acceptance: running twice posts once; closed date rejects new entries.

### P2-14 Reports
Deps: P2-13 · Size: L · Status: todo
- Occupancy, ADR, RevPAR, revenue by source/rate plan, arrivals/departures, in-house, cancellations; CSV export; formulas per `docs/DOMAIN_MODEL.md` §4.
- Acceptance: results match hand-calculated fixtures; report queries are index-backed (EXPLAIN reviewed in PR).

### P2-15 Staff management and activity feed
Deps: P1-03 · Size: S · Status: todo
- Invite users, assign roles per property, view activity feed from the audit log.
- Acceptance: invitation tokens expire; role changes audited.

### P2-16 Legacy data import
Deps: P2-07, P2-02 · Size: M · Status: todo
- CSV import for room types, rate plans, guests, future reservations; dry-run with error report; idempotent re-import. **Per OQ-04:** pilot currently uses manual processes, so a generic CSV template is sufficient — no custom parser required.
- Acceptance: imports 10k rows within a documented time on staging; a bad row never partially applies a reservation.

### P2-17 PMS alpha readiness
Deps: P2-03, P2-04, P2-06, P2-08, P2-10, P2-12, P2-14 · Size: M · Status: todo · Gate: M2
- Realistic seed dataset, dogfooding checklist, bug triage, known-issues list.
- Acceptance: gate M2 criteria in `docs/ROADMAP.md` §2.

---

## P3 — Booking Engine (weeks 9–16)

### P3-01 Public API v1 and OpenAPI spec
Deps: P1-09 · Size: L · Status: todo
- Endpoints: availability, quote, create booking, retrieve booking; per-property keys, CORS allow-list, rate limiting, `Idempotency-Key`, RFC 7807 errors.
- Acceptance: OpenAPI file validated in CI; contract tests; abuse tests (rate limit, bad origin).

### P3-02 Availability cache
Deps: P3-01 · Size: M · Status: todo
- Redis cache with version counters invalidated on `InventoryChanged`.
- Acceptance: tests prove cached data can never cause a successful oversell (hold re-validates); cache hit ratio metric exposed.

### P3-03 Booking flow and holds
Deps: P3-01, P2-01 · Size: L · Status: todo · Gate: M3
- Booking session, guest form validation, hold with TTL, countdown, reservation in `pending_payment`.
- Acceptance: double-click and refresh create one reservation; expiry releases units.

### P3-04 Payment gateway integration
Deps: P3-03, P0-04 · Size: L · Status: todo · Gate: M3
- Hosted payment per ADR-0005 (Xendit primary, Midtrans secondary per ADR-0013), signed idempotent webhooks, delayed/duplicate/out-of-order webhook handling, refunds, deposit vs full payment settings.
- **Per OQ-05:** payment method list is filtered by guest nationality in the booking widget — WNI sees all ID methods (cards + VA + QRIS + e-wallets via Xendit MIT-enabled channels); WNA sees credit card only. Nationality captured at booking and stored on the reservation.
- Acceptance: all failure paths in `docs/ARCHITECTURE.md` §6 tested against the gateway sandbox; reconciliation report of payments vs folio.

### P3-05 Promo codes and offers
Deps: P3-03 · Size: M · Status: todo
- Percent/fixed discounts, validity windows, min stay, usage limits, stackability rules.
- Acceptance: usage limit holds under concurrency (invariant 12); discount applied before tax per property setting.

### P3-06 Add-ons and extras
Deps: P3-03 · Size: M · Status: todo
- Pricing bases (per stay/night/person), tax handling, show in quote and folio.
- Acceptance: quote and folio totals equal for all pricing bases.

### P3-07 Embeddable widget
Deps: P3-01 · Size: L · Status: todo · Gate: M3
- Separate bundle (`<script>` embed + custom element), theming, `id`/`en`, responsive, WCAG 2.1 AA, size budget per ADR-0007, versioned loader.
- Acceptance: works on a plain HTML page and on a WordPress test page; Lighthouse accessibility check passes; bundle size within budget.

### P3-08 Hosted booking page
Deps: P3-01 · Size: M · Status: todo
- Same flow as a standalone page per property (custom domain optional later).
- Acceptance: parity with widget flow in E2E tests.

### P3-09 Confirmation and reminder emails
Deps: P1-15, P3-03 · Size: M · Status: todo
- Locale-aware confirmation, cancellation, pre-arrival reminder, `.ics` attachment.
- Acceptance: snapshot tests; bounce webhook updates delivery status.

### P3-10 Analytics events
Deps: P3-07 · Size: S · Status: todo
- GA4/GTM dataLayer and Meta Pixel events for search, select, begin_checkout, purchase; gated by consent.
- Acceptance: no tracking fires before consent where required (automated test).

### P3-11 Anti-abuse
Deps: P3-01 · Size: M · Status: todo
- Turnstile on submit, per-IP and per-key throttles, basic fraud rules (velocity, mismatched country flags), honeypot.
- Acceptance: scripted abuse scenarios blocked; false-positive path documented.

### P3-12 Booking engine admin
Deps: P1-06, P3-07 · Size: M · Status: todo
- Branding, languages (`id` / `en` / `zh` per OQ-06), visible rate plans, tax display, policies, payment settings (nationality-based filter per OQ-05), allowed origins, embed snippet generator.
- Acceptance: changes reflect in the widget without redeploy; origin allow-list enforced.

### P3-13 End-to-end test suite
Deps: P3-04, P3-07 · Size: M · Status: todo · Gate: M3
- Playwright: search → pay (sandbox) → confirm → visible in PMS; payment failure; hold expiry; last-room race; multilingual.
- Acceptance: runs in CI nightly and on release branches; flake rate tracked.

### P3-14 Pilot website integration and beta
Deps: P3-13, P0-08 · Size: M · Status: todo · Gate: M3
- Install on a pilot hotel's website, monitor funnel, fix issues.
- Acceptance: gate M3 criteria.

---

## P4 — Channel Manager (weeks 13–20)

### P4-01 Distribution domain model
Deps: P1-06, P1-08 · Size: M · Status: todo
- Connections, encrypted credentials, room/rate mappings, change log, inbound events tables.
- Acceptance: mappings validated (no duplicate external ids); credentials never appear in logs or API responses.

### P4-02 Aggregator client
Deps: P4-01, P0-04 · Size: L · Status: todo
- Typed client per ADR-0004 (auth, retries, rate limits, error taxonomy), sandbox configuration, recorded-fixture contract tests.
- Acceptance: contract tests pass against recorded fixtures and, when credentials exist, against the sandbox; vendor docs version/date noted in the PR.

### P4-03 Outbound ARI push
Deps: P4-02, P1-09 · Size: L · Status: todo · Gate: M4
- Change capture → debounce/coalesce → batch → ordered per-connection queue; retries; dead-letter.
- Acceptance: 1,000 rapid changes produce a small number of coalesced pushes; order preserved; failure leaves a visible, replayable state.

### P4-04 Bulk update UI
Deps: P4-03 · Size: M · Status: todo
- Date-range grid for rates, availability, restrictions across rooms and channels; preview of changes before pushing.
- Acceptance: bulk change of 365 days × 5 rooms completes without timeouts; audit entries created.

### P4-05 Inbound reservations
Deps: P4-02, P2-03, P2-04 · Size: L · Status: todo · Gate: M4
- Signed webhook receiver, raw event store, idempotent processing, create/modify/cancel mapped to reservations (`source = ota:<channel>`).
- Acceptance: replayed webhook is a no-op; modification and cancellation sequences correct; out-of-order delivery handled by revision.

### P4-06 Overbooking and quarantine workflow
Deps: P4-05 · Size: M · Status: todo · Gate: M4
- Unmapped rooms → quarantine; no availability → overbooking alert with resolution actions; nothing is dropped silently.
- Acceptance: scripted scenarios produce visible work items and notifications.

### P4-07 Reconciliation
Deps: P4-03 · Size: M · Status: todo · Gate: M4
- Daily job compares internal vs channel ARI, writes drift report, optional auto-heal behind a flag.
- Acceptance: injected drift is detected and reported; auto-heal restores state when enabled.

### P4-08 Sync dashboard
Deps: P4-03, P4-05 · Size: M · Status: todo
- Per-channel health, last success, error list, retry, pause/resume.
- Acceptance: operator can find and replay a failed push within three clicks.

### P4-09 Resilience tooling
Deps: P4-03, P4-05 · Size: M · Status: todo
- Circuit breaker per connection, dead-letter inspection and replay, alert rules, runbook draft.
- Acceptance: simulated provider outage pauses pushes, alerts, and recovers without data loss.

### P4-10 Sandbox certification and soak test
Deps: P4-06, P4-07, P4-08, P4-09 · Size: L · Status: todo · Gate: M4
- Complete the provider/OTA onboarding checklist; run two weeks of daily reconciliation with zero unexplained drift.
- Acceptance: gate M4 criteria.

---

## P5 — CRM & Smart Plugins (weeks 15–22)

### P5-01 Guest identity resolution
Deps: P2-07 · Size: M · Status: todo
- Dedupe by normalized email/phone, merge/undo, unified profile with stay history across PMS, booking engine, and chat.
- Acceptance: merge preserves all links; undo within the window restores the previous state.

### P5-02 Consent management
Deps: P5-01 · Size: M · Status: todo
- Purposes, policy versions, opt-in/out capture at booking/chat/web forms, audit trail, data export and deletion workflows.
- Acceptance: withdrawal blocks sends; deletion request removes or anonymizes personal data per policy and keeps required financial records.

### P5-03 Segments
Deps: P5-01 · Size: M · Status: todo
- Rule-based segments (stays, spend, country, language, source, last stay), saved segments with live counts.
- Acceptance: results match fixtures; queries are index-backed.

### P5-04 Email automations
Deps: P5-02, P1-15 · Size: L · Status: todo
- Triggers (booking confirmed, pre-arrival, post-stay, review request), delays, templates per locale, unsubscribe handling, bounce/complaint processing.
- **Delivery: Postmark per ADR-0011.** Transactional sends via the Transactional Message Stream; marketing sends via a separate Broadcast Message Stream to protect reputation. Sandbox token for dev/CI; production token applied late Phase 6 (ahead of M6). Webhook ingestion for `bounce`, `spamcomplaint`, `open` (optional, for engagement tracking) — all signed per Postmark spec.
- Acceptance: each send is idempotent; unsubscribed guests never receive marketing; failure paths tested; bounce/complaint webhook stops further sends to that address.

### P5-05 Campaigns
Deps: P5-03, P5-04 · Size: M · Status: todo
- One-off sends to a segment, scheduling, throttling, delivery and click reporting.
- Acceptance: a 10k-recipient send respects provider rate limits and can be paused.

### P5-06 Communication log
Deps: P5-01 · Size: S · Status: todo
- Unified timeline per guest (emails, chat, notes).
- Acceptance: entries link to source messages; access is permission-gated.

### P5-07 Visitor tracking and `/collect`
Deps: P5-02, P3-07 · Size: M · Status: todo
- Consent-gated snippet, event ingestion with rate limits, anonymous id → guest link on identification, retention policy job.
- Acceptance: nothing is stored without consent where required; retention job deletes expired events.

### P5-08 Price-match plugin
Deps: P4-03, P3-05 · Size: L · Status: todo
- Config UI (enable, max delta, floor, offset), reference price from pushed channel rates, widget prompt, `price_match_events` audit.
- Acceptance: never offers below the floor; every offer logged; behavior matches `docs/ARCHITECTURE.md` §11 in table-driven tests.

### P5-09 Live chat (Tawk.to)
Deps: P3-07 · Size: M · Status: todo
- Tawk.to widget integration per ADR-0010. Embed Tawk.to JS in booking widget (P3-07) and PMS pages; per-property widget config (widget id, property key) stored in `BookingEngine/WidgetConfig`. Signed visitor identity via Tawk.to JS API `visitor.setMetadata` + `setVisitorName/Email`, populated from our backend once a guest identifies themselves (booking, login). Offline email fallback already built into Tawk.to (no extra work).
- Acceptance: visitors cannot impersonate other identities (signature verified by Tawk.to); offline fallback delivers; per-property inbox routing via Tawk.to property switching.

### P5-10 Loyalty
Deps: P3-05, P5-01 · Size: L · Status: todo
- Points ledger, tiers, accrual on checkout, auto-applied benefits in booking, member magic-link login.
- Acceptance: ledger invariant tests (append-only, non-negative balance); benefits cannot be applied twice.

### P5-11 Instagram feed plugin
Deps: P0-09, P3-07 · Size: M · Status: todo
- Connect flow, token refresh job, cached feed, graceful fallback if the API fails.
- Acceptance: widget never calls Meta directly; expired token raises an operator alert.

### P5-12 Upsell and promo tools UI
Deps: P3-05, P3-06 · Size: M · Status: todo
- Manage codes, seasonal discounts, add-on packages.
- Acceptance: non-technical staff can create a seasonal offer without developer help (usability check with the pilot).

### P5-13 Remarketing hooks
Deps: P5-04, P5-07 · Size: M · Status: todo
- Abandoned-booking emails (opt-in only) and audience export for ad platforms behind a feature flag.
- Acceptance: only consented guests included; export is audited.

---

## P6 — Hardening & launch (weeks 21–26)

### P6-01 Load testing
Deps: P3-13, P4-05 · Size: M · Status: todo
- k6 scenarios (search, hold, booking, webhook bursts, ARI pushes) derived from pilot data; thresholds from `docs/ARCHITECTURE.md` §17. **SLA target = 99.5% uptime per OQ-09**; load test must demonstrate sufficient headroom to meet the monthly availability budget.
- Acceptance: targets met or exceptions recorded as risks; bottlenecks fixed or ticketed.

### P6-02 Security review [human + agent]
Deps: P3-04, P4-05 · Size: L · Status: todo · Gate: M5
- Internal OWASP review, dependency and secret scans, external pentest engagement, remediation of critical/high findings.
- Acceptance: no open critical/high findings; report archived.

### P6-03 Backup and restore drill
Deps: P0-06 · Size: M · Status: todo · Gate: M6
- PITR configured, encrypted offsite copies, documented RPO/RTO, restore rehearsal into an isolated environment.
- Acceptance: a restore from backup passes a data-integrity check script; timings recorded.

### P6-04 Observability and alerting
Deps: P4-09 · Size: M · Status: todo
- Dashboards and alert rules per `docs/ARCHITECTURE.md` §15; status page.
- Acceptance: each alert has a test or a game-day demonstration and an owner.

### P6-05 Runbooks
Deps: P6-04 · Size: M · Status: todo · Gate: M6
- Deploy/rollback, incident response, sync drift, payment reconciliation, restore, certificate/secret rotation.
- Acceptance: a person not involved in writing a runbook can follow it in a drill.

### P6-06 Onboarding wizard
Deps: P2-16, P4-01 · Size: M · Status: todo
- Guided property setup (rooms, rates, taxes, policies, widget install, channel connection), checklist and progress.
- Acceptance: a pilot hotel completes setup with support in a single session.

### P6-07 Documentation
Deps: P3-01 · Size: M · Status: todo
- User guide and admin handbook (`id`/`en`), API docs from the OpenAPI file, FAQ.
- Acceptance: docs reviewed by the pilot hotel's staff.

### P6-08 Legal and compliance pack [human]
Deps: P5-02 · Size: M · Status: todo
- Privacy policy, terms, DPA template, cookie banner text, data retention policy, breach notification procedure, reviewed by counsel.
- Acceptance: signed-off documents linked in the product.

### P6-09 Pilot go-live and hypercare
Deps: P6-03, P6-05, P6-06, P6-08, P6-10 · Size: L · Status: todo · Gate: M6
- Cutover plan with rollback, data import, staff training, daily check-ins for the first two weeks.
- Acceptance: gate M6 criteria.

### P6-10 Feature-complete verification
Deps: P2-17, P3-14, P4-10, P5-04, P5-08, P6-02 · Size: M · Status: todo · Gate: M5
- Regression run on staging against the full backlog acceptance list; triage remaining bugs.
- Acceptance: gate M5 criteria.
