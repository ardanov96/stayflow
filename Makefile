.PHONY: help setup setup-native up down restart logs shell shell-root artisan composer npm build test test-unit test-feature test-arch lint lint-check stan stan-fix ci fresh seed migrate queue horizon tinker ide-helper stop prune reset docker-prune docker-build

# ---------- Convenience --------------------------------------------------------

help:	## Show this help.
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "\033[36m%-22s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

# ---------- First-time setup ---------------------------------------------------

setup:	## Fresh environment: build images, start containers, migrate, seed (Docker / Sail)
	@if [ ! -f .env ]; then cp .env.sail .env; fi
	./vendor/bin/sail up -d
	./vendor/bin/sail artisan key:generate
	./vendor/bin/sail artisan migrate --seed
	@echo ""
	@echo "  Stayflow is running at http://localhost"
	@echo "    Mailpit UI : http://localhost:8025"
	@echo "    MinIO UI   : http://localhost:9001  (user/pass from .env.sail)"

setup-native:	## Fresh environment for native PHP (no Docker). Copies .env.example, runs composer + migrate + seed.
	@if [ ! -f .env ]; then cp .env.example .env; fi
	composer install --prefer-dist --no-interaction
	php artisan key:generate
	php artisan migrate --seed

# ---------- Docker / Sail ------------------------------------------------------

up:	## Start Sail containers in the background.
	./vendor/bin/sail up -d

down:	## Stop Sail containers.
	./vendor/bin/sail down

restart:	## Restart Sail containers.
	./vendor/bin/sail restart

logs:	## Tail logs from all services.
	./vendor/bin/sail logs -f

shell:	## Open a shell as the sail user inside the laravel container.
	./vendor/bin/sail shell

shell-root:	## Open a root shell inside the laravel container.
	./vendor/bin/sail root-shell

docker-build:	## Rebuild the laravel.test image (after Dockerfile or runtime changes).
	./vendor/bin/sail build

docker-prune:	## Remove stopped Sail containers, dangling images, and unused volumes.
	docker system prune -f

# ---------- Composer / Artisan shortcuts (via Sail) ---------------------------

artisan:	## Run any artisan command via Sail (e.g. `make artisan cmd="migrate:status"`).
	./vendor/bin/sail artisan $(cmd)

composer:	## Run any composer command via Sail (e.g. `make composer cmd="require foo/bar"`).
	./vendor/bin/sail composer $(cmd)

npm:	## Run any npm script via Sail (e.g. `make npm cmd="run build"`).
	./vendor/bin/sail npm $(cmd)

build:	## Build frontend assets.
	./vendor/bin/sail npm run build

tinker:	## Open Laravel Tinker via Sail.
	./vendor/bin/sail tinker

queue:	## Start the queue worker (foreground).
	./vendor/bin/sail artisan queue:work

horizon:	## Open Horizon dashboard (if installed).
	./vendor/bin/sail artisan horizon

# ---------- Database -----------------------------------------------------------

migrate:	## Run outstanding migrations.
	./vendor/bin/sail artisan migrate

fresh:	## Drop all tables and re-run all migrations + seeders.
	./vendor/bin/sail artisan migrate:fresh --seed

seed:	## Run database seeders.
	./vendor/bin/sail artisan db:seed

reset:	## Stop containers, remove volumes, run `make setup`. DESTRUCTIVE.
	./vendor/bin/sail down -v
	$(MAKE) setup

# ---------- Quality pipeline ---------------------------------------------------

lint:	## Auto-format code with Pint.
	./vendor/bin/sail pint

lint-check:	## Check formatting (CI mode, no changes).
	./vendor/bin/sail pint --test

stan:	## Run PHPStan / Larastan static analysis.
	./vendor/bin/sail php vendor/bin/phpstan analyse --memory-limit=1G --no-progress

stan-fix:	## Run PHPStan with auto-fix where supported.
	./vendor/bin/sail php vendor/bin/phpstan analyse --memory-limit=1G --no-progress --no-fail-on-warning

test:	## Run the full Pest test suite.
	./vendor/bin/sail pest --colors=always

test-unit:	## Run unit tests only.
	./vendor/bin/sail pest tests/Unit --colors=always

test-feature:	## Run feature tests only.
	./vendor/bin/sail pest tests/Feature --colors=always

test-arch:	## Run architecture tests only.
	./vendor/bin/sail pest tests/Arch --colors=always

ci:	## Full pipeline (lint-check + stan + test) - must be green before PR.
	./vendor/bin/sail pint --test
	./vendor/bin/sail php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	./vendor/bin/sail pest --colors=always

# ---------- Misc ---------------------------------------------------------------

stop:	## Stop background queue / horizon / pail processes.
	./vendor/bin/sail artisan queue:restart 2>/dev/null || true

prune:	## Drop old log files.
	./vendor/bin/sail artisan log:clear

ide-helper:	## Regenerate IDE helper files (php-ide-stub + meta) - no-op until php-ide-helper is installed.
	@echo "Install barryvdh/laravel-ide-helper before using this target."