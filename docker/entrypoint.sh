#!/bin/sh
set -e

# Cache configuration at boot using runtime environment variables
php artisan config:cache

# Execute web-specific initialization
if [ "$1" = "php-fpm" ]; then
    php artisan route:cache
    php artisan view:cache

    # Run database migrations safely across concurrent container boots
    php artisan migrate --force --isolated
fi

# Hand over PID 1 process control to CMD
exec "$@"