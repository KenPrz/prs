# ============================================
# Laravel Sail Makefile
# ============================================
# Usage:
#   make <command>
#
# Example:
#   make up
#   make migrate
#   make test
#
# --------------------------------------------

SAIL = ./vendor/bin/sail
ARTISAN = $(SAIL) artisan
COMPOSER = $(SAIL) composer
NPM = $(SAIL) npm
.DEFAULT_GOAL := help

# ============================================
# Application Lifecycle
# ============================================

## Build and start containers (detached)
build:
	$(SAIL) up -d --build

## Start containers
up:
	$(SAIL) up -d

## Stop containers
down:
	$(SAIL) down

## Restart containers
restart:
	$(SAIL) down && $(SAIL) up -d

## Destroy containers, networks, and volumes (DANGEROUS)
destroy:
	$(SAIL) down -v --remove-orphans

## Access app container shell
shell:
	$(SAIL) shell

## Access root shell
root:
	$(SAIL) root-shell

# ============================================
# Logs & Monitoring
# ============================================

## View all logs
logs:
	$(SAIL) logs

## Follow logs (real-time)
logs-follow:
	$(SAIL) logs -f

## Tail last 100 lines
logs-tail:
	$(SAIL) logs --tail=100

# ============================================
# Database
# ============================================

## Run migrations
migrate:
	$(ARTISAN) migrate

## Fresh migration (drop all tables)
migrate-fresh:
	$(ARTISAN) migrate:fresh

## Fresh migration with seeding
migrate-refresh:
	$(ARTISAN) migrate:refresh

## Seed database
seed:
	$(ARTISAN) db:seed

## Fresh migration + seed
seed-fresh:
	$(ARTISAN) migrate:fresh --seed

## Rollback last migration batch
rollback:
	$(ARTISAN) migrate:rollback

## Reset all migrations
reset:
	$(ARTISAN) migrate:reset

## Open database CLI (MySQL/Postgres)
db:
	$(SAIL) mysql

# ============================================
# Cache & Optimization
# ============================================

## Clear application cache
cache-clear:
	$(ARTISAN) cache:clear

## Clear config cache
config-clear:
	$(ARTISAN) config:clear

## Cache config
config-cache:
	$(ARTISAN) config:cache

## Clear route cache
route-clear:
	$(ARTISAN) route:clear

## Cache routes
route-cache:
	$(ARTISAN) route:cache

## Clear view cache
view-clear:
	$(ARTISAN) view:clear

## Optimize application
optimize:
	$(ARTISAN) optimize

## Clear all caches
optimize-clear:
	$(ARTISAN) optimize:clear

# ============================================
# Queues & Jobs
# ============================================

## Start queue worker
queue-work:
	$(ARTISAN) queue:work

## Restart queue workers
queue-restart:
	$(ARTISAN) queue:restart

## Monitor failed jobs
queue-failed:
	$(ARTISAN) queue:failed

## Retry failed jobs
queue-retry:
	$(ARTISAN) queue:retry all

# ============================================
# Scheduler
# ============================================

## Run scheduled tasks manually
schedule-run:
	$(ARTISAN) schedule:run

# ============================================
# Testing
# ============================================

## Run PHPUnit tests
test:
	$(SAIL) test

## Run tests with coverage
test-coverage:
	$(SAIL) test --coverage

## Run specific test file
test-file:
	$(SAIL) test $(file)

# ============================================
# Composer
# ============================================

## Install PHP dependencies
composer-install:
	$(COMPOSER) install

## Update PHP dependencies
composer-update:
	$(COMPOSER) update

## Dump autoload
composer-dump:
	$(COMPOSER) dump-autoload

# ============================================
# NPM / Frontend
# ============================================

## Install Node dependencies
npm-install:
	$(NPM) install

## Run dev build
npm-dev:
	$(NPM) run dev

## Run production build
npm-build:
	$(NPM) run build

## Watch files
npm-watch:
	$(NPM) run dev -- --watch

# ============================================
# Artisan Shortcuts
# ============================================

## Generate app key
key-generate:
	$(ARTISAN) key:generate

## Create storage symlink
storage-link:
	$(ARTISAN) storage:link

## Run tinker
tinker:
	$(ARTISAN) tinker

## List routes
routes:
	$(ARTISAN) route:list

## List all Artisan commands
artisan:
	$(ARTISAN)

# ============================================
# Permissions (useful for Linux hosts)
# ============================================

## Fix storage and cache permissions
permissions:
	$(SAIL) exec laravel.test chmod -R 775 storage bootstrap/cache

# ============================================
# Utility
# ============================================

## Run arbitrary Artisan command
# Usage: make art cmd="migrate --seed"
art:
	$(ARTISAN) $(cmd)

## Run arbitrary Composer command
# Usage: make comp cmd="require spatie/laravel-permission"
comp:
	$(COMPOSER) $(cmd)

## Run arbitrary NPM command
# Usage: make node cmd="run lint"
node:
	$(NPM) $(cmd)

# ============================================
# Help
# ============================================

## Show available commands
help:
	@awk '\
		/^## / { desc = substr($$0, 4); next } \
		/^[a-zA-Z0-9_.-]+:/ { \
			target = $$1; sub(/:.*/, "", target); \
			if (desc != "") printf "\033[36m%-25s\033[0m %s\n", target, desc; \
			desc = ""; \
		} \
	' $(MAKEFILE_LIST)