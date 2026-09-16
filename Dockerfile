# syntax=docker/dockerfile:1

########################################
# Stage 1: Composer dependencies
########################################
FROM php:8.5-cli-bookworm AS vendor

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP extensions required by the application/Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --classmap-authoritative


########################################
# Stage 2: Frontend assets
########################################
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

# Flux CSS is loaded from vendor/livewire/flux
COPY --from=vendor /app/vendor ./vendor

RUN npm run build


########################################
# Stage 3: Production runtime
########################################
FROM php:8.5-fpm-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false

WORKDIR /var/www/html


########################################
# Runtime packages
########################################
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libicu72 \
        libpq5 \
        libzip4 \
        libpng16-16 \
        libjpeg62-turbo \
        libfreetype6 \
    && rm -rf /var/lib/apt/lists/*


########################################
# PHP extensions
########################################
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear


########################################
# Application
########################################
COPY . .

COPY --from=vendor /app/vendor ./vendor

COPY --from=frontend /app/public/build ./public/build


########################################
# Laravel directories
########################################
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache


########################################
# Laravel package discovery
########################################
RUN php artisan package:discover --ansi


########################################
# Configuration
########################################
COPY docker/nginx.conf \
    /etc/nginx/nginx.conf

COPY docker/supervisord.conf \
    /etc/supervisor/conf.d/supervisord.conf

COPY docker/php.ini \
    /usr/local/etc/php/conf.d/zz-custom.ini

COPY docker/www.conf \
    /usr/local/etc/php-fpm.d/www.conf

COPY docker/entrypoint.sh \
    /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh


########################################
# Validate configuration
########################################
RUN php-fpm -t \
    && nginx -t


########################################
# Container
########################################
EXPOSE 80

HEALTHCHECK \
    --interval=30s \
    --timeout=5s \
    --start-period=15s \
    --retries=3 \
    CMD php -r \
        '$r=@file_get_contents("http://127.0.0.1/up"); exit($r === false ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-n"]