#!/bin/sh
set -e

# These read real environment variables (DB creds, APP_KEY, etc.), so they
# must run at container start-up, not at image build time — caching them
# during build would bake in whatever (or nothing) was present then.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"