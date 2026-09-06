#!/bin/bash
set -e

echo "==> Initializing GOBILLING container..."

# 1. Map Railway database variables if DB_HOST is unset or dummy 'mysql'
if [ -n "$MYSQL_URL" ] && [ -z "$DB_URL" ]; then
    export DB_URL="$MYSQL_URL"
fi
if [ -n "$DATABASE_URL" ] && [ -z "$DB_URL" ]; then
    export DB_URL="$DATABASE_URL"
fi
if [ "$DB_HOST" = "mysql" ] && [ -n "$MYSQLHOST" ]; then
    echo "==> Detected Railway MYSQLHOST, overriding dummy DB_HOST=mysql with $MYSQLHOST"
    export DB_HOST="$MYSQLHOST"
fi
if [ -n "$MYSQLPORT" ] && ([ -z "$DB_PORT" ] || [ "$DB_PORT" = "3306" ]); then
    export DB_PORT="$MYSQLPORT"
fi
if [ -n "$MYSQLUSER" ] && ([ -z "$DB_USERNAME" ] || [ "$DB_USERNAME" = "gobilling" ]); then
    export DB_USERNAME="$MYSQLUSER"
fi
if [ -n "$MYSQLPASSWORD" ] && ([ -z "$DB_PASSWORD" ] || [ "$DB_PASSWORD" = "secret" ]); then
    export DB_PASSWORD="$MYSQLPASSWORD"
fi
if [ -n "$MYSQLDATABASE" ] && ([ -z "$DB_DATABASE" ] || [ "$DB_DATABASE" = "gobilling" ]); then
    export DB_DATABASE="$MYSQLDATABASE"
fi

# Redis Railway fallback
if [ -n "$REDIS_URL" ]; then
    export REDIS_URL="$REDIS_URL"
elif [ -n "$REDISHOST" ] && ([ -z "$REDIS_HOST" ] || [ "$REDIS_HOST" = "redis" ]); then
    export REDIS_HOST="$REDISHOST"
    [ -n "$REDISPORT" ] && export REDIS_PORT="$REDISPORT"
    [ -n "$REDISPASSWORD" ] && export REDIS_PASSWORD="$REDISPASSWORD"
    [ -n "$REDISUSER" ] && export REDIS_USERNAME="$REDISUSER"
fi

# 2. Ensure directories and permissions exist
mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache
chmod -R a+rw storage bootstrap/cache || true

# 3. Clear build-time cached configs so runtime environment variables are respected
php artisan optimize:clear || true

# 4. Generate APP_KEY if missing
if [ -z "$APP_KEY" ]; then
    echo "==> APP_KEY not provided. Generating application key..."
    php artisan key:generate --force || true
fi

# 5. Ensure SQLite database file exists if sqlite driver is used
if [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p database
    touch database/database.sqlite
fi

# 6. Handle Database Migrations
if [ "$RAILPACK_SKIP_MIGRATIONS" = "true" ] || [ "$SKIP_MIGRATIONS" = "true" ]; then
    echo "==> Skipping migrations (RAILPACK_SKIP_MIGRATIONS=true)."
else
    echo "==> Checking database connection ($DB_CONNECTION)..."
    DB_CONNECTED=false
    MAX_TRIES=15
    COUNT=1

    while [ $COUNT -le $MAX_TRIES ]; do
        if php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
            DB_CONNECTED=true
            echo "==> Database connection established successfully!"
            break
        fi
        echo "==> Waiting for database connection (attempt $COUNT/$MAX_TRIES)..."
        sleep 2
        COUNT=$((COUNT + 1))
    done

    if [ "$DB_CONNECTED" = "true" ]; then
        echo "==> Running database migrations..."
        php artisan migrate --force || {
            echo "==> Warning: Migration command encountered an error."
            if [ "$IGNORE_MIGRATION_FAILURES" = "true" ] || [ "$FAIL_ON_MIGRATION_ERROR" = "false" ]; then
                echo "==> Continuing startup despite migration failure."
            else
                exit 1
            fi
        }
    else
        echo "==> Warning: Could not connect to database after $MAX_TRIES attempts."
        if [ "$IGNORE_MIGRATION_FAILURES" = "true" ] || [ "$FAIL_ON_MIGRATION_ERROR" = "false" ]; then
            echo "==> Continuing startup without running migrations."
        else
            echo "==> Executing migrate --force to display detailed connection/migration error..."
            php artisan migrate --force
        fi
    fi
fi

# 7. Storage symlink
php artisan storage:link || true

# 8. Optimize caches for production
if [ "$APP_ENV" = "production" ]; then
    echo "==> Optimizing caches for production..."
    php artisan optimize || true
fi

echo "==> Starting FrankenPHP web server..."
exec docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
