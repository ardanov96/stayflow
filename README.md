# Stayflow — Hospitality Platform Execution Kit

> "Stayflow" is a **working name** (placeholder). Rename freely; nothing in the code should depend on it.

This kit is the planning source of truth for building an all-in-one hospitality platform
(PMS + Booking Engine + Channel Manager + CRM + Smart Plugins) as a **production-ready MVP**.
It is written so that human engineers and AI coding agents can execute from it.

The product category and feature set were studied from public information about an existing
Bali-based vendor (Guirez). **Do not copy any text, images, branding, or UI from that vendor.**
Build original copy, design, and code.

## What we are building

A multi-tenant SaaS for hotels and villas:

| Module | One-line purpose |
|---|---|
| PMS | Run daily operations: reservations, tape chart, check-in/out, housekeeping, folio, reports |
| Booking Engine | Commission-free direct bookings on the hotel's own website (embeddable widget + hosted page) |
| Channel Manager | Keep rates/availability in sync with OTAs through an aggregator; ingest OTA reservations |
| CRM | Unified guest profiles, segments, automated emails, consent management |
| Smart Plugins | Price-match, live chat, loyalty, Instagram feed, upsell/promo tools |

## Plan at a glance

- **Duration:** ~26 weeks (13 two-week sprints) to go-live with 1–2 pilot hotels.
- **Team assumed:** 1 tech lead (Laravel), 2 Laravel full-stack devs, 1 frontend dev, 1 QA; part-time DevOps, designer, hospitality product owner.
- **Effort assumed:** roughly 22–30 person-months. These are order-of-magnitude estimates, not commitments.
- **Stack:** Laravel modular monolith, MySQL 8, Redis, Vue 3 + Inertia, Filament back office.
- **Gates:** M1 (wk 6) … M6 (wk 26). See `docs/ROADMAP.md`.

## Contents of this kit

| File | Read it for |
|---|---|
| `AGENTS.md` | **Start here if you are an AI agent.** Rules of engagement, task loop, definition of done |
| `docs/ROADMAP.md` | Phases, week ranges, gates, critical path, parallel tracks |
| `docs/BACKLOG.md` | Executable task list with IDs, dependencies, acceptance criteria |
| `docs/ARCHITECTURE.md` | Module boundaries, multi-tenancy, inventory engine, sync design, diagrams |
| `docs/DOMAIN_MODEL.md` | Entities, fields, invariants, state machines |
| `docs/TECH_STACK.md` | Chosen technologies, packages, vendors, version policy |
| `docs/ENGINEERING_STANDARDS.md` | Code style, testing, Git workflow, security rules, definition of done |
| `docs/PRODUCTION_READINESS.md` | Launch checklist with IDs |
| `docs/DECISIONS.md` | ADR log, human-owned decisions, risks, open questions |

## Recommended reading order

1. `README.md` (this file)
2. `docs/ROADMAP.md`
3. `docs/ARCHITECTURE.md` and `docs/DOMAIN_MODEL.md`
4. `docs/TECH_STACK.md`
5. `docs/ENGINEERING_STANDARDS.md`
6. `docs/BACKLOG.md` (then pick tasks)
7. `docs/DECISIONS.md` (before touching anything marked "needs human decision")

## Important caveats

- The competitor's tech stack was **inferred** from its public website and a job posting, not verified. This kit does not depend on it.
- Estimates, cost figures, and performance targets are starting points. Revisit after Phase 1.
- External vendor details (aggregator, payment gateway, Meta APIs) **must be re-verified against current vendor documentation** before implementation. Do not rely on memory.
- Several decisions are marked **human-owned** in `docs/DECISIONS.md`. Agents must not decide those on their own.