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

## Local development

The fastest path to a running app is `make setup` from the repo root — it builds Docker
containers for `mysql`, `redis`, `mailpit`, `minio`, and the `laravel.test` app image, then
runs migrations and seeders.

### Prerequisites

- Docker Desktop (or Docker Engine + Compose v2)
- GNU Make
- Git

### One-shot setup

```bash
git clone https://github.com/ardanov96/stayflow.git
cd stayflow
make setup
```

`make setup` will:

1. Copy `.env.sail` → `.env`
2. `sail up -d` to start all containers in the background
3. `sail artisan key:generate`
4. `sail artisan migrate --seed`

Once it finishes you can hit:

| URL | What |
|---|---|
| http://localhost | Laravel app (Filament + Inertia) |
| http://localhost:8025 | Mailpit UI (catches all outbound mail) |
| http://localhost:9001 | MinIO console (S3 stand-in; default user/pass `stayflow` / `stayflow-dev-secret`) |

### Useful commands

```bash
make up              # start containers
make down            # stop containers
make logs            # tail logs
make shell           # shell into laravel.test
make test            # full Pest suite
make lint            # Pint auto-format
make lint-check      # Pint --test (CI mode)
make stan            # PHPStan / Larastan
make ci              # lint-check + stan + test (the full pipeline)
make migrate         # run outstanding migrations
make fresh           # migrate:fresh --seed (DESTRUCTIVE)
make reset           # down -v + setup (DESTRUCTIVE)
make help            # show every target with its description
```

### Platform notes

| OS | Notes |
|---|---|
| Linux | Works out of the box. Add your user to the `docker` group or use rootless Docker. |
| macOS | Docker Desktop; if on Apple Silicon, Sail images include arm64 builds. |
| Windows | **Use WSL2** (per BACKLOG P0-02 acceptance). Native Docker Desktop works but the recommended workflow is to run `make` inside the WSL2 distro where the repo lives. |

### Native (no Docker) workflow

If you can't use Docker (e.g. on a constrained laptop), copy `.env.example` to `.env`,
point `DB_HOST` / `REDIS_HOST` / `MAIL_HOST` at your local services, and run:

```bash
make setup-native
# or
composer install
php artisan key:generate
php artisan migrate --seed
```

You still need MySQL 8, Redis, and (optionally) a local mail catcher running.

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