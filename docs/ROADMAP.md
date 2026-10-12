# ROADMAP — 26 weeks to pilot go-live

Weeks are counted from project kickoff (week 1). Phases overlap on purpose. Task IDs refer to `docs/BACKLOG.md`.

## 1. Phases

| Phase | Weeks | Goal | Exit gate |
|---|---|---|---|
| P0 Discovery & foundations | 1–2 | Repo, CI, environments, vendor choices, pilot hotels signed | — |
| P1 Platform core & inventory engine | 3–6 | Tenancy, auth, RBAC, catalog, rates, inventory with proven concurrency safety | **M1** wk 6 |
| P2 PMS | 5–12 | Reservations, tape chart, folio, housekeeping, reports | **M2** wk 12 |
| P3 Booking Engine | 9–16 | Search → hold → pay → confirm on a hotel website | **M3** wk 16 |
| P4 Channel Manager | 13–20 | Two-way OTA sync via aggregator, reconciliation, dashboard | **M4** wk 20 |
| P5 CRM & Smart Plugins | 15–22 | Guest profiles, consent, automations, price-match, chat, loyalty, social feed, upsell | — |
| P6 Hardening & launch | 21–26 | Load, security, backup drills, onboarding, go-live | **M5** wk 24, **M6** wk 26 |

## 2. Gates (pass/fail, no partial credit)

| Gate | Week | Pass criteria | If it fails |
|---|---|---|---|
| M1 | 6 | `P1-10` concurrency suite green: 500 concurrent bookings for the last unit produce exactly 1 success, availability never negative, no deadlocks that surface to users | **Freeze all other modules** until fixed |
| M2 | 12 | PMS alpha used daily by the internal team or a pilot hotel for ≥ 1 week on realistic data; folio balances reconcile; no P1 bugs open | Extend P2, delay P4/P5 starts |
| M3 | 16 | First real booking completed through the booking engine on a pilot hotel's website, paid via gateway (sandbox or live), visible in PMS | Hold P4 go-live work, fix funnel |
| M4 | 20 | Two-way sync with OTA sandbox stable for ≥ 2 weeks: zero unexplained inventory drift in daily reconciliation, inbound reservations land in PMS | Do not onboard any live OTA connection |
| M5 | 24 | All MVP features complete on staging, regression suite green, external pentest findings triaged (no open critical/high) | Slip M6; do not launch |
| M6 | 26 | Pilot hotel(s) live on production with hypercare, backups restored successfully in a drill, runbooks in place | Stay in staging |

## 3. Sprint plan (13 sprints × 2 weeks)

| Sprint | Weeks | Focus |
|---|---|---|
| S1 | 1–2 | P0 all |
| S2 | 3–4 | P1: tenancy, identity, RBAC, audit, money, catalog |
| S3 | 5–6 | P1: rates, inventory engine, holds, concurrency suite (**M1**); start P2 reservations |
| S4 | 7–8 | P2: reservation lifecycle, room assignment, tape chart |
| S5 | 9–10 | P2: folio, check-in/out, housekeeping; start P3 public API |
| S6 | 11–12 | P2: night audit, reports, import (**M2**); P3 availability cache, booking flow |
| S7 | 13–14 | P3: payments, promos, widget; start P4 distribution model and aggregator client |
| S8 | 15–16 | P3: emails, analytics, E2E, pilot beta (**M3**); P4 outbound sync; start P5 guest identity/consent |
| S9 | 17–18 | P4: inbound reservations, overbooking guard; P5: segments, automations |
| S10 | 19–20 | P4: reconciliation, dashboard, soak (**M4**); P5: price-match, chat |
| S11 | 21–22 | P5: loyalty, social feed, upsell; start P6 load/security |
| S12 | 23–24 | P6: pentest remediation, backup drill, runbooks (**M5**) |
| S13 | 25–26 | P6: onboarding, docs, go-live and hypercare (**M6**) |

## 4. Parallel tracks

Tasks are grouped so that several people or agents can work without stepping on each other.

| Track | Scope | Primary phases | Typical owner |
|---|---|---|---|
| A — Platform & Inventory | Tenancy, RBAC, money, catalog, rates, inventory, holds | P0–P1 | Tech lead + backend |
| B — PMS | Reservations, tape chart, folio, housekeeping, reports | P2 | Backend + frontend |
| C — Booking & Payments | Public API, widget, gateway, promos, emails, analytics | P3 | Backend + frontend |
| D — Distribution | Aggregator client, ARI push, inbound reservations, reconciliation | P4 | Backend |
| E — CRM & Plugins | Guests, consent, automations, price-match, chat, loyalty, feed | P5 | Backend + frontend |
| F — Quality & Ops | CI, environments, E2E, load, security, backups, runbooks | all | QA + DevOps |

Rule: Track A's public contracts (`Inventory`, `Catalog`, `Platform`) are frozen after M1 except via ADR.

## 5. Critical path

```
P0-01 → P1-01 → P1-06 → P1-07 → P1-08 → P1-09 → P1-10 (M1)
  → P2-01 → P2-02 → P2-09 → P2-17 (M2)
  → P3-01 → P3-03 → P3-04 → P3-13 → P3-14 (M3)
  → P4-02 → P4-03 → P4-05 → P4-06 → P4-10 (M4)
  → P6-10 (M5) → P6-09 (M6)
```

Anything on this path that slips by a week moves go-live by a week. Protect it first.

## 6. If we are behind: cut order

Cut from the top of this list first. Items below the line are never cut.

1. `P5-13` Remarketing hooks
2. `P5-11` Instagram feed
3. `P5-10` Loyalty
4. `P5-12` Upsell/promo tools UI (keep promo codes from `P3-05`)
5. `P5-09` Live chat (fall back to a contact link)
6. `P5-08` Price-match (ship as manual offer only)

**Never cut:** `P1-10`, `P2-09`, `P3-04`, `P4-06`, `P4-07`, `P6-02`, `P6-03`, `P6-05`.

## 7. Deferred after MVP

Native mobile apps, restaurant POS, accounting integrations, groups/MICE, multi-property chain reporting,
door-lock integrations, AI-based dynamic pricing, direct OTA connectivity (without an aggregator), white-label.

## 8. Cadence

- Sprint planning on day 1 of each sprint; demo and retro on the last day.
- Weekly: gate-risk review (are we on track for the next gate?).
- Daily async status: what is done, what is blocked, what is next.
- Every gate: written pass/fail note appended to `docs/DECISIONS.md` §4.

## 9. Pilot hotel track (human-owned, parallel to everything)

| Week | Milestone |
|---|---|
| 1–2 | 1–2 design-partner hotels identified and agreed (P0-08) |
| 5–6 | Pilot interview on rate plans, restrictions, tax/service charge, housekeeping workflow |
| 9–12 | Pilot staff test the PMS alpha (M2) |
| 13–16 | Booking engine installed on pilot website (M3) |
| 19–20 | OTA connection plan agreed with the pilot (M4) |
| 23–26 | Data import, staff training, go-live (M6) |
