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
Deps: P0-01 · Size: S · Status: in-progress
- Sentry wired for app and queue workers; JSON logs with `request_id`, `organization_id`, `property_id`; PII redaction helper.
- Acceptance: a test exception appears in Sentry with context; logs contain no email/phone/token in sample runs.

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

### P1-01 Tenancy and isolation suite
Deps: P0-01 · Size: L · Status: todo
- Organizations, properties, `BelongsToTenant` global scope, tenant-aware queues/jobs, tenant-aware cache keys.
- Isolation test helper that iterates every tenant table and proves cross-tenant read/update/delete is impossible via web, API, and jobs.
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
Deps: P1-01 · Size: S · Status: todo
- Record actor, subject, event, changes, timestamp for configured models; read-only viewer in admin.
- Acceptance: updates to rates, reservations, and roles appear in the log; log entries cannot be edited via the app.

### P1-05 Money and tax primitives
Deps: P0-01 · Size: M · Status: todo
- `Money` value object (minor units + currency), arithmetic with explicit rounding rules, tax/service-charge calculator (inclusive/exclusive, ordering).
- Acceptance: property-based tests (no float use; add/subtract/allocate preserve totals); rounding rules documented in code and `docs/`.

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
