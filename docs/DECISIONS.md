# DECISIONS

## 0. How to use this file

- **accepted** = the planning default; agents follow it. Changing it requires a new ADR that supersedes the old one, approved by the tech lead.
- **proposed** = needs a human decision before dependent tasks start; agents may draft options but must not choose.
- ADR format: *Context → Decision → Consequences → Status*. Keep each under 15 lines.

## 1. ADR log

### ADR-0001 Modular monolith — accepted
- **Context:** small team, one deployable unit simplifies transactions (inventory + reservation + folio) and operations.
- **Decision:** one Laravel application organized into modules with enforced boundaries (`docs/ARCHITECTURE.md` §3).
- **Consequences:** boundaries need CI enforcement (P0-10); extraction to services is possible later via contracts and events.

### ADR-0002 Laravel + MySQL + Redis + Vue/Inertia + Filament — accepted
- **Context:** large local hiring pool, mature ecosystem (queues, scheduler, billing, admin), fast CRUD for back office.
- **Decision:** stack in `docs/TECH_STACK.md`; no Lumen, no MongoDB at launch, no jQuery in new code.
- **Consequences:** frontend interactivity limited to Vue where needed; revisit Octane after load tests.

### ADR-0003 Single-database multi-tenancy with `organization_id` — accepted
- **Context:** per-tenant databases multiply operational cost; MVP tenant count is small.
- **Decision:** shared schema, global scope, composite keys, isolation test suite (P1-01).
- **Consequences:** any missing scope is a security bug; large tenants may need partitioning later.

### ADR-0004 Channel manager via aggregator — accepted
- **Decision:** **Channex** as the single channel aggregator for MVP. Chosen for self-service sandbox (`staging.channex.io`), immediate API key issuance, test accounts for Booking.com + Airbnb, self-certification (non-blocking), and public API docs with `llms.txt`. Compared against Cloudbeds (approval-gated), RateGain (partnership-gated), SiteMinder / STAAH (no public developer portal); Channex is the only one that needs no intensive vendor coordination during P0–P5.
- **Consequences:**
  - Sandbox-first for MVP: dev, test, and CI use Channex staging; production rollout gated by self-certification + partnership activation (target submit late Phase 4, ahead of M4).
  - Per-connection fees, rate limits (20 ARI req/min/property, 10 MB/call), and retention periods (bookings 6 months, logs 3 months, live feed 1 month) shape sync design — see `docs/ARCHITECTURE.md` §9 and `docs/BACKLOG.md` P4-03, P4-07.
  - Credentials encrypted at rest per `docs/ENGINEERING_STANDARDS.md` §4.
  - **Cross-reference ADR-0013:** same pattern — single in-house adapter wrapping one third-party API, sandbox-first for MVP, production gated by vendor review.
- **Verified:** 2026-10-12 (Channex API docs: reference, rate limits, PMS integration guide, retention periods).

### ADR-0005 Payments via hosted gateway pages — accepted
- **Decision:** **Xendit primary** for guest → hotel payments via Payment Session hosted mode. Midtrans (Snap) as secondary adapter per ADR-0013. PCI scope stays SAQ-A via `PAYMENT_LINK` (hosted page). `COMPONENTS` mode (embedded) only if business case demands embedded UX — raises PCI scope and needs separate review.
- **Consequences:**
  - **Sandbox-first for MVP:** dev, test, and CI use Xendit sandbox keys. Pilot validation in sandbox before applying for Xendit production review.
  - **Production gated by Xendit review** — submit for production credential approval early in Phase 6 so approval does not block M6.
  - Webhook signature verification, idempotency, and reconciliation are mandatory per `docs/ENGINEERING_STANDARDS.md` §3 and §4.
  - Credentials encrypted at rest per `docs/ENGINEERING_STANDARDS.md` §4.
  - **Cross-reference ADR-0013:** Xendit powers both guest payments (this ADR) and SaaS billing (ADR-0013); single vendor, single ops runbook, sandbox-first, production gated.
- **Verified:** Xendit Payment Session docs (2026-03-06), Midtrans Snap docs (2025-11-11), checked 2026-10-12.

