#!/bin/sh
set -e

echo "==> Initializing GOBILLING container process..."

# Clear any stale cached packages/config from host or previous boots
rm -f bootstrap/cache/*.php

ROLE="${CONTAINER_ROLE:-app}"
if [ "$1" = "php" ] && [ "$2" = "artisan" ] && [ "$3" = "horizon" ]; then
    ROLE="horizon"
elif [ "$1" = "supercronic" ] || [ "$1" = "scheduler" ] || ([ "$1" = "php" ] && [ "$2" = "artisan" ] && [ "$3" = "schedule:work" ]); then
    ROLE="scheduler"
    if [ "$1" = "scheduler" ] || ([ "$1" = "php" ] && [ "$2" = "artisan" ] && [ "$3" = "schedule:work" ]); then
        set -- supercronic /etc/crontabs/laravel-cron
    fi
elif [ "$1" = "horizon" ]; then
    ROLE="horizon"
    set -- php artisan horizon
fi

# Ensure storage and cache directories exist with proper write permissions
mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

# Function to wait for MySQL database readiness
wait_for_mysql() {
    if [ "$DB_CONNECTION" = "mysql" ]; then
        echo "==> Waiting for MySQL database (${DB_HOST:-mysql}:${DB_PORT:-3306}) to be ready..."
        MAX_TRIES=30
        COUNT=1
        until php -r "
            \$host = getenv('DB_HOST') ?: 'mysql';
            \$port = getenv('DB_PORT') ?: '3306';
            \$db   = getenv('DB_DATABASE') ?: 'gobilling';
            \$user = getenv('DB_USERNAME') ?: 'gobilling';
            \$pass = getenv('DB_PASSWORD') ?: '';
            try {
                new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [PDO::ATTR_TIMEOUT => 2]);
                exit(0);
            } catch (Exception \$e) {
                exit(1);
            }
        " 2>/dev/null; do
            if [ $COUNT -ge $MAX_TRIES ]; then
                echo "==> Warning: MySQL database was not ready after $MAX_TRIES attempts. Continuing anyway..."
                break
            fi
            echo "    Database not ready yet (attempt $COUNT/$MAX_TRIES), sleeping 2s..."
            sleep 2
            COUNT=$((COUNT + 1))
        done
        echo "==> MySQL database is reachable."
    fi
}

# Function to wait for Redis readiness
wait_for_redis() {
    if [ -n "$REDIS_HOST" ]; then
        echo "==> Waiting for Redis (${REDIS_HOST:-redis}:${REDIS_PORT:-6379}) to be ready..."
        MAX_TRIES=20
        COUNT=1
        until php -r "
            \$host = getenv('REDIS_HOST') ?: 'redis';
            \$port = (int) (getenv('REDIS_PORT') ?: 6379);
            \$pass = getenv('REDIS_PASSWORD');
            try {
                \$r = new Redis();
                if (@\$r->connect(\$host, \$port, 2.0)) {
                    if (\$pass && \$pass !== 'null') {
                        \$r->auth(\$pass);
                    }
                    exit(\$r->ping() ? 0 : 1);
                }
                exit(1);
            } catch (Exception \$e) {
                exit(1);
            }
        " 2>/dev/null; do
            if [ $COUNT -ge $MAX_TRIES ]; then
                echo "==> Warning: Redis was not ready after $MAX_TRIES attempts. Continuing anyway..."
                break
            fi
            echo "    Redis not ready yet (attempt $COUNT/$MAX_TRIES), sleeping 2s..."
            sleep 2
            COUNT=$((COUNT + 1))
        done
        echo "==> Redis is reachable."
    fi
}

# Wait for essential dependencies
wait_for_mysql
wait_for_redis

# Execute role-specific startup tasks
case "$ROLE" in
    horizon)
        echo "==> Running as Horizon Queue Worker..."
        exec "$@"
        ;;
    scheduler)
        echo "==> Running as Supercronic Scheduled Tasks Daemon..."
        exec "$@"
        ;;
    app|*)
        echo "==> Running as Application Web Server..."

        # Generate APP_KEY if missing
        if [ -z "$APP_KEY" ]; then
            echo "==> APP_KEY is unset. Generating a new application key..."
            php artisan key:generate --force
        fi

        # Run database migrations if enabled
        if [ "$RUN_MIGRATIONS" = "true" ]; then
            echo "==> Executing database migrations (RUN_MIGRATIONS=true)..."
            php artisan migrate --force
        fi

        # Create public storage symlink
        php artisan storage:link --quiet || true

        # Optimize framework cache in production mode
        if [ "$APP_ENV" = "production" ]; then
            echo "==> Warming production cache (config, routes, views)..."
            php artisan optimize:clear --quiet || true
            php artisan optimize --quiet || true
            php artisan view:cache --quiet || true
        fi

        echo "==> Launching web server..."
        exec "$@"
        ;;
esac
