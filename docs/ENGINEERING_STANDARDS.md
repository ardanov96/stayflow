# ENGINEERING_STANDARDS

Applies to humans and AI agents alike. Where a rule says "CI enforces", a failing check blocks the merge.

## 1. Code structure and style

1. PHP: `declare(strict_types=1);`, typed properties/params/returns, `final` classes by default, constructor injection. Format with Pint; static analysis with PHPStan/Larastan (level set in P0-01; never lower it; no baseline growth).
2. Module layout: `Domain/` (pure PHP: entities, value objects, domain services, events), `Application/` (actions/use cases, DTOs, queries), `Infrastructure/` (Eloquent models, repositories, adapters), `Http/` (controllers, requests, resources), `Contracts/` (public interfaces, DTOs, events). CI enforces boundaries.
3. Controllers are thin: validate (FormRequest) → authorize (policy) → call an Action → return a Resource. No business logic in controllers, models, or Blade/Vue.
4. One Action = one use case (`CreateReservation`, `HoldInventory`). Actions are transactional where they change state.
5. Naming: English identifiers; domain terms follow `docs/DOMAIN_MODEL.md` §1. Avoid abbreviations except those in the glossary.
6. Prefer composition and explicit data over magic (no dynamic properties, no hidden global state). Enable `Model::preventLazyLoading()` and `Model::shouldBeStrict()` outside production to catch N+1 and silent attribute bugs.
7. Comments explain **why**, not what. Every non-obvious invariant links to its test.

## 2. Data and domain rules

1. **Money:** integer minor units + ISO currency; use the `Money` value object; never floats; rounding is explicit and tested.
   Full policy and the rounding/tax rules live in `docs/MONEY.md`. Money is
   represented by `App\Support\Money\Money`; tax and service charge by
   `App\Support\Money\TaxRule` and `App\Support\Money\TaxCalculator`.
2. **Time:** store UTC timestamps; stay dates are `DATE` in the property's timezone; use helpers from P1-12; never format dates by hand.
3. **Migrations:**
   - Never edit a migration that has shipped; add a new one.
   - Expand → migrate → contract: every migration must be compatible with the previous app release.
   - Large-table changes use online-safe patterns and a documented backfill; no destructive change without a backfill/rollback plan.
   - Indexes justified in the PR (query and `EXPLAIN` for new hot paths).
4. **Transactions:** wrap multi-step state changes; dispatch events/jobs `afterCommit`.
5. **Locking:** inventory uses the algorithm in `docs/ARCHITECTURE.md` §5.3 only. Any new lock requires an ADR.
6. **Soft deletes** only where the domain needs history; financial records are never deleted.
7. **Enums** for closed sets (statuses, types), backed by strings; never magic strings.
8. **Tenant scoping:** every model on tenant tables uses `BelongsToTenant`; raw SQL must include the tenant condition (reviewed explicitly).

    The full model is in `docs/TENANCY.md` (hierarchy, scope behavior, the 1=0
    defense-in-depth default, the test surface, and the dev-only header
    resolver that P1-02 replaces). The trait, scope, context stack, queue
    middleware, cache wrapper, and log processor all live in
    `app/Modules/Platform/*` and are wired by `PlatformServiceProvider`.
    The CI gate is `tests/Feature/Platform/Tenancy/TenantTableArchTest`:
    adding a tenant model without the trait fails the build.
## 3. HTTP and API conventions

1. Validation in FormRequests; reject unknown fields on public API.
2. Authorization via policies on every action; each endpoint has a "forbidden" test and a "wrong tenant" test. The RBAC model, roles, matrix, middleware, and 403 audit logging are documented in `docs/RBAC.md`.
3. Public API: versioned path, `Idempotency-Key` on mutating requests, RFC 7807 problem responses, cursor pagination, rate limits per key and IP.
4. Webhooks: verify signature first; store the raw payload; acknowledge fast; process asynchronously and idempotently.
5. Error messages to end users are translated; internal details never leak (no stack traces, SQL, or vendor errors).
6. Responses use API Resources; never return Eloquent models directly.

## 4. Security rules (non-negotiable)

1. No secrets in code, config files, logs, fixtures, or screenshots. gitleaks runs in CI.
2. No raw card data touches our servers; only gateway hosted fields/pages and tokens.
3. Sensitive guest fields use encrypted casts; they are never logged and are excluded from search indexes.
4. Passwords hashed with Laravel defaults; 2FA required for owner/manager roles.
5. All user input is validated; all output is escaped; never build SQL from strings; never `eval`-like behavior.
6. File uploads: type/size validation, random names, stored outside the web root or on object storage; images re-encoded.
7. CORS allow-lists are per property and exact (no wildcards with credentials).
8. Rate limiting on auth, public API, webhooks, and expensive reports.
9. Dependencies: `composer audit` and `npm audit` in CI; critical advisories fixed within 7 days.
10. Logs: structured; redact emails, phones, tokens, ID numbers.
11. Admin/support impersonation: explicit, time-limited, audited.
12. Report suspected vulnerabilities privately to the tech lead; do not discuss them in public PRs or issue titles.

