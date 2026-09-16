# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine

# ---------------------------------------------------------
# Build dependencies
# ---------------------------------------------------------
RUN apk add --no-cache \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        postgresql-dev

# ---------------------------------------------------------
# Configure PHP extensions
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

# ---------------------------------------------------------
# Application
# ---------------------------------------------------------
WORKDIR /var/www/html

# Copy dependency definitions first for Docker layer caching
COPY composer.json composer.lock ./

# Install production dependencies
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# Copy application source
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload \
        --optimize \
        --no-dev \
        --classmap-authoritative

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
# Runtime
# ---------------------------------------------------------
USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]