### ADR-0006 Inventory locking strategy — accepted
- **Context:** overbooking is the costliest failure.
- **Decision:** DB row locks in deterministic order inside one transaction; idempotency keys; Redis never authoritative (`docs/ARCHITECTURE.md` §5.3).
- **Consequences:** contention on popular dates; mitigated by short transactions and coalescing; load-tested in P6-01.

### ADR-0007 Embeddable widget approach — accepted (budget pending)
- **Context:** hotel websites are heterogeneous (WordPress, custom, builders).
- **Decision:** separate lightweight bundle loaded via `<script>`, custom element with scoped styles, hosted-page fallback.
- **Consequences:** a gzip size budget must be set by the tech lead at the start of P3-07 and enforced in CI.

### ADR-0008 Money representation — accepted
- **Decision:** integer minor units + currency code via `Money`; no floats; explicit rounding rules (P1-05).
- **Implementation:** `brick/money` library; pinned at P0-01. Confirmed 2026-10-12.

### ADR-0009 Price-match reference source — accepted for MVP
- **Context:** scraping OTAs violates their terms; the system already knows what it pushes to channels.
- **Decision:** reference price = pushed channel rate + configurable offset; licensed rate-shopping data may be added later.
- **Consequences:** OTA-side promotions can make guest-visible prices differ; hence the offset and conservative guardrails.

### ADR-0010 Live chat via Tawk.to — accepted
- **Decision:** **Tawk.to** (hosted, forever-free) as the chat widget for MVP. Chosen because it is the only fully-free hosted option without per-seat or per-conversation limits (verified alternatives: Crisp Free = 2 seats/100 contacts max; Tidio Free = 50 conversations/mo; HubSpot Free = tied to HubSpot CRM ecosystem). Chatwoot (self-hosted) remains a viable post-MVP swap if full data sovereignty becomes a hard requirement.
- **Consequences:**
  - Hosted proprietary — no full control of chat data; acceptable for MVP. Plan a re-evaluation at M6 (post-pilot) when more volume forces a Chatwoot decision.
  - "Powered by tawk.to" branding shown on the free tier; $29/mo add-on removes it.
  - Visitor identification via Tawk.to JS API + signed identity from our widget (same pattern as the originally-proposed Chatwoot integration). Offline fallback to email supported by Tawk.to out of the box.
  - Self-service signup at `dashboard.tawk.to`; no vendor coordination required. JS widget embeddable in booking widget (P3-07) and PMS pages.
  - Cross-reference ADR-0011: same sandbox-first hosted-vendor pattern.
- **Verified:** 2026-10-12 (tawk.to pricing page, public docs at docs.tawk.to).

### ADR-0011 Transactional email via Postmark — accepted
- **Decision:** **Postmark** as the transactional email provider for MVP. Chosen because: (1) Free Developer plan — 100 emails/month that never expires, perfect for dev/CI/sandbox; (2) sandbox mode uses the same API as production; (3) native event webhooks for `bounce` and `spamcomplaint` (no SNS-style extra setup like SES); (4) Message Streams separate transactional from broadcast to protect reputation; (5) self-service onboarding, no AWS-style sandbox-access review.
- **Consequences:**
  - Sandbox-first for MVP — dev/CI use Postmark sandbox token; production apply late Phase 6, well ahead of M6.
  - Cost predictable: $15–$18/mo for first 10k emails, then per-1k overage. Acceptable up to ~50k/month; revisit SES as cheaper alternative if volume grows past that.
  - Single-region (US or EU at signup) — select EU for GDPR alignment; PDP / GDPR data residency controlled via signup region and processor agreement.
  - Webhook signature verification required per `docs/ENGINEERING_STANDARDS.md` §4.
  - Cross-reference ADR-0005 / ADR-0013: same pattern — hosted vendor with self-service sandbox, production gated by review.
- **Verified:** 2026-10-12 (Postmark pricing page, public docs at postmarkapp.com).

### ADR-0012 PDF generation — accepted
- **Decision:** `barryvdh/laravel-dompdf`. Chosen for zero Chrome dependency (simpler deploy) and Blade compatibility; trade-off is weaker CSS support — invoice/folio templates must use CSS table layouts and avoid flexbox/grid/JS.
- **Consequences:** Snapshot tests for `id`/`en` layouts per P2-10; media/web fonts limited to what Dompdf embeds.
- **Verified:** 2026-10-12.

