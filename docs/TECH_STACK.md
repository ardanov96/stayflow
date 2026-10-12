# TECH_STACK

Status: **proposed** unless an ADR in `docs/DECISIONS.md` marks it accepted.

## 0. Version policy

- Versions are **pinned at project kickoff** (task P0-01) to the current stable releases, then recorded in `composer.json`, `composer.lock`, `package.json`, and the lockfile. **Agents must read those files; never assume a version from this document.**
- Use the current stable Laravel major and a currently supported PHP version at kickoff. Do not start on an end-of-life version.
- Upgrade policy: security patches within 7 days; minor updates monthly; majors via an ADR.
- Before adding any dependency, check: maintained recently, license compatible with a commercial SaaS, no known critical advisories (`composer audit`, `npm audit`).

## 1. Core runtime

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP (current supported version), TypeScript for frontend | `declare(strict_types=1);` everywhere |
| Framework | Laravel (current stable major) | Modular monolith, see ADR-0001 |
| Database | MySQL 8.x (InnoDB, utf8mb4) | Authoritative store; UTC timestamps; money as BIGINT |
| Cache / queues / locks | Redis | Cache, queues (Horizon), rate limiting. **Not** the source of truth for inventory |
| Queue dashboard | Laravel Horizon | Required in production |
| App server | PHP-FPM + Nginx (Octane optional, decide after load tests) | Keep Octane out of MVP unless needed |
| Scheduler | Laravel scheduler (single cron entry) | Idempotent scheduled jobs only |

## 2. Application packages (candidates; confirm when implementing)

| Concern | Package | Why |
|---|---|---|
| Back office UI | Filament | Fast CRUD for master data, settings, admin |
| PMS interactive UI | Inertia.js + Vue 3 | Tape chart, forms; server-driven routing |
| Auth | Laravel Fortify (+ Sanctum for tokens) | Login, 2FA (TOTP), password reset |
| RBAC | spatie/laravel-permission (teams mode) or in-house | Roles per property; decide in P1-03 |
| Multi-tenancy | In-house global scope trait; stancl/tenancy in single-database mode only if it saves time (ADR-0003) | One DB, `organization_id` columns |
| Audit log | spatie/laravel-activitylog or in-house | Who changed what |
| Media | spatie/laravel-medialibrary (or in-house) + image optimization to WebP | |
| PDF | barryvdh/laravel-dompdf or browser-based renderer | Invoices; evaluate fidelity vs. ops cost |
| Money | `brick/money` | Integer minor units; ADR-0008 |
| Data objects | spatie/laravel-data | DTOs for actions and API |
| API docs | Scribe or dedicated OpenAPI file | Public booking API must have an OpenAPI spec |
| Billing (SaaS) | In-house `Billing` module (Domain/Application/Infrastructure); **Xendit** primary + **Midtrans** adapters per ADR-0013 | P1-11 |
| Feature flags | Laravel Pennant | Per-plan modules, gradual rollout |
| Monitoring | Sentry SDK; Laravel Pulse (internal) | Telescope in local/staging only |

## 3. Frontend

| Concern | Choice |
|---|---|
| Build | Vite |
| UI | Vue 3 (Composition API, `<script setup lang="ts">`), Tailwind CSS |
| State | Pinia (only where server state via Inertia is insufficient) |
| Tape chart | Custom virtualized grid component (avoid commercial scheduler licenses unless approved by a human) |
| Forms/validation | Server validation as the source of truth; client validation as UX only |
| i18n | `vue-i18n` + Laravel lang files; initial locales `id`, `en` |
| Booking widget | Separate Vite library build → single JS bundle embeddable via `<script>`; iframe/hosted-page fallback. Framework-light (vanilla/Vue custom element). Size budget set in ADR-0007 |
| Icons / charts | Lucide (or similar); chart library chosen in P2-14 |
| Accessibility | WCAG 2.1 AA target for the widget and the guest-facing pages |

## 4. Quality tooling

| Purpose | Tool |
|---|---|
| Code style | Laravel Pint (PSR-12 + Laravel preset) |
| Static analysis | PHPStan/Larastan (start at a high level and keep it; no baseline growth) |
| Architecture rules | Pest architecture tests (and/or Deptrac) — P0-10 |
| Tests | Pest (unit, feature, concurrency), Playwright (E2E), k6 (load) |
| Frontend lint/format | ESLint + Prettier; `vue-tsc` type check |
| Dependency audit | `composer audit`, `npm audit`, Dependabot/Renovate |
| Secret scanning | gitleaks (CI) |
| Mutation testing (optional) | Infection on Inventory and Folio modules |

## 5. Infrastructure

| Area | Choice |
|---|---|
| Hosting | Managed VPS (DigitalOcean, AWS Lightsail, or similar) provisioned via Forge or Ploi; revisit managed cloud after pilot |
| Edge | Cloudflare (DNS, CDN, WAF, Turnstile for bot protection) |
| Object storage | S3-compatible (AWS S3 or Cloudflare R2) |
| Environments | `local` (Docker/Sail), `staging`, `production`; staging mirrors production topology |
| CI/CD | GitHub Actions: lint → static analysis → tests → build → deploy (zero-downtime) |
| Backups | Daily full + binlog/PITR for MySQL; offsite copy; quarterly restore drill |
| Secrets | Environment variables via the host's secret store; never in the repo |
| Logs/metrics | Structured JSON logs with request ID; Sentry; uptime monitor and status page |

## 6. External vendors (all need human confirmation — see DECISIONS)

| Need | Candidate | Verify before use |
|---|---|---|
| Channel manager aggregator | Channex (primary candidate) | Current API, partner onboarding, pricing, supported OTAs, webhook model, sandbox access |
| Payment gateway | Midtrans or Xendit | Hosted payment flow, webhook signature, refunds, supported methods, settlement fees |
| Transactional email | **Postmark** (hosted) — sandbox with same API as production; native event webhooks (bounce, spamcomplaint); Message Streams separate transactional vs broadcast | ADR-0011; domain verification + DKIM/SPF/DMARC; webhook signature verification |
| Live chat | **Tawk.to** (hosted, free tier) — JS widget + visitor identification API + offline email fallback | ADR-0010; per-property widget config via `BookingEngine/WidgetConfig` |
| WhatsApp (post-MVP/optional) | Official WhatsApp Business API via a BSP | Template approval process |
| Instagram feed | Meta Instagram API for business/creator accounts | The older Basic Display API was discontinued; confirm the current API, review process, and token lifetime in Meta's docs |
| Analytics | GA4, GTM, Meta Pixel (consent-gated) | |
| Rate data for price-match (later) | Licensed rate-shopping provider | **Do not scrape OTAs** (terms of service) |

## 7. Explicit non-choices (for the MVP)

- **No Lumen** (unmaintained; use Laravel).
- **No microservices.** Modular monolith with enforced boundaries.
- **No MongoDB** at launch. MySQL JSON columns and tables cover the MVP; revisit via ADR if event volume demands it.
- **No jQuery** in new code.
- **No Kubernetes** in the MVP.
- **No scraping of OTAs** or any third-party site.
- **No card data** on our servers.

## 8. Defaults

| Setting | Default |
|---|---|
| Storage timezone | UTC; stay dates are `DATE` in the property's local timezone (Bali: Asia/Makassar) |
| Locales | `id`, `en`, `zh` (Mandarin) — per OQ-06 |
| Currency | Property currency (default IDR); display currency conversion is out of MVP scope unless required by a pilot |
| Date/number formats | Per locale via Intl; never hand-format |
| Pagination | Cursor-based for large lists, page-based for admin tables |
