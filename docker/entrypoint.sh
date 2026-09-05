#!/bin/sh
set -e

echo "[entrypoint] Initializing UNMS / GOBILLING container..."

# Ensure required storage and bootstrap cache directories exist
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/log/supervisor

# Fix permissions for storage and cache if running as root
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 775 {} +
    find /var/www/html/storage /var/www/html/bootstrap/cache -type f ! -name ".gitignore" -exec chmod 664 {} +
fi

# Function to execute a command as www-data if running as root
run_as_app() {
    if [ "$(id -u)" = "0" ]; then
        su-exec www-data "$@"
    else
        "$@"
    fi
}

# Warn if APP_KEY is unset in production
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] WARNING: APP_KEY is empty. Ensure APP_KEY is configured in your Dokploy environment."
fi

# Wait for database if DB_HOST is configured and DB_CONNECTION is not sqlite
DB_CONN="${DB_CONNECTION:-mysql}"
if [ -n "$DB_HOST" ] && [ "$DB_CONN" != "sqlite" ]; then
    DB_PORT="${DB_PORT:-3306}"
    echo "[entrypoint] Waiting for database at ${DB_HOST}:${DB_PORT}..."
    max_retries=45
    count=0
    while ! nc -z "$DB_HOST" "$DB_PORT" >/dev/null 2>&1; do
        count=$((count + 1))
        if [ "$count" -ge "$max_retries" ]; then
            echo "[entrypoint] Error: Timed out waiting for database at ${DB_HOST}:${DB_PORT}"
            exit 1
        fi
        sleep 1
    done
    echo "[entrypoint] Database is reachable."
fi

# Wait for redis if REDIS_HOST is configured
if [ -n "$REDIS_HOST" ] && [ "$QUEUE_CONNECTION" = "redis" ]; then
    REDIS_PORT="${REDIS_PORT:-6379}"
    echo "[entrypoint] Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT}..."
    max_retries=30
    count=0
    while ! nc -z "$REDIS_HOST" "$REDIS_PORT" >/dev/null 2>&1; do
        count=$((count + 1))
        if [ "$count" -ge "$max_retries" ]; then
            echo "[entrypoint] Error: Timed out waiting for Redis at ${REDIS_HOST}:${REDIS_PORT}"
            exit 1
        fi
        sleep 1
    done
    echo "[entrypoint] Redis is reachable."
fi

# Discover package manifests cleanly for production (prevents cached dev providers from crashing boot)
if [ -f /var/www/html/artisan ]; then
    echo "[entrypoint] Discovering packages..."
    run_as_app php artisan package:discover --ansi || true
fi

# In production or when explicitly enabled, create storage symlink
if [ "$APP_ENV" = "production" ] || [ "$CREATE_STORAGE_LINK" = "true" ]; then
    if [ ! -L /var/www/html/public/storage ]; then
        echo "[entrypoint] Creating storage symlink..."
        run_as_app php artisan storage:link --force || true
    fi
fi

# Run database migrations if RUN_MIGRATIONS is set to true
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "[entrypoint] Running database migrations (--force)..."
    run_as_app php artisan migrate --force --no-interaction
fi

# Cache configuration, routes, and views if requested
if [ "$CACHE_ON_STARTUP" = "true" ]; then
    echo "[entrypoint] Caching configuration, routes, and views..."
    run_as_app php artisan config:cache || true
    run_as_app php artisan route:cache || true
    run_as_app php artisan view:cache || true
fi

# Disable Horizon or Scheduler supervisor workers if requested (useful for multi-container horizontal scale)
if [ "$DISABLE_HORIZON" = "true" ]; then
    echo "[entrypoint] Disabling Horizon supervisor program..."
    sed -i '/\[program:horizon\]/,/\[program:/ s/autostart=true/autostart=false/' /etc/supervisord.conf 2>/dev/null || true
fi

if [ "$DISABLE_SCHEDULER" = "true" ]; then
    echo "[entrypoint] Disabling Scheduler supervisor program..."
    sed -i '/\[program:scheduler\]/,/\[program:/ s/autostart=true/autostart=false/' /etc/supervisord.conf 2>/dev/null || true
fi

echo "[entrypoint] Ready. Executing command: $@"

# Note: php-fpm and supervisord master processes must start as root to handle sockets and log descriptors,
# after which workers run as unprivileged user www-data.
case "$1" in
    *php-fpm*|*supervisord*)
        exec "$@"
        ;;
    *)
        if [ "$(id -u)" = "0" ]; then
            exec su-exec www-data "$@"
        else
            exec "$@"
        fi
        ;;
esac
