#!/bin/bash
set -e

cd /var/www/html

# This image is designed to run without a committed .env file in production --
# all configuration comes from real container environment variables injected
# by Docker/Dokploy (see .env.docker.example for the full list). Laravel's
# Dotenv loader is immutable and safeLoad()s silently when no .env exists, so
# omitting the file entirely is supported and intentional.
#
# We still create an empty .env here (not copied from .env.example, which
# carries local-dev defaults like APP_ENV=local/APP_DEBUG=true/DB_HOST=127.0.0.1)
# purely because some package service providers call `env()` before
# config-caching and expect the file to exist; an empty file changes nothing
# since every real key is already present in the process environment.
if [ ! -f .env ]; then
    touch .env
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set in the container environment. Refusing to start with a silently-generated or missing key." >&2
    exit 1
fi

# Clear any stale bytecode/config/route/view cache before rebuilding it below.
# Guards against bootstrap/cache/*.php or storage/framework/cache/* getting
# baked into the image (e.g. if .dockerignore ever misses them) and against
# leftover cache from a previous image layer.
php artisan optimize:clear --no-interaction || true

# Guarded by a distributed Redis lock so concurrent replica boots (rolling
# deploys, 2+ replicas) never run migrations against the same database at
# the same time. Falls back to skipping (not failing) if another replica
# already holds the lock -- see app/Console/Commands/MigrateOnceCommand.php.
php artisan app:migrate-once --no-interaction

php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true
php artisan event:cache --no-interaction || true

php artisan storage:link --no-interaction || true

php artisan app:ensure-public-media-bucket --no-interaction || true

exec "$@"