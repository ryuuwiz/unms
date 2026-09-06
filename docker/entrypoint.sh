#!/usr/bin/env bash
set -euo pipefail

echo "[entrypoint] waiting for database..."
until php -r "
try {
    new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    exit(0);
} catch (\Throwable \$e) {
    exit(1);
}
"; do
  sleep 2
done
echo "[entrypoint] database is up."

# Only the container that's actually serving requests should run migrations.
# Guard with RUN_MIGRATIONS=true on exactly one service (see docker-compose).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] running migrations..."
  php artisan migrate --force
  echo "[entrypoint] ensuring storage symlink (medialibrary public disk)..."
  php artisan storage:link || true
fi

if [ "${APP_ENV:-production}" = "production" ]; then
  echo "[entrypoint] caching config/routes/views..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
fi

exec "$@"
