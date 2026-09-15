# syntax=docker/dockerfile:1

########################################
# Stage 1: Composer dependencies
########################################
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

########################################
# Stage 2: Frontend assets (Vite/Tailwind)
########################################
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Alpine's build network has no real IPv6 route; Node's fetch tries IPv6 first
# (happy eyeballs) when downloading self-hosted fonts and times out before
# falling back to IPv4. Force IPv4 lookups to avoid flaky build failures.
ENV NODE_OPTIONS=--dns-result-order=ipv4first
RUN npm run build

########################################
# Stage 3: Runtime image
########################################
FROM php:8.5-fpm-alpine

# Runtime system libraries
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpq \
        libzip \
        icu-libs \
        libpng \
        freetype \
        libjpeg-turbo

# Build-time only deps, compile extensions, then remove the toolchain
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpq-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        zip \
        opcache \
        sockets \
        bcmath \
        intl \
        pcntl \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/pear

WORKDIR /var/www/html

# Application code
COPY . .

# Bring in vendor/ and compiled assets from the earlier stages
# (placed after `COPY . .` so they win over anything stale from the host)
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# --no-scripts above skipped Composer's post-autoload-dump hook, so
# package discovery needs to run explicitly
RUN php artisan package:discover --ansi

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD wget -qO- http://localhost/ || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]