## 5. Testing strategy

| Level | Tooling | What it must cover |
|---|---|---|
| Unit | Pest | Domain logic: pricing, tax, state machines, ledger math |
| Feature | Pest + HTTP tests | Every endpoint: success, validation, forbidden, wrong tenant |
| Architecture | Pest arch / Deptrac | Module boundaries, forbidden dependencies, naming rules |
| Concurrency | Pest (parallel processes) | Inventory, promo usage, invoice numbering, room assignment |
| Contract | Recorded fixtures + sandbox | Aggregator and payment gateway clients |
| E2E | Playwright | Booking funnel, PMS critical paths, widget embed |
| Load | k6 | Search, hold, booking, webhooks, ARI pushes |
| Property-based | Pest datasets / custom generators | Inventory counters, folio ledger, money allocation |

Rules:
- A bug fix starts with a failing test that reproduces it.
- Tests are deterministic: freeze time, seed randomness, no network in unit/feature tests (use fakes).
- Flaky tests are bugs: quarantine with a ticket within one day, fix within the sprint.
- Coverage is a signal, not a goal; but Inventory, Folio, Payments, and Distribution require ≥ 90% line coverage on domain/application code (CI reports; exceptions need justification).
- Test data uses factories; no production data in tests or fixtures.

## 6. Queues, jobs, and scheduling

1. Every job is idempotent and safe to retry; define `tries`, `backoff`, and a `failed()` handler that alerts.
2. Use unique/`WithoutOverlapping` keys where duplicates or parallel runs are harmful.
3. Jobs receive IDs, not models; they re-load and re-check state.
4. Scheduled tasks use `onOneServer()` and are idempotent; record last-run metrics.
5. Queue names and priorities per `docs/ARCHITECTURE.md` §13.

## 7. Frontend standards

1. TypeScript strict; `<script setup lang="ts">`; props and emits typed.
2. All user-facing strings via i18n keys; CI checks missing keys for `id` and `en`.
3. Server is the source of truth; optimistic UI only where rollback is implemented and tested.
4. Accessibility: semantic HTML, keyboard operable, visible focus, color contrast AA, ARIA only where needed; axe checks in E2E.
5. Performance: lazy-load heavy views, virtualize large grids, avoid layout thrash; the widget respects its size budget (ADR-0007).
6. No inline secrets or environment-specific URLs; configuration comes from the backend or build-time env.
7. The widget must not break host pages: namespaced CSS (shadow DOM or scoped), no global variables, no console noise in production.

## 8. Git and review workflow

1. Trunk-based: short-lived branches `feat/<task-id>-<slug>`, `fix/<id>-<slug>`; rebase before merge; squash merge with a Conventional Commit title.
2. One task per PR; PR size: aim for < 500 changed lines excluding generated files; split otherwise.
3. PR description: task ID, summary, acceptance mapping, migrations, risks, screenshots for UI, how it was tested, vendor docs consulted (with date).
4. Required checks: CI green; at least one human approval for changes to Inventory, Folio, Payments, Distribution, security, or migrations on large tables.
5. No force-push to `main`; no direct commits to `main`.
6. Release: tagged releases from `main`; changelog from Conventional Commits; rollback procedure documented in runbooks.

## 9. Definition of Done (copy into every PR)

- [ ] Task acceptance criteria each mapped to a test or documented manual check
- [ ] `composer ci` green; frontend lint/test/build green
- [ ] New/changed endpoints have validation, policy, rate limit, forbidden + wrong-tenant tests
- [ ] No hard rule in `AGENTS.md` §3 violated (money, inventory, PCI, tenant scope, secrets, idempotency)
- [ ] Migrations reviewed: backward compatible, indexed, backfill plan if needed
- [ ] Jobs are idempotent with retries/backoff and a failure handler
- [ ] Logs contain no PII/secrets; new error paths are observable (log/metric/alert as appropriate)
- [ ] UI: i18n keys for `id` and `en`, keyboard accessible, empty/error/loading states
- [ ] Docs updated (`docs/`, OpenAPI, runbook, ADR if a decision changed)
- [ ] BACKLOG status updated; follow-up tasks recorded rather than left as TODO comments
- [ ] For vendor integrations: vendor documentation version/date recorded; sandbox test evidence attached

## 10. Documentation and decisions

1. Architecture decisions are recorded as ADRs in `docs/DECISIONS.md` (context, decision, consequences, status).
2. Public behavior changes update the OpenAPI spec and user docs in the same PR.
3. Runbooks live in `docs/runbooks/` (created in P6-05) and are tested in drills.
4. Do not leave `TODO` without a task ID.

## 11. Performance and observability conventions

1. Every new hot-path query has an index justified by `EXPLAIN`; no unbounded queries; pagination required on lists.
2. Cache with explicit keys, TTLs, and invalidation; never cache authorization decisions across users.
3. Add a metric or log for every new background process (start, success, failure, duration).
4. Business-critical failures (sync, payments, inventory invariants) alert; do not rely on someone reading logs.
