#!/usr/bin/env bash
# deploy.sh — run on the production server inside the repo directory.
# Usage: ./deploy.sh [--build] [--migrate]
#   --build    rebuild the Docker image and recreate the container
#   --migrate  run php artisan migrate --force inside the running container
set -euo pipefail

BUILD=false
MIGRATE=false

for arg in "$@"; do
  case "$arg" in
    --build)   BUILD=true ;;
    --migrate) MIGRATE=true ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

echo "==> Pulling latest code..."
git remote set-url origin "https://github.com/kapkory/farm-record-backend.git"
git fetch origin main
git reset --hard origin/main

if $BUILD; then
  echo "==> Building Docker image..."
  docker compose build app

  echo "==> Recreating container..."
  docker compose up -d --force-recreate --no-deps app
fi

if $MIGRATE; then
  echo "==> Running database migrations..."
  docker compose exec -T app php artisan migrate --force
fi

echo "==> Deploy complete."
