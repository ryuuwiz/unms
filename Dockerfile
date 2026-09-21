# syntax=docker/dockerfile:1
# =============================================================================
# GOBILLING — Production Dockerfile untuk Dokploy (single service)
#
# Satu image, satu container: Caddy (web) + PHP-FPM + Horizon (queue) +
# scheduler, semuanya diawasi supervisord. MySQL, Redis, dan RustFS berjalan
# sebagai service Dokploy terpisah — image ini hanya perlu bisa menjangkau
# mereka lewat env var (DB_HOST, REDIS_HOST, AWS_ENDPOINT, dst).
#
# Rasionale arsitektur: docs/adr/0036-single-service-dokploy-deployment.md
# =============================================================================

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# -----------------------------------------------------------------------------
# Stage: vendor — install dependensi PHP dengan Composer
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
# --ignore-platform-reqs: stage ini cuma menyusun berkas sumber PHP, tidak
# pernah mengeksekusi kode aplikasi, jadi ekstensi PHP yang dibutuhkan saat
# *runtime* (gd, redis, dll) tidak perlu ada di image composer ini.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && composer clear-cache

# -----------------------------------------------------------------------------
# Stage: frontend — build aset Vite/Tailwind
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app/vendor ./vendor

COPY . .
RUN npm run build

# -----------------------------------------------------------------------------
# Stage: runtime — Caddy + PHP-FPM + Horizon + scheduler via supervisord
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    TZ=Asia/Jakarta \
    APP_MAINTENANCE_DRIVER=cache \
    APP_MAINTENANCE_STORE=redis

# --- Paket OS -----------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        supervisor \
        curl \
        ca-certificates \
        tzdata \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    && ln -sf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && rm -rf /var/lib/apt/lists/*

# --- Binary Caddy (dibangun oleh image resmi Caddy, tinggal disalin) --------
COPY --from=caddy:2-alpine /usr/bin/caddy /usr/local/bin/caddy

# --- Ekstensi PHP --------------------------------------------------------------
# REDIS_CLIENT=phpredis di .env.docker.example -> butuh ekstensi pecl asli;
# predis/predis di composer.json cuma fallback client, bukan yang dipakai.
RUN curl -sSLf \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        sockets \
        zip

# --- Samakan UID/GID www-data (1000) di seluruh fleet -------------------------
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

WORKDIR /var/www/html

# --- Berkas konfigurasi runtime ------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor.d/ /etc/supervisor/conf.d/

RUN caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile

# --- Kode aplikasi ---------------------------------------------------------------
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

RUN php artisan package:discover --ansi \
    && php artisan livewire:publish --assets --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions \
        storage/framework/testing storage/framework/views storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

# Healthcheck HTTP-only: membuktikan Caddy+PHP-FPM hidup. Kesehatan Horizon
# dipantau terpisah lewat command terjadwal `horizon:monitor-health`.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]