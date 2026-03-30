#!/bin/sh
set -e

# Run migrations on startup (safe with --force for production)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

# Cache config/routes/views for production
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing for production..."
    php artisan optimize
fi

# Ensure storage link exists
php artisan storage:link 2>/dev/null || true

exec "$@"
