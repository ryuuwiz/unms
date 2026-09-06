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

# Sensible production defaults
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export CACHE_STORE="${CACHE_STORE:-redis}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-redis}"
export SESSION_DRIVER="${SESSION_DRIVER:-redis}"

# Prevent accidental SQLite / database cache/session driver leak when running in container with MySQL and Redis services
if [ "$DB_CONNECTION" = "sqlite" ] && [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Warning: DB_CONNECTION was set to 'sqlite' but DB_HOST='${DB_HOST}' is configured. Overriding DB_CONNECTION to 'mysql'."
    export DB_CONNECTION="mysql"
fi

if [ -n "$REDIS_HOST" ]; then
    if [ "$CACHE_STORE" = "database" ] || [ "$CACHE_STORE" = "file" ] || [ -z "$CACHE_STORE" ]; then
        echo "[entrypoint] Notice: Setting CACHE_STORE='redis' since REDIS_HOST='${REDIS_HOST}' is configured."
        export CACHE_STORE="redis"
    fi
    if [ "$SESSION_DRIVER" = "database" ] || [ -z "$SESSION_DRIVER" ]; then
        echo "[entrypoint] Notice: Setting SESSION_DRIVER='redis' since REDIS_HOST='${REDIS_HOST}' is configured."
        export SESSION_DRIVER="redis"
    fi
    if [ "$QUEUE_CONNECTION" = "database" ] || [ -z "$QUEUE_CONNECTION" ]; then
        echo "[entrypoint] Notice: Setting QUEUE_CONNECTION='redis' since REDIS_HOST='${REDIS_HOST}' is configured."
        export QUEUE_CONNECTION="redis"
    fi
fi

# Safety net: Ensure SQLite database file and directory exist with correct permissions if SQLite is ever used
mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/database
    chmod -R 775 /var/www/html/database
    chmod 664 /var/www/html/database/database.sqlite
fi

# Ensure APP_KEY exists so Laravel never crashes with MissingAppKeyException
if [ -z "$APP_KEY" ]; then
    if [ -f /var/www/html/.env ] && grep -q "^APP_KEY=base64:" /var/www/html/.env; then
        export APP_KEY=$(grep "^APP_KEY=" /var/www/html/.env | head -n 1 | cut -d '=' -f2-)
    fi
fi

if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] WARNING: APP_KEY is empty! Auto-generating encryption key for application stability..."
    if [ ! -f /var/www/html/.env ]; then
        touch /var/www/html/.env
        chown www-data:www-data /var/www/html/.env
    fi
    php artisan key:generate --force || true
    if grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
        export APP_KEY=$(grep "^APP_KEY=" /var/www/html/.env | head -n 1 | cut -d '=' -f2-)
    fi
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

# Clear any stale cached configuration from previous builds
if [ -f /var/www/html/artisan ]; then
    run_as_app php artisan config:clear || true
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
