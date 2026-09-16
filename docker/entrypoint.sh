#!/bin/sh
set -eu

cd /var/www/html

: "${APP_KEY:?FATAL: APP_KEY is not set in the container environment}"


########################################
# Runtime permissions
########################################

chown -R www-data:www-data \
    storage \
    bootstrap/cache

find storage bootstrap/cache \
    -type d \
    -exec chmod 775 {} \;

find storage bootstrap/cache \
    -type f \
    -exec chmod 664 {} \;


########################################
# External services
########################################

php artisan app:wait-for-services --no-interaction


########################################
# Database
########################################

php artisan app:migrate-once --no-interaction


########################################
# Laravel caches
########################################

php artisan optimize:clear --no-interaction

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction


########################################
# Restore permissions
########################################

chown -R www-data:www-data \
    storage \
    bootstrap/cache


########################################
# Storage
########################################

if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi


########################################
# Media storage
########################################

if ! php artisan app:ensure-public-media-bucket --no-interaction; then
    echo "WARNING: Unable to ensure public media bucket." >&2
fi


########################################
# Start Supervisor
########################################

exec "$@"