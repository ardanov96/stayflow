# AGENTS.md — Instructions for AI coding agents

Read this file fully before doing anything. Claude Code users: copy or symlink this file to `CLAUDE.md`.

## 1. Project in three sentences

Stayflow (working name) is a multi-tenant SaaS for hotels and villas: PMS, Booking Engine, Channel Manager,
CRM, and Smart Plugins. It is a **Laravel modular monolith** (MySQL 8, Redis, Vue 3 + Inertia, Filament).
The goal is a production-ready MVP in ~26 weeks; correctness of inventory and money matters more than speed.

## 2. Source of truth (in order)

1. `docs/DECISIONS.md` — accepted ADRs win over everything else.
2. `docs/ARCHITECTURE.md` and `docs/DOMAIN_MODEL.md` — structure and invariants.
3. `docs/ENGINEERING_STANDARDS.md` — how code is written and verified.
4. `docs/BACKLOG.md` — what to build, with acceptance criteria.
5. `docs/ROADMAP.md` — sequencing and gates.

If two documents conflict, stop and raise it (see §7). Never silently pick one.

## 3. Hard rules (never violate)

1. **Never oversell inventory.** All availability changes go through the Inventory module's public service. No raw SQL updates to `inventory_days` outside it.
2. **Money is integer minor units + currency code.** No floats, ever. Use the `Money` value object.
3. **Never store, log, or transmit raw card data.** Payments use the gateway's hosted page only (PCI SAQ-A scope).
4. **Every tenant-scoped query is scoped.** Every tenant table has `organization_id` (and `property_id` where relevant). A missing scope is a security bug.
5. **No secrets in the repo, logs, or test fixtures.** Use `.env` and the secret store. Never commit real API keys, tokens, or guest data.
6. **Webhooks and mutating public endpoints are idempotent** and verify signatures where the sender provides them.
7. **Do not copy text, images, brand names, or UI from guirez.com or any competitor.** Original work only.
8. **Do not weaken tests to make them pass.** If a test is wrong, fix it and say why in the PR.
9. **Do not make human-owned decisions** (listed in `docs/DECISIONS.md` §2). Propose, then wait.
10. **Do not invent vendor API details.** Read the vendor's current documentation (or ask a human to supply it). Record what you verified and the date in the PR.

## 4. Task loop

For each task in `docs/BACKLOG.md`:

1. **Pick** a task whose `deps` are all `done` and whose status is `todo`. Prefer lower phase numbers, then lower IDs.
2. **Claim**: set its status to `in-progress` in `docs/BACKLOG.md` in your first commit.
3. **Plan** in the PR description: files you will touch, migrations, risks. For tasks sized `L`, post the plan first and wait for approval when a human is available.
4. **Branch**: `feat/<task-id>-<slug>` (for example `feat/P1-08-inventory-days`).
5. **Implement** in small commits using Conventional Commits (`feat:`, `fix:`, `test:`, `docs:`, `chore:`, `refactor:`).
6. **Verify** locally: `composer ci` must pass (format check, static analysis, architecture tests, unit and feature tests). Frontend: `npm run lint && npm run test && npm run build`.
7. **Prove acceptance**: map each acceptance bullet in the task to a test or a documented manual check in the PR.
8. **Finish**: tick the Definition of Done (`docs/ENGINEERING_STANDARDS.md` §9), set status `done` (or `blocked` with the reason), open the PR.

One task per PR. A PR that touches more than one module needs a stated reason.

## 5. Repository layout (target)

```
app/
  Modules/
    Platform/        # tenancy, identity, RBAC, audit, SaaS billing, settings
    Catalog/         # properties, room types, room units, media, policies
    Inventory/       # rate plans, prices, restrictions, availability, holds
    Reservations/    # reservation aggregate, guests link, room assignment
    Folio/           # ledger, charges, taxes, payments, invoices
    Housekeeping/
    Payments/        # gateway adapters, webhooks
    Distribution/    # channel manager: mappings, ARI push, inbound reservations
    BookingEngine/   # public API, widget backend, promos, add-ons
    Guests/          # CRM: profiles, consent, segments, automations
    Plugins/         # PriceMatch, Loyalty, Chat, SocialFeed, Upsell
    Reporting/
    Notifications/
  Support/           # shared value objects (Money, DateRange), helpers
resources/js/        # Vue 3 + Inertia (PMS UI), widget/ (embeddable bundle)
tests/               # Pest: Unit, Feature, Arch, Concurrency, E2E (Playwright in e2e/)
docs/
```

Each module has `Domain/` (entities, value objects, services), `Application/` (actions, DTOs, queries),
`Infrastructure/` (Eloquent models, repositories, adapters), `Http/`, `Database/`, `Tests/`.
**Modules talk to each other only through a module's public `Contracts/` interfaces and domain events.**
Architecture tests enforce this (task P0-10).

## 6. Commands (created by tasks P0-01 and P0-02)

| Command | Purpose |
|---|---|
| `make setup` | Fresh environment: containers, migrate, seed |
| `composer ci` | Pint (check), PHPStan, Pest arch tests, Pest unit and feature tests |
| `php artisan test --parallel` | Test suite |
| `vendor/bin/pint` | Auto-format |
| `vendor/bin/phpstan analyse` | Static analysis |
| `npm run lint && npm run test && npm run build` | Frontend checks |
| `npx playwright test` | End-to-end tests |

If a command above does not exist yet, create it as part of the earliest task that needs it, and update this table.

## 7. When to stop and ask a human

Stop and write a clear question (do not guess) when:

- A document conflict or an ambiguity affects money, availability, security, or privacy.
- A task needs a vendor account, credentials, legal text, or a pilot-hotel decision.
- Acceptance criteria cannot be met without changing an accepted ADR.
- You would need to touch another task's module extensively.
- A test fails intermittently (flaky). Report it; do not retry until green and move on.

Format: `BLOCKED <task-id>: <one-sentence problem>. Options: A) … B) … Recommendation: …`

## 8. Quality bar

- Domain code is pure and unit-tested; infrastructure code is thin.
- Prefer boring, well-known Laravel solutions over clever abstractions.
- Every new endpoint: authorization policy, validation (FormRequest), rate limit, test for the forbidden case.
- Every new queue job: idempotent, has retry/backoff, has a failure test.
- Every migration: reversible when possible, safe on a large table, no destructive change without a backfill plan.
- Write docs as you go: update `docs/` when behavior or structure changes.

## 9. Kickoff prompt templates

**Start a task**
> You are an engineer on Stayflow. Read `AGENTS.md`, then execute task `<TASK-ID>` from `docs/BACKLOG.md`
> following §4. Stop and ask per §7 if blocked.

**Review a PR**
> Review this PR against `docs/ENGINEERING_STANDARDS.md` §9 (Definition of Done) and the task's acceptance
> criteria in `docs/BACKLOG.md`. List violations of the hard rules in `AGENTS.md` §3 first.

**Plan a phase**
> Read `docs/ROADMAP.md` and `docs/BACKLOG.md`. For phase `<P#>`, propose a sprint plan that respects
> dependencies and the critical path, assigning tasks to tracks. Do not change scope.
