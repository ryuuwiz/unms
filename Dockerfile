# syntax=docker/dockerfile:1.7
#
# Note: Debian Buster (Debian 10) is EOL and is not published for
# php:8.4 or node:24 upstream images, so this build uses Debian
# Bookworm (current stable) throughout instead — the closest
# supported Debian base for both stages.
#
# PHP_EXT_PACKAGES lists the apt -dev headers needed by install-php-extensions
# to compile the runtime extensions below. It is duplicated (not shared via a
# base stage) between the `vendor` and `runtime` stages on purpose: `vendor`
# is a throwaway CLI build stage while `runtime` additionally needs supervisor/
# bash/curl/tzdata for process supervision. Keep both lists in sync manually
# whenever the extension set changes.
ARG PHP_EXT_PACKAGES="libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev"

########################################
# Stage 1: PHP dependencies (Composer)
########################################
FROM php:8.4-cli-bookworm AS vendor

ARG PHP_EXT_PACKAGES

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        ${PHP_EXT_PACKAGES} \
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

# Dependency manifests copied first (and database/ after) so `composer install`
# only re-runs when the lockfile actually changes, not on every source edit.
COPY composer.json composer.lock ./
COPY database/ database/

# Cache mount persists Composer's package archive across builds so a lockfile
# change only re-downloads the packages that actually changed.
RUN --mount=type=cache,target=/tmp/cache,sharing=locked \
    composer install \
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
RUN --mount=type=cache,target=/root/.npm \
    npm ci

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
ARG PHP_EXT_PACKAGES

# --- System packages ---------------------------------------------------
# git/unzip are deliberately NOT installed here: nothing in the app or
# entrypoint.sh shells out to either (composer isn't even present in this
# stage), so they'd be pure dead weight in every pushed layer. If an
# operator ever needs them for a one-off `docker exec` task, `apt-get
# install -y git unzip` on the live container is a cheap, rare cost.
RUN apt-get update && apt-get install -y --no-install-recommends \
        supervisor \
        bash \
        curl \
        tzdata \
    && ln -sf /usr/share/zoneinfo/Asia/Jakarta /etc/localtime \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ------------------------------------------------------
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# PHP_EXT_PACKAGES (the -dev/header packages) are installed AND purged again
# within this one RUN, unlike the `vendor` stage where they're left in place.
# `vendor` is a throwaway build stage that's never pushed; this `runtime`
# stage is the image that gets exported and pushed to the registry, so any
# package left installed here becomes bytes in a pushed layer. Purging in a
# later, separate RUN wouldn't reclaim that space -- Docker layers are
# additive, so the headers would still exist (and still be uploaded) in this
# layer even after a later layer deletes them. Note: no --auto-remove here --
# it would let apt treat the *runtime* shared libs (libicu72, libzip4, etc.,
# pulled in as dependencies of the -dev packages) as orphaned and remove
# those too, silently breaking intl/gd/zip/etc. at container boot.
RUN apt-get update && apt-get install -y --no-install-recommends ${PHP_EXT_PACKAGES} \
    && install-php-extensions \
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
        sockets \
    && apt-get purge -y ${PHP_EXT_PACKAGES} \
    && rm -rf /var/lib/apt/lists/*

# --- PHP configuration ---------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

# --- Caddy ---------------------------------------------------------------
# Static binary copied from the official image rather than an apt repo/GPG
# key dance — Caddy ships CGO-disabled, so it runs unmodified on this glibc
# base image.
COPY --from=caddy:2-alpine /usr/bin/caddy /usr/local/bin/caddy
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Fail the build, not the container boot, on a broken Caddyfile -- Caddy's
# path_regexp matcher compiles with Go's RE2 engine (no lookaround support),
# a mismatch from PCRE that previously passed review and only surfaced as a
# supervisor crash-loop in production.
RUN caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile

# --- Supervisor configuration ----------------------------------------------
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor.d/ /etc/supervisor/conf.d/

# --- Non-root user matching host UID/GID (adjust for Dokploy if needed) ---
RUN usermod -u ${WWWUSER} www-data \
    && groupmod -g ${WWWGROUP} www-data

WORKDIR /var/www/html

# --- Baked-in production defaults ----------------------------------------
# Docker container/Dokploy runtime environment variables always take
# precedence over these at `docker run` time, so this only sets a safe floor
# for anything an operator forgets to configure in Dokploy's Environment tab
# -- it does not replace .env.docker.example, which documents every variable
# that must still be supplied per-deployment (secrets, hostnames, keys).
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# --- Application code -------------------------------------------------
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Re-publish Livewire's self-hosted JS (public/vendor/livewire/*, served as a
# static file by Caddy's file_server instead of round-tripping through
# PHP-FPM) against whatever livewire/livewire version composer.lock resolved
# to in THIS build -- publishing once and committing the output (as this repo
# previously did) goes stale on the next `composer update` and Livewire's own
# JS then warns "published Livewire assets are out of date" in the browser
# console, since it compares this manifest against vendor/livewire/livewire's.
RUN php artisan livewire:publish --assets \
    && chown -R www-data:www-data public/vendor

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
