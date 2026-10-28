# PRODUCTION_READINESS

Every item must be checked, with evidence (link to PR, report, dashboard, or drill log), before gate **M6** (pilot go-live).
Items marked ★ are required even for the first pilot with a single hotel. Related tasks in `docs/BACKLOG.md`.

**Status as of 2026-10-27.** 15 tasks are done (P0-01, P0-02, P0-05, P0-07, P0-10, P1-01…P1-10). The `Done`
cell carries the task ID plus the evidence behind the tick, and an open cell states *why* it is still open, so
this checklist works as gate evidence instead of a blank grid. It is updated at task closeout, not only before
M5/M6.

Legend — Owner: TL = tech lead, BE = backend, FE = frontend, QA, OPS = DevOps, PO = product owner, LEGAL = counsel/human.

## Known scope gaps (deferred from MVP per `docs/ROADMAP.md` §7)

These items are explicitly **out of MVP scope** and must not block M6. They are listed here so reviewers and the pilot hotel know what to expect.

- **Multi-property chain reporting (roll-up across properties in one organization).** Each property has its own reports in MVP; no aggregate chain dashboard. If a chain hotel is the customer, surface this in the onboarding call (P6-06).
- **Per-unit selling.** Pilots sell by room type only; `RoomUnit` is retained for housekeeping and room assignment but is not a sellable unit. Per-unit pricing for unique rooms (e.g. villas) is deferred from MVP. `bed_config` is configurable per `RoomUnit` for assignment and guest preference (DOMAIN_MODEL §2 Catalog).

## Security (SEC)

| ID | Requirement | Owner | Task | Done |
|---|---|---|---|---|
| SEC-01 ★ | OWASP Top 10 review completed and findings triaged | TL | P6-02 | [ ] |
| SEC-02 ★ | External penetration test done; no open critical/high findings | TL | P6-02 | [ ] |
| SEC-03 ★ | 2FA enforced for owner/manager roles | BE | P1-02, P1-03 | [x] P1-02/P1-03 — `Role::requiresTwoFactor()` (owner, manager), `User::requiresTwoFactor()`, `EnsureTwoFactorIsConfirmed` applied to the `/app` route group |
| SEC-04 ★ | Tenant isolation suite green for every tenant table | BE | P1-01 | [x] P1-01 — `tests/Feature/Platform/Tenancy/TenantTableArchTest.php` plus the isolation suite |
| SEC-05 ★ | Sensitive guest fields encrypted at rest; keys outside repo | BE | P2-07 | [ ] |
| SEC-06 ★ | Secrets in host secret store; rotation procedure documented | OPS | P6-05 | [ ] |
| SEC-07 ★ | WAF, HSTS, CSP, secure cookies enabled | OPS | P0-06 | [ ] Not yet: the WAF and the edge policy come with Cloudflare, which needs the server to be provisioned first (`docs/STAGING.md` §5) |
| SEC-08 | Rate limiting on auth, public API, webhooks verified by tests | BE | P3-01, P3-11 | [ ] |
| SEC-09 ★ | Dependency audit and secret scan clean in CI | OPS | P0-01 | [x] P0-01 — `composer audit` and gitleaks run in `.github/workflows/ci.yml` |
| SEC-10 ★ | Webhook signature verification and replay protection tested | BE | P3-04, P4-05 | [ ] |
| SEC-11 | Support/impersonation access audited and time-limited | BE | P1-04 | [ ] |

## Compliance and legal (CMP)

| ID | Requirement | Owner | Task | Done |
|---|---|---|---|---|
| CMP-01 ★ | Privacy policy, terms of service, DPA template reviewed by counsel | LEGAL | P6-08 | [ ] |
| CMP-02 ★ | Consent capture and audit trail work; withdrawal stops marketing sends | BE | P5-02 | [ ] |
| CMP-03 ★ | Data export and deletion workflows tested end-to-end | BE | P5-02 | [ ] |
| CMP-04 ★ | Payments only via hosted gateway pages (PCI SAQ-A scope confirmed) | TL | P3-04 | [ ] |
| CMP-05 | Data retention policy implemented (tracking events, logs, backups) | BE | P5-07 | [ ] |
| CMP-06 | Breach notification procedure documented and rehearsed | LEGAL | P6-08 | [ ] |
| CMP-07 | Cookie/consent banner and tracking gating verified | FE | P3-10, P5-07 | [ ] |
| CMP-08 | Tax invoice requirements for the pilot's jurisdiction confirmed | PO | P2-10 | [ ] |

## Reliability (REL)

