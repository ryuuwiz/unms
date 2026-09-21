#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    touch .env
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set in the container environment." >&2
    exit 1
fi

# Build-time chown is baked into the image layer; a Dokploy volume mounted
# over storage/ at runtime silently overrides it. Re-apply on every boot.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# docker/php.ini sets opcache.file_cache to this path, but nothing else
# creates it -- PHP fatals on startup ("... must be a full path of an
# accessible directory") for every process (php-fpm, horizon, this script's
# own artisan calls) without it. Needed by both root (this script) and
# www-data (php-fpm/horizon/scheduler, per php-fpm's pool `user`), and /tmp
# may be a fresh tmpfs per container start regardless of the image layer.
mkdir -p /tmp/opcache-file-cache
chmod 1777 /tmp/opcache-file-cache

php artisan app:wait-for-services --no-interaction
php artisan optimize:clear --no-interaction || true
php artisan app:migrate-once --no-interaction

# Not `|| true`: fail the boot loudly on a bad cache build instead of
# serving 500s against a stale/missing cache.
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction

# Cache commands above wrote bootstrap/cache/*.php as root; re-chown so
# www-data can overwrite them on a later runtime cache:clear.
chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link --no-interaction || true
php artisan app:ensure-public-media-bucket --no-interaction || true

exec "$@"
