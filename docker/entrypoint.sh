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

# The Dockerfile chowns storage/bootstrap-cache to www-data at BUILD time, but that's
# baked into the image layer -- if Dokploy (or any operator) mounts a volume over
# storage/ for persistence, the volume's on-disk ownership silently overrides it at
# runtime. LOG_CHANNEL=stderr (see ADR-0036) avoids storage/logs/laravel.log for
# normal app logs, but Laravel's LogManager falls back to a hardcoded emergency
# logger at storage_path('logs/laravel.log') whenever the configured channel itself
# fails to write -- so storage/logs must stay writable regardless. Re-applying this
# on every boot (root here, before supervisord drops to www-data) is idempotent and
# guards against both a stale volume and a fresh empty one.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Database and Redis run as separate services (Dokploy) with no guaranteed
# startup order relative to this container -- without this, a boot race fails
# app:migrate-once (its Cache::lock needs Redis) with a raw exception instead
# of a clear message. See app/Console/Commands/WaitForServicesCommand.php.
php artisan app:wait-for-services --no-interaction

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

# Deliberately NOT `|| true`: a failed config/route/view/event cache build
# (e.g. a bad env value, a ServiceProvider throwing during boot) used to be
# swallowed here, leaving `optimize:clear` having wiped the previous good
# cache and no new one written -- supervisord then happily started php-fpm
# against a broken bootstrap/cache with every HTTP request 500'ing and
# nothing useful in the logs, while `artisan horizon`/`schedule:run` (which
# don't necessarily exercise the same cached config path) kept working. Fail
# the boot loudly instead so the real exception lands in `docker logs` and
# the container visibly crash-loops rather than silently serving 500s.
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction

# Re-chown after the cache-building commands above: they run as root (like
# the rest of this script, before supervisord drops to www-data) and write
# fresh files into bootstrap/cache/*.php, so without this the php-fpm worker
# (www-data) can read but never overwrite them on a later `artisan cache:clear`
# triggered at runtime.
chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link --no-interaction || true

php artisan app:ensure-public-media-bucket --no-interaction || true

exec "$@"