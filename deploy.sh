#!/usr/bin/env bash
# Zero-fuss redeploy: pull, rebuild, restart, migrate, re-cache.
set -euo pipefail
cd "$(dirname "$0")"

git pull --ff-only

docker compose -f compose.prod.yaml build app
docker compose -f compose.prod.yaml up -d --remove-orphans

# Wait for the app container to be ready, then migrate + cache.
docker compose -f compose.prod.yaml exec -T app php artisan migrate --force
docker compose -f compose.prod.yaml exec -T app php artisan optimize

# Queue workers hold old code in memory until restarted.
docker compose -f compose.prod.yaml restart queue scheduler

echo "Deployed. App status:"
docker compose -f compose.prod.yaml ps
