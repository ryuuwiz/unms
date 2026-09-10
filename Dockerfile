# syntax=docker/dockerfile:1.7
#
# Note: Debian Buster (Debian 10) is EOL and is not published for
# php:8.4 or node:24 upstream images, so this build uses Debian
# Bookworm (current stable) throughout instead — the closest
# supported Debian base for both stages.

########################################
# Stage 1: PHP dependencies (Composer)
########################################
FROM php:8.4-cli-bookworm AS vendor

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Mirrors the runtime stage's extension set so composer's platform-req
# check (ext-mbstring, ext-pdo_mysql, etc. declared by Laravel and its
# dependencies) passes during `composer install` — a mismatch here is
# the most common cause of a silent "exit code: 2" from this step.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        redis \
        sockets

COPY database/ database/
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

########################################
# Stage 2: Frontend build (Vite)
########################################
FROM node:24-bookworm AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

########################################
# Stage 3: Runtime image
########################################
FROM php:8.4-fpm-bookworm AS runtime

LABEL maintainer="Ryu"

ARG WWWUSER=1000
ARG WWWGROUP=1000

# --- System packages ---------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        bash \
        curl \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        tzdata \
    && ln -sf /usr/share/zoneinfo/Asia/Jakarta /etc/localtime \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ------------------------------------------------------
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        redis \
        sockets

# --- PHP configuration ---------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

# --- Nginx configuration ---------------------------------------------------
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-enabled/default

# --- Supervisor configuration ----------------------------------------------
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor.d/ /etc/supervisor/conf.d/

# --- Non-root user matching host UID/GID (adjust for Dokploy if needed) ---
RUN usermod -u ${WWWUSER} www-data \
    && groupmod -g ${WWWGROUP} www-data

WORKDIR /var/www/html

# --- Application code -------------------------------------------------
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# --- Permissions ---------------------------------------------------------
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -f http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]