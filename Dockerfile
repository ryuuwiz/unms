FROM php:8.3-fpm-alpine

# ---------------------------------------------------------
# System dependencies
# ---------------------------------------------------------
RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git

# ---------------------------------------------------------
# PHP extensions
# ---------------------------------------------------------
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    zip \
    opcache

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------
# Application
# ---------------------------------------------------------
WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload \
    --optimize \
    --no-dev

# ---------------------------------------------------------
# Laravel optimization
# ---------------------------------------------------------
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# ---------------------------------------------------------
# Permissions
# ---------------------------------------------------------
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# ---------------------------------------------------------
# PHP-FPM
# ---------------------------------------------------------
USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]