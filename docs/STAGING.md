# STAGING — deployment pipeline and runbook

Documentation for task `P0-06` (staging environment and deployment pipeline).

## 1. What it does

A push to `main` publishes a release to the staging server. The release is
prepared, the server is pointed at it, and the health endpoint is probed.
When the probe fails, the server **rolls back to the previous release by
itself** and the deployment is reported as failed.

```
push to main
    |
    v
build (composer --no-dev, npm run build)
    |
    v
upload to the server            (rsync over SSH, secrets never travel)
    |
    v
php artisan deploy:publish
    |
    +-- prepare the release      (migrations, caches, secrets)   fails -> stop, never published
    +-- move the pointer         (atomic swap)                  the old release keeps serving
    +-- probe /healthz           (up to DEPLOY_HEALTH_TIMEOUT)  fails -> roll back
    |
    v
healthy -> the release stays
```

The health endpoint is the contract between the two halves. It answers
`200` with `{"status":"ok"}` when every component is healthy and `503`
with `{"status":"degraded"}` when one is not. It names the failing
component and never the reason: the reason is in the log.

## 2. The server layout

```
/srv/stayflow/
├── current            -> the release that is serving (a symlink)
├── releases/
│   ├── 20261027-1015/     one directory per release
│   └── 20261027-1142/
├── shared/
│   └── .env              the secrets, outside every release
└── upload/               the build uploaded by CI
```

- `current` is a symlink on the server. The swap is a rename over the old
  pointer, so it is atomic: a request that is already being served is
  never interrupted, and the next request is served by the new release.
- Where symlinks are not available (a developer machine), the pointer is a
  plain file holding the path of the release. The pipeline reads both the
  same way, so it behaves identically everywhere.
- The secrets live in `shared/.env`. They are copied into the release
  that serves (with `APP_VERSION` set to the release id) and are never
  committed, never uploaded, and never part of the build artifact.

The web server (nginx) points at `current/public`. Because the pointer is
swapped atomically, nginx serves the new release without a reload.

## 3. The commands

| Command | What it does |
|---|---|
| `php artisan deploy:publish` | Prepares a release, points the server at it, probes the health endpoint, and rolls back when the probe fails |
| `php artisan deploy:rollback` | Points the server back at the previous release |
| `php artisan deploy:verify` | Probes the health endpoint |
| `php artisan deploy:status` | Shows the current release, the previous one and the health of the server |
| `php artisan deploy:prune` | Removes the releases that are too old to keep |

Useful options:

| Option | Meaning |
|---|---|
| `--root` | The server root (default `config('deploy.root')`) |
| `--source` | The directory holding the build to publish |
| `--release` | The identifier of the release (default: a timestamp) |
| `--health-url` | The health endpoint to probe |
| `--health-timeout` | How long the probe may take, in seconds |
| `--skip-dependencies` | Do not run `composer install` (the build already carries `vendor/`) |
| `--skip-migrations` | Do not run the migrations |
| `--skip-optimize` | Do not warm the caches |

## 4. Configuration

`config/deploy.php` reads:

| Variable | Default | Meaning |
|---|---|---|
| `DEPLOY_ROOT` | `/srv/stayflow` | The server root |
| `DEPLOY_HEALTH_URL` | *(none)* | The health endpoint to probe |
| `DEPLOY_HEALTH_TIMEOUT` | `30` | How long the probe may take, in seconds |
| `DEPLOY_HEALTH_INTERVAL` | `500` | How often the probe retries, in milliseconds |
| `DEPLOY_COMPOSER` | `composer` | The composer binary |

## 5. Provisioning the server (human action)

The pipeline is complete and proven, but it needs a server to publish to.
This part needs accounts and a spending decision
(`docs/DECISIONS.md` §2 items 6 and 7: hosting region and data residency,
spending approval).

1. **Provision the server** through Laravel Forge or Ploi: Ubuntu, PHP
   8.2 with the extensions of `composer.json`, nginx, MySQL 8, Redis, a
   queue worker, and a deploy user with SSH access.
2. **Create the layout** as the deploy user:

   ```bash
   sudo mkdir -p /srv/stayflow/{releases,shared,upload}
   sudo chown -R deploy:www-data /srv/stayflow
   chmod 750 /srv/stayflow/shared
   ```

3. **Put the secrets** in `/srv/stayflow/shared/.env` (mode 640, never in
   the repository). It carries at least `APP_KEY`, `APP_ENV=staging`,
   `APP_DEBUG=false`, the database and Redis credentials, and the mail
   credentials.
4. **Point nginx** at the pointer: `root /srv/stayflow/current/public;`
   and `location ~ \.php$ { fastcgi_pass unix:/run/php/php8.2-fpm.sock; }`.
5. **Put Cloudflare in front** of it, and set the WAF, HSTS and the
   secure cookie policy. This is what closes `SEC-07` in
   `docs/PRODUCTION_READINESS.md`.
6. **Configure the workflow** with the repository variables and secrets:

   | Repository variable | Value |
   |---|---|
   | `STAGING_HOST` | The server address (its presence enables the job) |
   | `STAGING_USER` | The deploy user |
   | `STAGING_ROOT` | `/srv/stayflow` |
   | `STAGING_HEALTH_URL` | `https://staging.<domain>/healthz` |

   | Repository secret | Value |
   |---|---|
   | `STAGING_SSH_KEY` | The private key of the deploy user |
   | `STAGING_KNOWN_HOSTS` | The pinned host key of the server |

7. **Run the first deployment** by pushing to `main`, and confirm it with
   `php artisan deploy:status` on the server.

## 6. The rollback drill

The acceptance of the task is proven by
`tests/Feature/Platform/DeploymentTest.php`: it publishes a release whose
health is `degraded` and asserts that the server goes back to the release
that worked, while the health endpoint keeps answering `200`.

To repeat the drill against the real server:

```bash
# Break the health of the current release on purpose.
sudo -u deploy php /srv/stayflow/current/artisan tinker
# (or edit the shared .env to point at a database that is not there)

# Watch the pipeline notice and roll back.
sudo -u deploy php /srv/stayflow/upload/artisan deploy:status
```

A release that does not survive the probe is kept on disk (for
forensics) and is never the one that serves.

## 7. Limits

- **The job is skipped until the server is provisioned.** `STAGING_HOST` is
  a repository variable, and the job only runs when it is set. Until then
  the pipeline is proven by the test suite, not by a live server.
- **The health probe is a point in time.** It proves the release answers
  200 right after the swap. The observability alerts of `P6-04` cover
  what happens afterwards.
- **One release at a time.** The pipeline refuses to run two deployments
  at once (the workflow has a concurrency group). A migration that is not
  backward compatible with the previous release therefore breaks the
  deploy: `REL-08` in `docs/PRODUCTION_READINESS.md` stays open until the
  migrations are verified on staging.
- **The queue workers reload after a healthy release**, not before: a
  worker must never run code that the health probe rejected.

## 8. Related documents

- `docs/PRODUCTION_READINESS.md` — `REL-03` (zero-downtime deploy with
  automatic rollback) and `SEC-07` (WAF, HSTS, CSP, secure cookies).
- `docs/ENGINEERING_STANDARDS.md` §8 — the Git and review workflow.
- `AGENTS.md` §4 — the task loop: the work lands on `main`, and the
  pipeline publishes it.