| ID | Requirement | Owner | Task | Done |
|---|---|---|---|---|
| REL-01 ★ | Inventory concurrency suite green (no oversell) | BE | P1-10 | [x] P1-10 — `composer concurrency` against MySQL 8: 500 concurrent holds for the last unit produce exactly 1 success; 4 green runs. See `docs/CONCURRENCY.md` |
| REL-02 ★ | Daily backups + PITR; restore drill passed; RPO/RTO documented | OPS | P6-03 | [ ] |
| REL-03 ★ | Zero-downtime deploy with automatic rollback verified | OPS | P0-06 | [x] P0-06 — the release is prepared before the pointer moves (atomic swap, the old release keeps serving) and `deploy:publish` rolls back automatically when the health probe fails; proven by the drill in `tests/Feature/Platform/DeploymentTest.php`. The server itself is still to be provisioned (`docs/STAGING.md` §5) |
| REL-04 ★ | Queues have retries, backoff, dead-letter handling, failure alerts | BE | P4-09 | [ ] |
| REL-05 ★ | Channel reconciliation shows no unexplained drift for 2 weeks | BE | P4-10 | [ ] |
| REL-06 ★ | Overbooking and quarantine workflows exercised | QA | P4-06 | [ ] |
| REL-07 | Circuit breaker pauses and recovers on provider outage (game day) | BE | P4-09 | [ ] |
| REL-08 | Migrations backward compatible with previous release (verified on staging) | BE | all | [ ] Not verifiable yet — no staging environment exists (P0-06). |
| REL-09 | Hold-expiry job monitored; double-run safety verified | BE | P1-10 | [x] P1-10 — `tests/Concurrency/DoubleExpiryTest.php`: 32 concurrent expiry runs expire the hold exactly once and return the units exactly once (monitoring/alerting itself stays with P6-04) |

## Observability (OBS)

| ID | Requirement | Owner | Task | Done |
|---|---|---|---|---|
| OBS-01 ★ | Sentry captures app and worker errors with tenant context | OPS | P1-16 | [x] P1-16 — `config/sentry.php` is published, every reported exception is handed to Sentry, and the `before_send` hook attaches the `organization_id` and the `property_id` of the tenant that is active when the event is sent (a request and a queued job alike). The hook also takes the identity of the user out of the event. |
| OBS-02 ★ | Structured logs with request IDs; PII redaction verified | OPS | P0-07 | [x] P0-07 — `RequestIdProcessor` and `PiiScrubberProcessor` |
| OBS-03 ★ | Alerts: sync failing, drift, quarantine, payment webhook failures, queue depth, hold expiry stalled | OPS | P6-04 | [ ] |
| OBS-04 ★ | Uptime checks: app, public API, widget loader, webhook endpoints | OPS | P6-04 | [ ] |
| OBS-05 | Public status page live | OPS | P6-04 | [ ] |
| OBS-06 | Business dashboards: bookings, payment success rate, sync lag | PO | P6-04 | [ ] |

## Quality (QA)

| ID | Requirement | Owner | Task | Done |
|---|---|---|---|---|
| QA-01 ★ | E2E booking funnel passes on staging, including failure paths | QA | P3-13 | [ ] |
| QA-02 ★ | PMS critical paths E2E: create/modify/cancel, check-in/out, folio, night audit | QA | P2-17 | [ ] |
| QA-03 ★ | Folio ledger and invoice numbering invariants tested | BE | P2-09, P2-10 | [ ] |
| QA-04 | Load test meets targets or exceptions accepted by TL | QA | P6-01 | [ ] |
| QA-05 | Accessibility audit of widget and guest pages (WCAG 2.1 AA) | FE | P3-07 | [ ] |
| QA-06 | Cross-browser and mobile checks for widget and PMS tape chart | QA | P3-07, P2-06 | [ ] |
| QA-07 | Regression suite green on release candidate | QA | P6-10 | [ ] |

## Operations (OPS)

| ID | Requirement | Owner | Task | Done |
|---|---|---|---|---|
| OPS-01 ★ | Runbooks: deploy/rollback, incident, sync drift, payment reconciliation, restore, secret rotation | OPS | P6-05 | [ ] |
| OPS-02 ★ | On-call rota and escalation path defined for hypercare | TL | P6-09 | [ ] |
| OPS-03 ★ | Onboarding wizard and legacy import verified with the pilot | PO | P6-06, P2-16 | [ ] |
| OPS-04 | Support channel and SLA defined (email/WhatsApp, hours); **target uptime SLA = 99.5% per OQ-09** | PO | P6-09 | [ ] |
| OPS-05 | User guide and admin handbook in `id` and `en` reviewed by pilot staff | PO | P6-07 | [ ] |
| OPS-06 | Cutover plan with rollback and data freeze window agreed with the pilot | PO | P6-09 | [ ] |
| OPS-07 | Cost monitoring (infra, email, gateway fees) set up | OPS | P6-04 | [ ] |

## Sign-off

| Gate | Decision | Date | By |
|---|---|---|---|
| M5 — feature complete | pass / fail | | |
| M6 — go-live | pass / fail | | |