### ADR-0013 SaaS billing: in-house module with Xendit (primary) + Midtrans adapters — accepted
- **Context:** Laravel Cashier only supports Paddle/Stripe (cards). Bali hotels prefer VA/QRIS/e-wallet. Midtrans Recurring is credit-card only and needs a separate "recurring MID" from the acquiring bank (per docs 2025-11-11). Xendit Subscriptions supports VA/QRIS/e-wallet/cards for any channel that enables Merchant-Initiated Transactions (per docs 2026-07-09).
- **Decision:** build an in-house `Billing` module (`Domain/Subscription` state machine, `Application/Actions`, `Infrastructure/{XenditAdapter, MidtransAdapter}`). Primary adapter is **Xendit** (`/recurring/plans`, `/recurring/cycles/*`). Secondary adapter is **Midtrans** (Recurring via Core API, cards only). Single source of truth = local `subscriptions` table; gateway refs stored as encrypted credentials per `organization`. Webhooks from both gateways are normalized into internal events (`BillingCyclePaid`, `BillingCycleFailed`, `SubscriptionCanceled`).
- **Consequences:**
  - More code than Cashier, but state machine, idempotency, and retry policy are owned in-house (same pattern as reservation/inventory).
  - No lock-in to a third-party package that may be abandoned.
  - PCI scope stays at SAQ-A: hosted payment pages / tokenization only; no raw card data on our servers.
  - P1-11 scope grows: gateway adapters, webhook normalizer, per-channel retry policy, and adapter swap.
- **Verified:** Midtrans Recurring docs (2025-11-11), Xendit Subscriptions docs (2026-07-09), checked 2026-10-12.

## 2. Human-owned decisions (agents must not decide)

1. Pilot hotels, commercial terms, and acceptance of pilot feedback.
2. Vendor selection and accounts: aggregator, payment gateway, email, WhatsApp provider.
3. SaaS pricing and plan packaging.
4. Legal documents, DPA, compliance sign-off, legal entity and data-processing locations.
5. Product name, brand, domains.
6. Hosting region and any data-residency commitments.
7. Spending approvals: commercial licenses, pentest vendor, paid tooling.
8. Go/no-go at each gate (M1–M6).
9. Revenue/report definitions (net vs gross of tax, treatment of add-ons) with the pilot.
10. Access to production data and support impersonation policy.

## 3. Open questions (owner → due)

