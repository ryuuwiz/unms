#!/bin/sh
set -e

# ==============================================================================
# GOBILLING / UNMS Docker Entrypoint Script
# ==============================================================================

# Determine runtime user (sail in dev, www-data in prod)
if id "sail" >/dev/null 2>&1 && [ "$APP_ENV" != "production" ]; then
    APP_USER="sail"
else
    APP_USER="www-data"
fi

# Ensure storage directories exist in case of fresh volume mounts
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Fix ownership and permissions on volume mounts if started as root
if [ "$(id -u)" = "0" ]; then
    chown -R ${APP_USER}:${APP_USER} /var/www/html/storage /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
fi

# Wait for MySQL database if configured
if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    echo "Waiting for MySQL database at ${DB_HOST}:${DB_PORT:-3306}..."
    while ! nc -z "$DB_HOST" "${DB_PORT:-3306}"; do
        sleep 1
    done
    echo "MySQL database is ready!"
fi

# Wait for Redis if configured
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT:-6379}..."
    while ! nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}"; do
        sleep 1
    done
    echo "Redis is ready!"
fi

# Production Initialization (Only for Web/FPM entry)
if [ "$APP_ENV" = "production" ]; then
    # Ensure storage symlink exists
    if [ ! -L /var/www/html/public/storage ]; then
        echo "Creating storage symlink..."
        if [ "$(id -u)" = "0" ]; then
            su-exec ${APP_USER} php artisan storage:link --no-interaction || true
        else
            php artisan storage:link --no-interaction || true
        fi
    fi

    # Conditional database migration runner
    if [ "$RUN_MIGRATIONS" = "true" ] || [ "$RUN_MIGRATIONS" = "1" ]; then
        echo "Running database migrations..."
        if [ "$(id -u)" = "0" ]; then
            su-exec ${APP_USER} php artisan migrate --force --no-interaction
        else
            php artisan migrate --force --no-interaction
        fi
    fi

    # Warmup Laravel Caches
    echo "Warming up application caches..."
    if [ "$(id -u)" = "0" ]; then
        su-exec ${APP_USER} php artisan config:cache --no-interaction
        su-exec ${APP_USER} php artisan route:cache --no-interaction
        su-exec ${APP_USER} php artisan view:cache --no-interaction
        su-exec ${APP_USER} php artisan event:cache --no-interaction
    else
        php artisan config:cache --no-interaction
        php artisan route:cache --no-interaction
        php artisan view:cache --no-interaction
        php artisan event:cache --no-interaction
    fi
fi

# Drop privileges to unprivileged APP_USER if started as root
if [ "$(id -u)" = "0" ]; then
    exec su-exec ${APP_USER} "$@"
else
    exec "$@"
fi
