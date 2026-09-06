# syntax=docker/dockerfile:1.7

##############################
# Stage 1: PHP deps (composer)
##############################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install deps without running scripts/artisan (app code not copied yet).
# --no-dev / --no-scripts kept apart from final optimize step below.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && composer run-script post-autoload-dump --no-dev || true

##############################
# Stage 2: Frontend assets
##############################
# NOTE: glibc-based image on purpose. package.json pins native optional deps
# as "*-linux-x64-gnu" (tailwindcss oxide, lightningcss, rollup) — building
# on node:*-alpine (musl) will fail to resolve/run these native binaries.
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
# Pull in vendor so any composer-published assets vite may need are present
COPY --from=vendor /app/vendor ./vendor

RUN npm run build

##############################
# Stage 3: Runtime (PHP-FPM)
##############################
FROM php:8.4-fpm-alpine AS runtime

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV} \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
        bash \
        curl \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        tzdata \
        mysql-client \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        curl-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        curl \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && apk add --no-cache fcgi \
    && curl -sSL https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
        -o /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

WORKDIR /var/www/html

# App code + vendor from build stages
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# PHP/OPcache production config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