| ID | Question | Owner | Needed by |
|---|---|---|---|
| OQ-01 | ~~Do pilots sell by room type only, or by individual units (villas)?~~ **Resolved 2026-10-12:** sell per room type only; `RoomUnit` retained for housekeeping and room assignment; per-unit selling deferred from MVP. `bed_config` added to `RoomUnit` (DOMAIN_MODEL §2 Catalog). | — | — |
| OQ-02 | ~~Tax and service-charge rules and invoice format for the pilot's jurisdiction~~ **Resolved 2026-10-12:** apply the standard Indonesian hospitality (horeca) tax and service-charge rules in force at MVP launch. Implemented via per-property `TaxRule` config (`DOMAIN_MODEL` §2 Catalog) plus the tax calculator in P1-05. Specific rates (PB1 restoran, PPN, service charge) snapshotted onto each charge at posting time per `ARCHITECTURE.md` §8. | — | — |
| OQ-03 | ~~Which OTAs does the pilot actually use, and who owns the existing OTA accounts?~~ **Resolved 2026-10-12:** pilot currently has no OTA connections. When OTAs are activated later, migrate bookings and inventory directly into Stayflow (no coexisting channel manager). Channel connections are created during onboarding per BACKLOG P4-04. | — | — |
| OQ-04 | ~~Existing system to migrate from (formats, volumes)~~ **Resolved 2026-10-12:** pilot currently uses manual processes (no existing PMS). Legacy import (P2-16) is reduced to a generic CSV template for room types, rate plans, guests, and future reservations; no custom parser required. | — | — |
| OQ-05 | ~~Payment methods required locally (cards, bank transfer, QRIS, e-wallets)~~ **Resolved 2026-10-12:** Indonesian residents (WNI) see all ID methods (cards + VA + QRIS + e-wallets via Xendit MIT-enabled channels); foreign residents (WNA) see credit card only. Booking widget (P3-07) detects / asks nationality and filters the payment method list accordingly. Nationality captured at booking and stored on the reservation. **Verified:** Xendit Payment Session supports all listed ID methods (docs 2026-03-06). | — | — |
| OQ-06 | ~~Languages beyond `id`/`en` needed for the pilot's guests~~ **Resolved 2026-10-12:** add **Mandarin (`zh`)** in addition to `id` and `en`. Total locales = `id`, `en`, `zh`. i18n scaffold per BACKLOG P0-05; CI missing-key check covers all three. | — | — |
| OQ-07 | Instagram API review status and permitted account types | TL | week 10 | **Partly answered 2026-10-27:** the permitted account types are the Instagram Business and Creator accounts linked to a Facebook Page, and the current path is the *Instagram API with Unified Login* (a Business Integration System user access token, the host `graph.facebook.com`, the single permission `instagram_basic`) — see `docs/META_APP_RUNBOOK.md` §2–3. What remains is the **review status** of the advanced access: the request cannot be sent yet, because registering the app needs the business verification of the legal entity, which is human. |
| OQ-08 | Support hours and channel expected during hypercare | PO | week 22 |
| OQ-09 | ~~Target SLA/uptime promised to the pilot~~ **Resolved 2026-10-12:** **99.5% uptime SLA** (monthly). Load-test thresholds (P6-01) and alerting rules (P6-04) target this figure; infra sizing assumes single-region deployment unless pilot later requires multi-region. See `ARCHITECTURE.md` §17 (NFR targets). | — | — |
| OQ-10 | Data retention periods (tracking events, communication logs) | LEGAL | week 15 |
| OQ-11 | Per-person pricing vs flat rate; child age bands; extra-bed fees (DOMAIN_MODEL §5 question 2) | PO | week 8 |
| OQ-12 | Deposit policies and refund rules per rate plan, and what the payment gateway allows (DOMAIN_MODEL §5 question 5) | PO | week 8 |

## 4. Gate log (append a note at each gate)

| Gate | Date | Result | Evidence | Notes |
|---|---|---|---|---|
| M1 | | | | |
| M2 | | | | |
| M3 | | | | |
| M4 | | | | |
| M5 | | | | |
| M6 | | | | |

## 5. Risk register

| ID | Risk | Likelihood | Impact | Mitigation | Owner |
|---|---|---|---|---|---|
| R1 | Pilot hotel not secured early; PMS built without real feedback | M | H | P0-08 in week 1; advisory hotelier as fallback | PO |
| R2 | Aggregator or OTA onboarding slower than planned | M | H | Start vendor conversations in week 1; keep P4 work against sandbox | TL |
| R3 | Scope creep on plugins | H | M | Cut order in `docs/ROADMAP.md` §6; plugins behind feature flags | PO |
| R4 | Inventory bug causes overbooking | L | H | P1-10 suite, invariants, reconciliation, overbooking workflow | TL |
| R5 | Price-match produces wrong offers | M | M | Conservative guardrails, floor, audit log, manual-approval mode | BE |
| R6 | Meta API review or policy changes block the feed | M | L | Feed is optional; fallback to a static link | PO |
| R7 | Key-person dependency (tech lead) | M | H | ADRs, runbooks, pair on critical modules | TL |
| R8 | Vendor API changes break integrations | M | M | Contract tests, version pinning, vendor changelog watch | BE |
| R9 | Privacy/legal gaps (PDP law, GDPR for EU guests) | M | H | Consent by design, counsel review (P6-08) | LEGAL |
| R10 | Estimates are optimistic | H | M | Re-estimate at M1 and M2; use the cut order | TL |
| R11 | Perceived copying of a competitor's brand or content | L | M | Original copy, design, naming; legal review before launch | PO |
| R12 | Infra and per-message costs exceed pricing assumptions | M | M | Cost monitoring (OPS-07); model unit economics before pricing | PO |
