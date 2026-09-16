# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine

# ---------------------------------------------------------
# Runtime dependencies
# ---------------------------------------------------------
RUN apk add --no-cache \
    freetype \
    libjpeg-turbo \
    libpng \
    libzip \
    postgresql-libs

# ---------------------------------------------------------
# Build dependencies
# ---------------------------------------------------------
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    linux-headers \
    postgresql-dev

# ---------------------------------------------------------
# PHP extensions
# ---------------------------------------------------------
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip \
        opcache

# ---------------------------------------------------------
# Remove build dependencies
# ---------------------------------------------------------
RUN apk del .build-deps \
    && rm -rf /var/cache/apk/*

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------
# Composer dependencies
# ---------------------------------------------------------
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# ---------------------------------------------------------
# Application
# ---------------------------------------------------------
COPY . .

# ---------------------------------------------------------
# Composer autoload
# ---------------------------------------------------------
RUN composer dump-autoload \
    --optimize \
    --no-dev \
    --classmap-authoritative \
    --no-scripts

# ---------------------------------------------------------
# Laravel package discovery
# ---------------------------------------------------------
RUN php artisan package:discover --ansi

# ---------------------------------------------------------
# Laravel caches
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
# PHP configuration
# ---------------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# ---------------------------------------------------------
# Runtime
# ---------------------------------------------------------
USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]